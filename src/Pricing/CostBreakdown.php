<?php

namespace SpaanProductions\LaravelAiUsage\Pricing;

/**
 * The cost of one AI call, split by token kind, with the rates (per 1M tokens) used.
 */
final readonly class CostBreakdown
{
	/** @param array{input: float|null, cache_read: float|null, cache_write: float|null, output: float|null}|null $prices */
	public function __construct(
		public float $inputCost = 0,
		public float $cacheReadCost = 0,
		public float $cacheWriteCost = 0,
		public float $outputCost = 0,
		public ?int $aiModelId = null,
		public ?array $prices = null,
		public bool $pricingMissing = true,
	) {
	}

	public function total(): float
	{
		return round($this->inputCost + $this->cacheReadCost + $this->cacheWriteCost + $this->outputCost, 8);
	}

	/** @return array<string, mixed> The ai_usage_records attributes. */
	public function toAttributes(): array
	{
		return [
			'ai_model_id' => $this->aiModelId,
			'input_cost' => $this->inputCost,
			'cache_read_cost' => $this->cacheReadCost,
			'cache_write_cost' => $this->cacheWriteCost,
			'output_cost' => $this->outputCost,
			'total_cost' => $this->total(),
			'prices' => $this->prices,
			'pricing_missing' => $this->pricingMissing,
		];
	}
}
