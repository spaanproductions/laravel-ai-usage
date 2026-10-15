<?php

namespace SpaanProductions\LaravelAiUsage;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Gateway\ParentInvocation;
use Illuminate\Contracts\Auth\Authenticatable;
use SpaanProductions\LaravelAiUsage\Support\Bounded;
use SpaanProductions\LaravelAiUsage\Support\Redactor;
use SpaanProductions\LaravelAiUsage\Budgets\BudgetGuard;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Events\AiUsageRecorded;
use SpaanProductions\LaravelAiUsage\Pricing\CostCalculator;
use SpaanProductions\LaravelAiUsage\Contracts\ProvidesAiUsageContext;

/**
 * Records AI usage rows and keeps the scoped context (feature, subject, user) they are
 * attributed to. Registered as a singleton.
 */
class AiUsage
{
	/** Start times older than this are forgotten: the call failed without a completion event. */
	public const STALE_AFTER_SECONDS = 3600;

	/** The most start times kept, so a long-running worker never grows without bound. */
	public const MAX_RUNNING = 1000;

	/** @var array<int, UsageContext> */
	private array $contexts = [];

	/** @var array<string, int> Start times (hrtime, ns) of running calls, by invocation id. */
	private array $startedAt = [];

	/** @var array<string, array{trace: string, started: int}> The trace (root invocation) of recent calls, by invocation id. */
	private array $traces = [];

	/** The depth of withoutRecording() callbacks being run. */
	private int $paused = 0;

	public function __construct(private CostCalculator $calculator, private BudgetGuard $budgets)
	{
	}

	/**
	 * Run the callback with every AI call inside it attributed to this feature, subject and
	 * user. Contexts nest: the inner values win.
	 *
	 * @template TReturn
	 * @param Closure(): TReturn $callback
	 * @param array<string, mixed> $metadata
	 * @return TReturn
	 */
	public function context(Closure $callback, ?string $feature = null, ?Model $subject = null, ?Authenticatable $user = null, array $metadata = []): mixed
	{
		$this->contexts[] = $this->currentContext()->merge(new UsageContext($feature, $subject, $user, $metadata));

		try {
			return $callback();
		} finally {
			array_pop($this->contexts);
		}
	}

	public function currentContext(): UsageContext
	{
		return end($this->contexts) ?: new UsageContext;
	}

	public function enabled(): bool
	{
		return (bool) config('ai-usage.enabled', true);
	}

	/** Whether calls are recorded right now: enabled and not inside withoutRecording(). */
	public function recording(): bool
	{
		return $this->paused === 0 && $this->enabled();
	}

	/**
	 * Run the callback without recording the AI calls inside it.
	 *
	 * @template TReturn
	 * @param Closure(): TReturn $callback
	 * @return TReturn
	 */
	public function withoutRecording(Closure $callback): mixed
	{
		$this->paused++;

		try {
			return $callback();
		} finally {
			$this->paused--;
		}
	}

	/**
	 * Where a call sits in its execution trace. A sub-agent (or any call made inside a tool)
	 * names the invocation and tool invocation it was delegated from; the trace id is the
	 * invocation id of the root call, so every row of one run shares it. laravel/ai 0.x has
	 * no parent ids: every call is then its own trace.
	 *
	 * @return array{trace_id: string, parent_invocation_id: string|null, parent_tool_invocation_id: string|null}
	 */
	public function lineage(string $invocationId, ?object $prompt = null): array
	{
		[$parent, $parentTool] = $prompt !== null && property_exists($prompt, 'parentInvocationId')
			? [$prompt->parentInvocationId, $prompt->parentToolInvocationId ?? null]
			: (class_exists(ParentInvocation::class) ? ParentInvocation::current() : [null, null]);

		$trace = $this->traces[$invocationId]['trace']
			?? ($parent === null ? $invocationId : ($this->traces[$parent]['trace'] ?? $parent));

		if ( ! isset($this->traces[$invocationId])) {
			$this->traces = Bounded::trim($this->traces, fn (array $entry): int => $entry['started'], self::STALE_AFTER_SECONDS, self::MAX_RUNNING);
			$this->traces[$invocationId] = ['trace' => $trace, 'started' => hrtime(true)];
		}

		return ['trace_id' => $trace, 'parent_invocation_id' => $parent, 'parent_tool_invocation_id' => $parent === null ? null : $parentTool];
	}

