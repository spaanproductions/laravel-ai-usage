<?php

namespace SpaanProductions\LaravelAiUsage\Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;
use SpaanProductions\LaravelAiUsage\Enums\Operation;
use SpaanProductions\LaravelAiUsage\Enums\UsageStatus;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Pricing\CostCalculator;

/**
 * Fills ai_usage_records with three months of demo usage to try out the dashboard. The rows
 * are priced with the current ai_models prices and marked with metadata.demo, so running the
 * seeder again replaces them:
 *
 *     php artisan db:seed --class="SpaanProductions\LaravelAiUsage\Database\Seeders\AiUsageDemoSeeder"
 */
class AiUsageDemoSeeder extends Seeder
{
	public const DAYS = 90;

	/**
	 * Per feature the candidate models (the first one with pricing in ai_models is used), the
	 * agent and the token ranges of a call.
	 *
	 * @var array<string, array{models: array<int, array{0: string, 1: string}>, agent: string|null, operation: Operation, weight: int, input: array{0: int, 1: int}, output: array{0: int, 1: int}}>
	 */
	private const FEATURES = [
		'document_extraction' => ['models' => [['anthropic', 'claude-haiku-4-5-20251001'], ['openrouter', 'anthropic/claude-haiku-4.5']], 'agent' => 'App\\Ai\\Agents\\DocumentExtractor', 'operation' => Operation::Chat, 'weight' => 10, 'input' => [3000, 25000], 'output' => [800, 4000]],
		'link_analysis' => ['models' => [['openai', 'gpt-4o'], ['openrouter', 'openai/gpt-4o']], 'agent' => 'App\\Ai\\Agents\\LinkAnalyzer', 'operation' => Operation::Chat, 'weight' => 6, 'input' => [800, 1500], 'output' => [150, 400]],
		'prompt_improver' => ['models' => [['gemini', 'gemini-2.5-flash'], ['openrouter', 'google/gemini-2.5-flash']], 'agent' => 'SpaanProductions\\LaravelSwitchbotFrame\\Ai\\Agents\\PromptImprover', 'operation' => Operation::Chat, 'weight' => 3, 'input' => [200, 600], 'output' => [100, 300]],
		'frame_image' => ['models' => [['openai', 'gpt-image-2'], ['openrouter', 'openai/gpt-5-image-mini'], ['gemini', 'gemini-2.5-flash-image']], 'agent' => null, 'operation' => Operation::Image, 'weight' => 2, 'input' => [50, 200], 'output' => [4000, 6500]],
		'search_index' => ['models' => [['openai', 'text-embedding-3-small']], 'agent' => null, 'operation' => Operation::Embeddings, 'weight' => 2, 'input' => [2000, 30000], 'output' => [0, 0]],
	];

	public function run(): void
	{
		AiUsageRecord::query()->where('metadata->demo', true)->delete();

		$calculator = resolve(CostCalculator::class);
		$userIds = $this->userIds();
		$features = collect(self::FEATURES)->flatMap(fn (array $feature, string $key): array => array_fill(0, $feature['weight'], $key))->all();
		$models = array_map(fn (array $feature): array => $this->pricedModel($feature['models'], $calculator), self::FEATURES);
		$rows = [];

		for ($day = self::DAYS - 1; $day >= 0; $day--) {
			$date = now()->subDays($day)->startOfDay();
			$calls = $date->isWeekend() ? random_int(2, 8) : random_int(8, 25);

			for ($i = 0; $i < $calls; $i++) {
				$feature = $features[array_rand($features)];
				$rows[] = $this->row($feature, self::FEATURES[$feature], $models[$feature], $date->copy()->addSeconds(random_int(7 * 3600, 22 * 3600)), $calculator, $userIds);
			}
		}

		foreach (array_chunk($rows, 500) as $chunk) {
			AiUsageRecord::query()->insert($chunk);
		}

		$this->command?->info(sprintf('Seeded %d demo AI usage records.', count($rows)));
	}

