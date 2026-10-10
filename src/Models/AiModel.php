<?php

namespace SpaanProductions\LaravelAiUsage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SpaanProductions\LaravelAiUsage\Support\AiProviders;
use SpaanProductions\LaravelAiUsage\Database\Factories\AiModelFactory;

/**
 * A text model of one of the AI providers, synced by `ai-usage:sync-models` and enriched with
 * prices, context and capabilities from OpenRouter. Visible models can be chosen in the
 * AI settings; the prices are used to calculate the cost of the recorded usage.
 *
 * @property int $id
 * @property string $provider
 * @property string $model_id
 * @property string|null $openrouter_id The matching OpenRouter model, e.g. "anthropic/claude-haiku-4.5".
 * @property string|null $name
 * @property string|null $description
 * @property string|null $owned_by
 * @property bool $is_visible
 * @property float|null $input_price_per_million
 * @property float|null $cached_input_price_per_million
 * @property float|null $cache_write_price_per_million
 * @property float|null $output_price_per_million
 * @property int|null $context_length
 * @property int|null $max_completion_tokens
 * @property array{input?: array<int, string>, output?: array<int, string>}|null $modalities
 * @property array<int, string>|null $supported_parameters
 * @property \Illuminate\Support\Carbon|null $provider_created_at
 * @property \Illuminate\Support\Carbon|null $openrouter_synced_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $title
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */
class AiModel extends Model
{
	/** @use HasFactory<AiModelFactory> */
	use HasFactory;
	use SoftDeletes;

	public $guarded = [];

	/** The table name from `ai-usage.tables.models`, so it can be changed when the name is taken. */
	public function getTable(): string
	{
		return (string) config('ai-usage.tables.models', 'ai_models');
	}

	protected static function newFactory(): AiModelFactory
	{
		return AiModelFactory::new();
	}

	protected function casts(): array
	{
		return [
			'is_visible' => 'boolean',
			'input_price_per_million' => 'float',
			'cached_input_price_per_million' => 'float',
			'cache_write_price_per_million' => 'float',
			'output_price_per_million' => 'float',
			'context_length' => 'integer',
			'max_completion_tokens' => 'integer',
			'modalities' => 'array',
			'supported_parameters' => 'array',
			'provider_created_at' => 'datetime',
			'openrouter_synced_at' => 'datetime',
		];
	}

	/** @param Builder<AiModel> $query */
	public function scopeSelectable(Builder $query): void
	{
		$query->where('is_visible', true);
	}

	/** The value used in the settings: "provider:model_id". */
	public function selectionKey(): string
	{
		return self::selectionKeyFor($this->provider, $this->model_id);
	}

	public static function selectionKeyFor(string $provider, string $modelId): string
	{
		return $provider . ':' . $modelId;
	}

	/**
	 * Page with the details of the model: its OpenRouter page when it was matched there,
	 * otherwise the provider's own model documentation when it has a page per model.
	 */
	public function externalUrl(): ?string
	{
		if ($this->openrouter_id !== null) {
			return 'https://openrouter.ai/' . $this->openrouter_id;
		}

		return match ($this->provider) {
			'openrouter' => 'https://openrouter.ai/' . $this->model_id,
			'openai' => 'https://platform.openai.com/docs/models/' . $this->model_id,
			'gemini' => 'https://ai.google.dev/gemini-api/docs/models#' . $this->model_id,
			'groq' => 'https://console.groq.com/docs/model/' . $this->model_id,
			default => null,
		};
	}

	public function externalSiteName(): string
	{
		return $this->openrouter_id !== null || $this->provider === 'openrouter' ? 'OpenRouter' : $this->providerLabel();
	}

	/** @return Attribute<string, never> */
	protected function title(): Attribute
	{
		return Attribute::get(fn (): string => $this->name ?: $this->model_id);
	}

	public function providerLabel(): string
	{
		return AiProviders::label($this->provider);
	}

	/** Whether the model accepts the given input, e.g. "file" (PDF) or "image". Unknown without OpenRouter data. */
	public function acceptsInput(string $modality): bool
	{
		return in_array($modality, $this->modalities['input'] ?? [], true);
	}

	/**
	 * Whether the model can return JSON matching a schema, which the agents with structured
	 * output need. laravel/ai requests a JSON schema from every provider except DeepSeek, which
	 * uses JSON mode. Null when unknown (no OpenRouter data for this model).
	 */
	public function supportsStructuredOutput(): ?bool
	{
		$parameters = $this->supported_parameters ?? [];

		if ($parameters === []) {
			return null;
		}

		return in_array('structured_outputs', $parameters, true)
			|| ($this->provider === 'deepseek' && in_array('response_format', $parameters, true));
	}

	/**
	 * OpenRouter routers (e.g. "openrouter/auto") have no fixed price; OpenRouter reports a
	 * negative price for them, which is stored as is.
	 */
	public function hasVariablePricing(): bool
	{
		return ($this->input_price_per_million ?? 0) < 0 || ($this->output_price_per_million ?? 0) < 0;
	}

	public function hasPricing(): bool
	{
		return $this->input_price_per_million !== null && $this->output_price_per_million !== null && ! $this->hasVariablePricing();
	}

	/** Dropdown label, e.g. "Claude Haiku 4.5 — in $1.00 / uit $5.00 per 1M". */
	public function optionLabel(): string
	{
		$label = $this->title;

		if ($this->hasPricing()) {
			$label .= sprintf(' — in $%s / uit $%s per 1M', number_format((float) $this->input_price_per_million, 2), number_format((float) $this->output_price_per_million, 2));
		}

		return $label;
	}
}