	/**
	 * Remember when a call started, to measure its latency. Calls that never complete (an
	 * exception without an AgentFailed event, e.g. on laravel/ai 0.9) would otherwise stay in
	 * memory for the life of the worker, so stale and excess start times are dropped.
	 */
	public function start(string $invocationId): void
	{
		$this->startedAt = Bounded::trim($this->startedAt, fn (int $startedAt): int => $startedAt, self::STALE_AFTER_SECONDS, self::MAX_RUNNING);
		$this->startedAt[$invocationId] = hrtime(true);
	}

	/** The number of calls with a remembered start time. */
	public function running(): int
	{
		return count($this->startedAt);
	}

	/** The milliseconds since the call started, forgetting the start time. */
	public function stop(?string $invocationId): ?int
	{
		if ($invocationId === null || ! isset($this->startedAt[$invocationId])) {
			return null;
		}

		$elapsed = (int) round((hrtime(true) - $this->startedAt[$invocationId]) / 1_000_000);
		unset($this->startedAt[$invocationId]);

		return $elapsed;
	}

	/**
	 * Store a usage row: attributes it to the current context (or the agent), calculates
	 * the cost and checks the budgets.
	 *
	 * @param array<string, mixed> $attributes
	 */
	public function record(array $attributes, ?object $agent = null, ?string $prompt = null, ?string $response = null): AiUsageRecord
	{
		$context = $this->currentContext();
		$subject = $context->subject ?? ($agent instanceof ProvidesAiUsageContext ? $agent->aiUsageSubject() : null);
		$user = $context->user ?? auth()->user();

		$tokens = [
			'input_tokens' => (int) ($attributes['input_tokens'] ?? 0),
			'cache_read_tokens' => (int) ($attributes['cache_read_tokens'] ?? 0),
			'cache_write_tokens' => (int) ($attributes['cache_write_tokens'] ?? 0),
			'output_tokens' => (int) ($attributes['output_tokens'] ?? 0),
			'reasoning_tokens' => (int) ($attributes['reasoning_tokens'] ?? 0),
		];

		$cost = $this->calculator->calculate(
			$attributes['provider'] ?? null,
			$attributes['model'] ?? null,
			$tokens['input_tokens'],
			$tokens['output_tokens'],
			$tokens['cache_read_tokens'],
			$tokens['cache_write_tokens'],
		);

		$metadata = [...$context->metadata, ...($attributes['metadata'] ?? [])];

		$record = AiUsageRecord::create([
			...$attributes,
			...$tokens,
			...$cost->toAttributes(),
			'total_tokens' => $tokens['input_tokens'] + $tokens['cache_read_tokens'] + $tokens['cache_write_tokens'] + $tokens['output_tokens'],
			'agent' => $agent !== null ? $agent::class : null,
			'feature' => $context->feature ?? $this->agentFeature($agent),
			'subject_type' => $subject?->getMorphClass(),
			'subject_id' => $subject?->getKey(),
			'user_id' => $user?->getAuthIdentifier(),
			'currency' => (string) config('ai-usage.currency', 'USD'),
			'prompt' => $this->content($prompt),
			'response' => $this->content($response),
			'metadata' => $metadata === [] ? null : $metadata,
		]);

		AiUsageRecorded::dispatch($record);

		$this->budgets->evaluate($record);

		return $record;
	}

	private function agentFeature(?object $agent): ?string
	{
		if ($agent === null) {
			return null;
		}

		if ($agent instanceof ProvidesAiUsageContext && ($feature = $agent->aiUsageFeature()) !== null) {
			return $feature;
		}

		return Str::snake(class_basename($agent));
	}

	private function content(?string $text): ?string
	{
		if ($text === null || ! config('ai-usage.store_content', false)) {
			return null;
		}

		return Str::limit((string) Redactor::redact($text), (int) config('ai-usage.content_limit', 5000));
	}
}