	/**
	 * @param array{models: array<int, array{0: string, 1: string}>, agent: string|null, operation: Operation, weight: int, input: array{0: int, 1: int}, output: array{0: int, 1: int}} $config
	 * @param array{0: string, 1: string} $model
	 * @param array<int, int|null> $userIds
	 * @return array<string, mixed>
	 */
	private function row(string $feature, array $config, array $model, Carbon $at, CostCalculator $calculator, array $userIds): array
	{
		[$provider, $modelId] = $model;
		$failed = random_int(1, 100) <= 3;
		$streamed = $config['operation'] === Operation::Chat && random_int(1, 100) <= 10;
		$input = $failed ? 0 : random_int(...$config['input']);
		$output = $failed ? 0 : random_int(...$config['output']);
		$cacheRead = ! $failed && str_contains($modelId, 'claude') && random_int(1, 100) <= 40 ? random_int(1000, 4000) : 0;
		$cacheWrite = ! $failed && $cacheRead === 0 && str_contains($modelId, 'claude') && random_int(1, 100) <= 20 ? random_int(1000, 4000) : 0;
		$reasoning = str_contains($modelId, 'gpt') && $config['operation'] === Operation::Chat ? (int) round($output * random_int(0, 30) / 100) : 0;

		$cost = $calculator->calculate($provider, $modelId, $input, $output, $cacheRead, $cacheWrite);

		return [
			'invocation_id' => (string) Str::uuid7(),
			'conversation_id' => $feature === 'prompt_improver' && random_int(1, 100) <= 50 ? 'demo-conversation-' . random_int(1, 6) : null,
			'operation' => $failed ? Operation::Failover->value : ($streamed ? Operation::Stream->value : $config['operation']->value),
			'provider' => $provider,
			'model' => $modelId,
			'ai_model_id' => $cost->aiModelId,
			'agent' => $config['agent'],
			'feature' => $feature,
			'user_id' => $userIds[array_rand($userIds)],
			'status' => $failed ? UsageStatus::Failed->value : UsageStatus::Success->value,
			'error' => $failed ? sprintf('AI provider [%s] is overloaded.', $provider) : null,
			'latency_ms' => $failed ? null : random_int(300, $config['operation'] === Operation::Image ? 40000 : 15000),
			'input_tokens' => $input,
			'cache_read_tokens' => $cacheRead,
			'cache_write_tokens' => $cacheWrite,
			'output_tokens' => $output,
			'reasoning_tokens' => $reasoning,
			'total_tokens' => $input + $cacheRead + $cacheWrite + $output,
			'input_cost' => $cost->inputCost,
			'cache_read_cost' => $cost->cacheReadCost,
			'cache_write_cost' => $cost->cacheWriteCost,
			'output_cost' => $cost->outputCost,
			'total_cost' => $cost->total(),
			'currency' => (string) config('ai-usage.currency', 'USD'),
			'pricing_missing' => $cost->pricingMissing,
			'prices' => $cost->prices === null ? null : json_encode($cost->prices),
			'metadata' => json_encode(['demo' => true]),
			'created_at' => $at,
			'updated_at' => $at,
		];
	}

	/**
	 * @param array<int, array{0: string, 1: string}> $candidates
	 * @return array{0: string, 1: string}
	 */
	private function pricedModel(array $candidates, CostCalculator $calculator): array
	{
		foreach ($candidates as [$provider, $model]) {
			if ($calculator->findModel($provider, $model)?->hasPricing()) {
				return [$provider, $model];
			}
		}

		return $candidates[0];
	}

	/** @return array<int, int|null> A few existing users, plus null for queued calls without a user. */
	private function userIds(): array
	{
		$model = (string) config('ai-usage.user_model');
		$ids = class_exists($model) ? $model::query()->limit(3)->pluck('id')->all() : [];

		return [...$ids, null, null];
	}
}
