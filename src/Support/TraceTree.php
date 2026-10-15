<?php

namespace SpaanProductions\LaravelAiUsage\Support;

use Illuminate\Support\Collection;
use SpaanProductions\LaravelAiUsage\Enums\Operation;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;

/**
 * The execution trace of a record as flat waterfall rows: every row of the trace (the root
 * call and the sub-agents and calls made inside its tools) with the steps and tool calls
 * stored on agent rows. A sub-agent hangs under the tool call that started it; its start
 * offset is that of the tool call, as rows only know their own timeline.
 */
final class TraceTree
{
	/** The most rows of one trace that are loaded. */
	public const MAX_RECORDS = 500;

	/** @var list<array<string, mixed>> */
	public array $rows = [];

	/** @var array<string, list<AiUsageRecord>> */
	private array $byTool = [];

	/** @var array<string, list<AiUsageRecord>> */
	private array $byParent = [];

	/** @var array<string, list<AiUsageRecord>> */
	private array $failovers = [];

	/** @var array<int, true> */
	private array $placed = [];

	/** @param Collection<int, AiUsageRecord> $records */
	private function __construct(public readonly AiUsageRecord $current, public readonly Collection $records)
	{
	}

	public static function for(AiUsageRecord $record): ?self
	{
		$records = $record->trace_id === null ? collect([$record]) : AiUsageRecord::query()
			->inTrace($record->trace_id)
			->orderBy('id')
			->limit(self::MAX_RECORDS)
			->get();

		if ( ! $records->contains(fn (AiUsageRecord $other): bool => $other->is($record))) {
			$records->push($record);
		}

		$tree = (new self($record, $records))->build();

		return count($tree->rows) > 1 ? $tree : null;
	}

	public function root(): AiUsageRecord
	{
		return $this->rows[0]['record'];
	}

	/** The length of the timeline in milliseconds. */
	public function duration(): float
	{
		return max(1.0, ...array_map(fn (array $row): float => (float) ($row['offset'] ?? 0) + (float) ($row['duration'] ?? 0), $this->rows));
	}

	public function cost(): float
	{
		return (float) $this->records->sum('total_cost');
	}

	public function tokens(): int
	{
		return (int) $this->records->sum('total_tokens');
	}

	/** Whether some calls with tokens could not be priced, so the cost is only partial. */
	public function partial(): bool
	{
		return $this->records->contains(fn (AiUsageRecord $record): bool => $record->pricing_missing && $record->total_tokens > 0);
	}

	public function failed(): bool
	{
		return collect($this->rows)->contains(fn (array $row): bool => $row['status'] === 'failed');
	}

	private function build(): self
	{
		$invocations = $this->records->pluck('invocation_id')->filter()->flip();
		$roots = [];

		foreach ($this->records as $record) {
			if ($record->operation === Operation::Failover && $this->records->contains(fn (AiUsageRecord $other): bool => $other->invocation_id === $record->invocation_id && $other->operation !== Operation::Failover)) {
				$this->failovers[(string) $record->invocation_id][] = $record;

				continue;
			}

			if ($record->parent_invocation_id === null || ! $invocations->has($record->parent_invocation_id)) {
				$roots[] = $record;
			} elseif ($record->parent_tool_invocation_id !== null) {
				$this->byTool[$record->parent_tool_invocation_id][] = $record;
			} else {
				$this->byParent[$record->parent_invocation_id][] = $record;
			}
		}

		foreach ($roots as $root) {
			$this->call($root, 0, 0.0);
		}

		return $this;
	}

