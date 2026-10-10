<?php

namespace SpaanProductions\LaravelAiUsage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SpaanProductions\LaravelAiUsage\Models\AiModel;

/**
 * @extends Factory<AiModel>
 */
class AiModelFactory extends Factory
{
	protected $model = AiModel::class;

	public function definition(): array
	{
		return [
			'provider' => 'openai',
			'model_id' => 'gpt-' . fake()->unique()->numerify('#.#') . '-' . fake()->word(),
			'name' => null,
			'owned_by' => 'openai',
			'is_visible' => true,
			'modalities' => ['input' => ['text'], 'output' => ['text']],
			'supported_parameters' => ['max_tokens', 'response_format', 'structured_outputs'],
			'provider_created_at' => fake()->dateTimeBetween('-1 year'),
		];
	}

	public function hidden(): static
	{
		return $this->state(fn (): array => ['is_visible' => false]);
	}

	public function withoutStructuredOutput(): static
	{
		return $this->state(fn (): array => ['supported_parameters' => ['max_tokens', 'temperature']]);
	}

	/** No OpenRouter data, so the capabilities are unknown. */
	public function unknownCapabilities(): static
	{
		return $this->state(fn (): array => ['supported_parameters' => null]);
	}

	public function readsFiles(): static
	{
		return $this->state(fn (): array => ['modalities' => ['input' => ['text', 'image', 'file'], 'output' => ['text']]]);
	}

	public function withPricing(float $input = 2.5, float $output = 10, ?float $cachedInput = null, ?float $cacheWrite = null): static
	{
		return $this->state(fn (): array => [
			'input_price_per_million' => $input,
			'cached_input_price_per_million' => $cachedInput,
			'cache_write_price_per_million' => $cacheWrite,
			'output_price_per_million' => $output,
		]);
	}
}
