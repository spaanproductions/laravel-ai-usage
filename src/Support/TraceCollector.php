<?php

namespace SpaanProductions\LaravelAiUsage\Support;

use Throwable;
use Stringable;
use JsonSerializable;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\StepFailed;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Illuminate\Contracts\Support\Arrayable;
use SpaanProductions\LaravelAiUsage\AiUsage;
use SpaanProductions\LaravelAiUsage\Pricing\CostCalculator;

/**
 * Buffers the steps and tool calls of running agent calls (laravel/ai 1.x events), so the
 * full execution trace can be stored on the agent's usage row when it completes. Each span
 * keeps its start offset from the start of the call and its duration, in milliseconds.
 * Registered as a singleton; only used when `ai-usage.trace.enabled` is on.
 */
class TraceCollector
{
	/** @var array<string, array{started: int, spans: list<array<string, mixed>>, open: array<string, int>}> */
	private array $runs = [];

	public function __construct(private CostCalculator $calculator)
	{
	}

	public static function enabled(): bool
	{
		return (bool) config('ai-usage.trace.enabled', false);
	}

	public function begin(string $invocationId): void
	{
		$this->runs = Bounded::trim($this->runs, fn (array $run): int => $run['started'], AiUsage::STALE_AFTER_SECONDS, AiUsage::MAX_RUNNING);
		$this->runs[$invocationId] = ['started' => hrtime(true), 'spans' => [], 'open' => []];
	}

	/** The number of calls with buffered spans. */
	public function running(): int
	{
		return count($this->runs);
	}

	public function stepStarted(StartingStep $event): void
	{
		$this->open($event->invocationId, 'step:' . $event->stepNumber, [
			'type' => 'step',
			'number' => $event->stepNumber,
			'provider' => $event->provider->name(),
			'model' => $event->model,
			'status' => 'running',
		]);
	}

	public function stepCompleted(StepCompleted $event): void
	{
		$response = $event->response;

		$this->close($event->invocationId, 'step:' . $event->stepNumber, $event->time, [
			'type' => 'step',
			'number' => $event->stepNumber,
			'provider' => $response->meta->provider ?: $event->provider->name(),
			'model' => $response->meta->model ?: $event->model,
			'status' => 'success',
			'finish_reason' => $response->finishReason->value,
			...UsageTokens::from($response->usage),
			'tool_calls' => array_map(fn ($call): string => $call->name, $response->toolCalls),
			'text' => self::value($response->text),
			// Not on every 1.x StepResponse.
			'reasoning' => self::value(get_object_vars($response)['reasoning'] ?? null ?: null),
		]);
	}

	public function stepFailed(StepFailed $event): void
	{
		$this->close($event->invocationId, 'step:' . $event->stepNumber, $event->time, [
			'type' => 'step',
			'number' => $event->stepNumber,
			'provider' => $event->provider->name(),
			'model' => $event->model,
			...self::failure($event->exception),
		]);
	}

	public function toolStarted(InvokingTool $event): void
	{
		$this->open($event->invocationId, 'tool:' . $event->toolInvocationId, [
			'type' => 'tool',
			'id' => $event->toolInvocationId,
			'name' => self::toolName($event->tool),
			'status' => 'running',
			'arguments' => self::value($event->arguments),
		]);
	}

	public function toolInvoked(ToolInvoked $event): void
	{
		$this->close($event->invocationId, 'tool:' . $event->toolInvocationId, $event->time, [
			'type' => 'tool',
			'id' => $event->toolInvocationId,
			'name' => self::toolName($event->tool),
			'status' => 'success',
			'arguments' => self::value($event->arguments),
			'result' => self::value($event->result),
		]);
	}

	public function toolFailed(ToolFailed $event): void
	{
		$this->close($event->invocationId, 'tool:' . $event->toolInvocationId, $event->time, [
			'type' => 'tool',
			'id' => $event->toolInvocationId,
			'name' => self::toolName($event->tool),
			'arguments' => self::value($event->arguments),
			...self::failure($event->exception, toolError: true),
		]);
	}