	private function call(AiUsageRecord $record, int $depth, ?float $offset): void
	{
		if (isset($this->placed[$record->id])) {
			return;
		}

		$this->placed[$record->id] = true;
		$trace = $record->trace ?? [];

		$this->rows[] = [
			'kind' => $record->agent !== null ? 'agent' : 'call',
			'depth' => $depth,
			'label' => $record->agent !== null ? class_basename($record->agent) : $record->operation->label(),
			'sublabel' => $depth === 0 ? ($record->agent !== null ? 'Agent run' : $record->model) : ($record->agent !== null ? 'Delegated agent' : $record->model),
			'model' => $record->model,
			'offset' => $offset,
			'duration' => $record->latency_ms,
			'tokens' => $record->total_tokens,
			'cost' => $record->pricing_missing ? null : $record->total_cost,
			'priced' => $record->total_tokens > 0,
			'status' => $record->isFailed() ? 'failed' : 'success',
			'error' => $record->error,
			'record' => $record,
			'details' => array_filter([
				'Instructions' => $trace['instructions'] ?? null,
				'Input' => $trace['input'] ?? $record->prompt,
				'Output' => $trace['output'] ?? $record->response,
				'Error' => $record->error,
			], fn (mixed $value): bool => $value !== null && $value !== ''),
			'attachments' => $trace['attachments'] ?? [],
		];

		foreach ($this->failovers[(string) $record->invocation_id] ?? [] as $failover) {
			$this->rows[] = $this->row('failover', $depth + 1, 'Failover', $failover->provider . ' · ' . $failover->model, $offset, null, status: 'failed', error: $failover->error, record: $failover, details: ['Error' => $failover->error]);
		}

		foreach ($trace['spans'] ?? [] as $span) {
			$tokens = array_sum(array_map(fn (string $key): int => (int) ($span[$key] ?? 0), ['input_tokens', 'cache_read_tokens', 'cache_write_tokens', 'output_tokens']));
			$start = $offset !== null && isset($span['offset_ms']) ? $offset + (float) $span['offset_ms'] : null;

			if (($span['type'] ?? null) === 'step') {
				$this->rows[] = $this->row(
					'step',
					$depth + 1,
					'Model step ' . ((int) ($span['number'] ?? 0) + 1),
					$span['model'] ?? null,
					$start,
					$span['duration_ms'] ?? null,
					tokens: $tokens,
					cost: $span['cost'] ?? null,
					status: $span['status'] ?? 'success',
					error: $span['error'] ?? null,
					details: array_filter([
						'Text' => $span['text'] ?? null,
						'Reasoning' => $span['reasoning'] ?? null,
						'Tool calls' => $span['tool_calls'] ?? null,
						'Finish reason' => $span['finish_reason'] ?? null,
						'Tokens' => array_filter(array_intersect_key($span, array_flip(['input_tokens', 'cache_read_tokens', 'cache_write_tokens', 'output_tokens', 'reasoning_tokens']))),
						'Error' => $span['error'] ?? null,
					], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []),
					priced: $tokens > 0,
				);

				continue;
			}

			$this->rows[] = $this->row(
				'tool',
				$depth + 1,
				(string) ($span['name'] ?? 'Tool'),
				'Tool call',
				$start,
				$span['duration_ms'] ?? null,
				status: $span['status'] ?? 'success',
				error: $span['error'] ?? null,
				details: array_filter([
					'Arguments' => $span['arguments'] ?? null,
					'Result' => $span['result'] ?? null,
					'Error' => $span['error'] ?? null,
				], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []),
			);

			foreach ($this->byTool[(string) ($span['id'] ?? '')] ?? [] as $child) {
				$this->call($child, $depth + 2, $start);
			}
		}

		// Calls linked to this one without a stored tool span (tracing off, or older rows).
		$children = [...($this->byParent[(string) $record->invocation_id] ?? [])];

		foreach ($this->byTool as $toolChildren) {
			foreach ($toolChildren as $child) {
				if ($child->parent_invocation_id === $record->invocation_id) {
					$children[] = $child;
				}
			}
		}

		foreach ($children as $child) {
			$this->call($child, $depth + 1, null);
		}
	}

	/**
	 * @param array<string, mixed> $details
	 * @return array<string, mixed>
	 */
	private function row(string $kind, int $depth, string $label, ?string $sublabel, ?float $offset, int|float|null $duration, ?int $tokens = null, int|float|null $cost = null, string $status = 'success', ?string $error = null, ?AiUsageRecord $record = null, array $details = [], bool $priced = false): array
	{
		return [
			'kind' => $kind,
			'depth' => $depth,
			'label' => $label,
			'sublabel' => $sublabel,
			'model' => null,
			'offset' => $offset,
			'duration' => $duration,
			'tokens' => $tokens,
			'cost' => $cost,
			'priced' => $priced,
			'status' => $status,
			'error' => $error,
			'record' => $record,
			'details' => $details,
			'attachments' => [],
		];
	}
}
