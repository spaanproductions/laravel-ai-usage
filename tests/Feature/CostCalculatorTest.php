<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use SpaanProductions\LaravelAiUsage\Models\AiModel;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Pricing\CostCalculator;

class CostCalculatorTest extends TestCase
{
	public function test_it_charges_every_token_kind_at_its_own_rate(): void
	{
		$model = AiModel::factory()->withPricing(input: 3, output: 15, cachedInput: 0.3, cacheWrite: 3.75)->create(['provider' => 'anthropic', 'model_id' => 'claude-sonnet-5-5']);

		$cost = resolve(CostCalculator::class)->calculate('anthropic', 'claude-sonnet-5-5', inputTokens: 1_000_000, outputTokens: 100_000, cacheReadTokens: 2_000_000, cacheWriteTokens: 400_000);

		$this->assertSame(3.0, $cost->inputCost);
		$this->assertSame(0.6, $cost->cacheReadCost);
		$this->assertSame(1.5, $cost->cacheWriteCost);
		$this->assertSame(1.5, $cost->outputCost);
		$this->assertSame(6.6, $cost->total());
		$this->assertSame($model->id, $cost->aiModelId);
		$this->assertFalse($cost->pricingMissing);
	}

	public function test_cached_tokens_fall_back_to_the_input_price(): void
	{
		AiModel::factory()->withPricing(input: 2, output: 8)->create(['model_id' => 'gpt-6-luna']);

		$cost = resolve(CostCalculator::class)->calculate('openai', 'gpt-6-luna', 0, 0, cacheReadTokens: 1_000_000, cacheWriteTokens: 1_000_000);

		$this->assertSame(['input' => 2.0, 'cache_read' => 2.0, 'cache_write' => 2.0, 'output' => 8.0], $cost->prices);
		$this->assertSame(4.0, $cost->total());
	}

	public function test_a_dated_model_id_matches_the_undated_model(): void
	{
		$model = AiModel::factory()->withPricing(1, 5)->create(['provider' => 'anthropic', 'model_id' => 'claude-haiku-4-5']);

		$cost = resolve(CostCalculator::class)->calculate('anthropic', 'claude-haiku-4-5-20251001', 1_000_000, 0);

		$this->assertSame($model->id, $cost->aiModelId);
		$this->assertSame(1.0, $cost->total());
	}

	public function test_an_openrouter_id_matches_the_model(): void
	{
		$model = AiModel::factory()->withPricing(1, 5)->create(['provider' => 'openrouter', 'model_id' => 'anthropic/claude-haiku-4.5', 'openrouter_id' => 'anthropic/claude-haiku-4.5']);

		$cost = resolve(CostCalculator::class)->calculate('openrouter', 'anthropic/claude-haiku-4.5', 0, 1_000_000);

		$this->assertSame($model->id, $cost->aiModelId);
		$this->assertSame(5.0, $cost->total());
	}

	public function test_unknown_and_variable_priced_models_have_missing_pricing(): void
	{
		AiModel::factory()->withPricing(-1_000_000, -1_000_000)->create(['provider' => 'openrouter', 'model_id' => 'openrouter/auto']);
		AiModel::factory()->create(['model_id' => 'gpt-without-prices']);

		$calculator = resolve(CostCalculator::class);

		foreach ([['openai', 'gpt-unknown'], ['openrouter', 'openrouter/auto'], ['openai', 'gpt-without-prices'], [null, null]] as [$provider, $model]) {
			$cost = $calculator->calculate($provider, $model, 1000, 1000);

			$this->assertTrue($cost->pricingMissing, "{$provider}/{$model} should have missing pricing");
			$this->assertSame(0.0, $cost->total());
		}
	}
}
