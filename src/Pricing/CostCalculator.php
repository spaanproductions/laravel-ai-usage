<?php

namespace SpaanProductions\LaravelAiUsage\Pricing;

use Illuminate\Support\Str;
use SpaanProductions\LaravelAiUsage\Models\AiModel;
use SpaanProductions\LaravelAiUsage\Support\AiModelSynchronizer;

/**
 * Calculates the cost of a call with the prices (per 1M tokens) of the matching AI model.
 *
 * laravel/ai normalizes the usage of every provider: the input tokens exclude the cached
 * tokens (they are reported separately) and the output tokens include the reasoning tokens,
 * so reasoning is not charged a second time.
 */
class CostCalculator
{
	public function calculate(?string $provider, ?string $model, int $inputTokens, int $outputTokens, int $cacheReadTokens = 0, int $cacheWriteTokens = 0): CostBreakdown
	{
		$aiModel = $this->findModel($provider, $model);

		if ($aiModel === null || ! $aiModel->hasPricing()) {
			return new CostBreakdown(aiModelId: $aiModel?->id);
		}

		$input = (float) $aiModel->input_price_per_million;
		$cacheRead = self::rate($aiModel->cached_input_price_per_million) ?? $input;
		$cacheWrite = self::rate($aiModel->cache_write_price_per_million) ?? $input;
		$output = (float) $aiModel->output_price_per_million;

		return new CostBreakdown(
			inputCost: self::cost($inputTokens, $input),
			cacheReadCost: self::cost($cacheReadTokens, $cacheRead),
			cacheWriteCost: self::cost($cacheWriteTokens, $cacheWrite),
			outputCost: self::cost($outputTokens, $output),
			aiModelId: $aiModel->id,
			prices: ['input' => $input, 'cache_read' => $cacheRead, 'cache_write' => $cacheWrite, 'output' => $output],
			pricingMissing: false,
		);
	}

	/**
	 * The AI model of the provider with this id. Falls back to the same id without dots,
	 * dates and "-latest" (e.g. "claude-haiku-4-5-20251001" for "claude-haiku-4-5"), and
	 * to the OpenRouter id (e.g. "anthropic/claude-haiku-4.5").
	 */
	public function findModel(?string $provider, ?string $model): ?AiModel
	{
		if (blank($provider) || blank($model)) {
			return null;
		}

		$exact = AiModel::withTrashed()
			->where('provider', $provider)
			->where('model_id', $model)
			->orderBy('deleted_at')
			->first();

		if ($exact !== null) {
			return $exact;
		}

		$undated = AiModelSynchronizer::normalizeId(Str::afterLast($model, '/'), stripDate: true);

		return AiModel::query()
			->where('provider', $provider)
			->get()
			->first(fn (AiModel $candidate): bool => AiModelSynchronizer::normalizeId($candidate->model_id, stripDate: true) === $undated)
			?? AiModel::query()->where('openrouter_id', $model)->first();
	}

	private static function rate(?float $price): ?float
	{
		return $price === null || $price < 0 ? null : $price;
	}

	private static function cost(int $tokens, float $pricePerMillion): float
	{
		return round($tokens * $pricePerMillion / 1_000_000, 8);
	}
}
