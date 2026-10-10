<?php

namespace SpaanProductions\LaravelAiUsage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SpaanProductions\LaravelAiUsage\Enums\Operation;
use SpaanProductions\LaravelAiUsage\Enums\UsageStatus;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;

/**
 * @extends Factory<AiUsageRecord>
 */
class AiUsageRecordFactory extends Factory
{
	protected $model = AiUsageRecord::class;

	public function definition(): array
	{
		$input = fake()->numberBetween(100, 5000);
		$output = fake()->numberBetween(50, 2000);
		$inputCost = round($input * 2.5 / 1_000_000, 8);
		$outputCost = round($output * 10 / 1_000_000, 8);

		return [
			'invocation_id' => fake()->uuid(),
			'operation' => Operation::Chat,
			'provider' => 'openai',
			'model' => 'gpt-6-luna',
			'feature' => 'document_extraction',
			'status' => UsageStatus::Success,
			'latency_ms' => fake()->numberBetween(200, 8000),
			'input_tokens' => $input,
			'output_tokens' => $output,
			'total_tokens' => $input + $output,
			'input_cost' => $inputCost,
			'output_cost' => $outputCost,
			'total_cost' => $inputCost + $outputCost,
			'currency' => 'USD',
			'prices' => ['input' => 2.5, 'cache_read' => null, 'cache_write' => null, 'output' => 10],
		];
	}

	public function failed(string $error = 'The provider is overloaded.'): static
	{
		return $this->state(fn (): array => [
			'operation' => Operation::Failover,
			'status' => UsageStatus::Failed,
			'error' => $error,
			'input_tokens' => 0,
			'output_tokens' => 0,
			'total_tokens' => 0,
			'input_cost' => 0,
			'output_cost' => 0,
			'total_cost' => 0,
		]);
	}

	public function pricingMissing(): static
	{
		return $this->state(fn (): array => [
			'pricing_missing' => true,
			'prices' => null,
			'input_cost' => 0,
			'output_cost' => 0,
			'total_cost' => 0,
		]);
	}
}