	/**
	 * The spans of a call, ordered by start, with the cost of every step; forgets the call.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function pull(string $invocationId): array
	{
		$spans = $this->runs[$invocationId]['spans'] ?? [];
		unset($this->runs[$invocationId]);

		usort($spans, fn (array $a, array $b): int => $a['offset_ms'] <=> $b['offset_ms']);

		return array_map(fn (array $span): array => $span['type'] === 'step' ? $this->priced($span) : $span, $spans);
	}

	/**
	 * A value prepared for storage: objects become arrays or strings, sensitive values are
	 * redacted and strings are truncated to `content_limit`.
	 */
	public static function value(mixed $value): mixed
	{
		return self::truncate(Redactor::redact(self::normalize($value)));
	}

	/** @param array<string, mixed> $span */
	private function open(string $invocationId, string $key, array $span): void
	{
		$run = $this->run($invocationId);

		$this->runs[$invocationId]['open'][$key] = count($run['spans']);
		$this->runs[$invocationId]['spans'][] = [...$span, 'offset_ms' => $this->elapsed($invocationId), 'duration_ms' => null];
	}

	/** @param array<string, mixed> $span */
	private function close(string $invocationId, string $key, float $time, array $span): void
	{
		$this->run($invocationId);
		$index = $this->runs[$invocationId]['open'][$key] ?? null;
		unset($this->runs[$invocationId]['open'][$key]);

		if ($index === null) {
			// No start event: derive the start from the duration.
			$this->runs[$invocationId]['spans'][] = [...$span, 'offset_ms' => max(0.0, round($this->elapsed($invocationId) - $time, 1)), 'duration_ms' => round($time, 1)];

			return;
		}

		$this->runs[$invocationId]['spans'][$index] = [...$this->runs[$invocationId]['spans'][$index], ...$span, 'duration_ms' => round($time, 1)];
	}

	/** @return array{started: int, spans: list<array<string, mixed>>, open: array<string, int>} */
	private function run(string $invocationId): array
	{
		if ( ! isset($this->runs[$invocationId])) {
			$this->begin($invocationId);
		}

		return $this->runs[$invocationId];
	}

	private function elapsed(string $invocationId): float
	{
		return round((hrtime(true) - $this->runs[$invocationId]['started']) / 1_000_000, 1);
	}

	/**
	 * @param array<string, mixed> $span
	 * @return array<string, mixed>
	 */
	private function priced(array $span): array
	{
		if ( ! isset($span['input_tokens'])) {
			return $span;
		}

		$cost = $this->calculator->calculate(
			$span['provider'] ?? null,
			$span['model'] ?? null,
			(int) $span['input_tokens'],
			(int) ($span['output_tokens'] ?? 0),
			(int) ($span['cache_read_tokens'] ?? 0),
			(int) ($span['cache_write_tokens'] ?? 0),
		)->toAttributes();

		return [...$span, 'cost' => $cost['pricing_missing'] ? null : $cost['total_cost']];
	}

	/** @return array<string, mixed> */
	private static function failure(Throwable $exception, bool $toolError = false): array
	{
		return [
			'status' => 'failed',
			'error' => self::value($exception->getMessage()),
			'error_kind' => $toolError ? 'tool_error' : ErrorClassifier::classify($exception)->value,
		];
	}

	private static function toolName(Tool $tool): string
	{
		return method_exists($tool, 'name') ? (string) $tool->name() : class_basename($tool);
	}

	private static function normalize(mixed $value): mixed
	{
		return match (true) {
			$value === null, is_scalar($value) => $value,
			is_array($value) => array_map(self::normalize(...), $value),
			$value instanceof Arrayable => self::normalize($value->toArray()),
			$value instanceof JsonSerializable => self::normalize($value->jsonSerialize()),
			$value instanceof Stringable => (string) $value,
			default => get_debug_type($value),
		};
	}

	private static function truncate(mixed $value): mixed
	{
		if (is_string($value)) {
			return Str::limit($value, (int) config('ai-usage.content_limit', 5000));
		}

		return is_array($value) ? array_map(self::truncate(...), $value) : $value;
	}
}
