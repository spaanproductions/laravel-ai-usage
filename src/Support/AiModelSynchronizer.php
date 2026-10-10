<?php

namespace SpaanProductions\LaravelAiUsage\Support;

use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use SpaanProductions\LaravelAiUsage\Models\AiModel;

/**
 * Syncs the ai_models table with the models API of every configured provider, using the
 * provider's own model ids, and enriches them with prices, context and capabilities from
 * the public OpenRouter catalogue.
 */
class AiModelSynchronizer
{
	public const OPENROUTER_MODELS_URL = 'https://openrouter.ai/api/v1/models';

	/** @var array<int, array<string, mixed>>|null */
	private ?array $openRouterCatalogue = null;

	/** New models whose id contains one of these are hidden by default (not chat models). */
	public const HIDDEN_KEYWORDS = [
		'transcribe', 'tts', 'image', 'audio', 'realtime', 'codex', 'search', 'whisper', 'embed', 'moderation',
		'davinci', 'babbage', 'dall-e', 'sora', 'preview', 'computer-use', 'guard', 'ocr', 'turbo', 'instruct',
	];

	/**
	 * Sync the models of one provider. Returns the number of models it returned.
	 *
	 * @throws RuntimeException When the provider has no API key or the request fails.
	 */
	public function syncProvider(string $provider): int
	{
		if ( ! self::canSync($provider)) {
			throw new RuntimeException(sprintf('No API key is set for %s.', AiProviders::label($provider)));
		}

		$models = collect($this->fetch($provider));

		if ($models->isEmpty()) {
			return 0;
		}

		foreach ($models as $attributes) {
			/** @var AiModel $model */
			$model = AiModel::withTrashed()->firstOrNew(['provider' => $provider, 'model_id' => $attributes['model_id']]);

			if ( ! $model->exists) {
				$model->is_visible = $provider !== 'openrouter' && self::visibleByDefault($attributes['model_id']);
			}

			$model->fill(array_filter($attributes, fn (mixed $value): bool => $value !== null));
			$model->deleted_at = null;
			$model->save();
		}

		AiModel::query()
			->where('provider', $provider)
			->whereNotIn('model_id', $models->pluck('model_id'))
			->delete();

		return $models->count();
	}

	/**
	 * Add prices, context and capabilities from OpenRouter to the synced models. Returns the
	 * number of models that were matched.
	 */
	public function enrichFromOpenRouter(): int
	{
		$catalogue = collect($this->openRouterCatalogue())->keyBy('id');
		$matched = 0;

		AiModel::query()->each(function (AiModel $model) use ($catalogue, &$matched): void {
			$entry = $model->provider === 'openrouter'
				? $catalogue->get($model->model_id)
				: $this->findInCatalogue($catalogue, $model);

			if ($entry === null) {
				return;
			}

			$model->fill(self::openRouterAttributes($entry))->save();
			$matched++;
		});

		return $matched;
	}

	/**
	 * Providers whose models can be synced: those with an API key, and always OpenRouter,
	 * whose models list is public. OpenRouter models can only be chosen with OPENROUTER_API_KEY.
	 *
	 * @return array<int, string>
	 */
	public static function syncableProviders(): array
	{
		return array_values(array_filter(AiProviders::keys(), fn (string $provider): bool => self::canSync($provider)));
	}

	public static function canSync(string $provider): bool
	{
		return $provider === 'openrouter' || AiProviders::isConfigured($provider);
	}

	public static function visibleByDefault(string $modelId): bool
	{
		return ! Str::contains(Str::lower($modelId), self::HIDDEN_KEYWORDS)
			&& ! preg_match('/\d{4}-\d{2}-\d{2}/', $modelId);
	}

	/**
	 * The provider's models, normalized to ai_models attributes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch(string $provider): array
	{
		$key = (string) config("ai.providers.{$provider}.key");

		return match ($provider) {
			'openai' => $this->openAiCompatible(rtrim((string) config('ai.providers.openai.url', 'https://api.openai.com/v1'), '/') . '/models', $key),
			'xai' => $this->openAiCompatible('https://api.x.ai/v1/models', $key),
			'deepseek' => $this->openAiCompatible('https://api.deepseek.com/models', $key),
			'groq' => $this->openAiCompatible('https://api.groq.com/openai/v1/models', $key),
			'mistral' => $this->mistral($key),
			'anthropic' => $this->anthropic($key),
			'gemini' => $this->gemini($key),
			'openrouter' => $this->openRouter(),
			default => throw new RuntimeException(sprintf('Models of %s cannot be synced.', $provider)),
		};
	}

	/** @return array<int, array<string, mixed>> */
	private function openAiCompatible(string $url, string $key): array
	{
		return collect($this->request(Http::withToken($key), $url)['data'] ?? [])
			->map(fn (array $model): array => [
				'model_id' => (string) $model['id'],
				'owned_by' => $model['owned_by'] ?? null,
				'context_length' => $model['context_window'] ?? null,
				'provider_created_at' => isset($model['created']) ? Carbon::createFromTimestamp((int) $model['created']) : null,
			])
			->values()
			->all();
	}

	/** @return array<int, array<string, mixed>> */
	private function mistral(string $key): array
	{
		return collect($this->request(Http::withToken($key), 'https://api.mistral.ai/v1/models')['data'] ?? [])
			->filter(fn (array $model): bool => ($model['capabilities']['completion_chat'] ?? true) === true)
			->map(fn (array $model): array => [
				'model_id' => (string) $model['id'],
				'name' => $model['name'] ?? null,
				'description' => $model['description'] ?? null,
				'owned_by' => $model['owned_by'] ?? null,
				'context_length' => $model['max_context_length'] ?? null,
				'provider_created_at' => isset($model['created']) ? Carbon::createFromTimestamp((int) $model['created']) : null,
			])
			->unique('model_id')
			->values()
			->all();
	}

	/** @return array<int, array<string, mixed>> */
	private function anthropic(string $key): array
	{
		$url = rtrim((string) config('ai.providers.anthropic.url', 'https://api.anthropic.com/v1'), '/') . '/models';
		$request = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01']);

		return collect($this->request($request, $url, ['limit' => 1000])['data'] ?? [])
			->map(fn (array $model): array => [
				'model_id' => (string) $model['id'],
				'name' => $model['display_name'] ?? null,
				'owned_by' => 'anthropic',
				'provider_created_at' => isset($model['created_at']) ? Carbon::parse($model['created_at']) : null,
			])
			->values()
			->all();
	}

	/** @return array<int, array<string, mixed>> */
	private function gemini(string $key): array
	{
		$url = rtrim((string) config('ai.providers.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/'), '/') . '/models';

		return collect($this->request(Http::withHeaders(['x-goog-api-key' => $key]), $url, ['pageSize' => 1000])['models'] ?? [])
			->filter(fn (array $model): bool => in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true))
			->map(fn (array $model): array => [
				'model_id' => Str::after((string) $model['name'], 'models/'),
				'name' => $model['displayName'] ?? null,
				'description' => $model['description'] ?? null,
				'owned_by' => 'google',
				'context_length' => $model['inputTokenLimit'] ?? null,
				'max_completion_tokens' => $model['outputTokenLimit'] ?? null,
			])
			->values()
			->all();
	}

	/** @return array<int, array<string, mixed>> */
	private function openRouter(): array
	{
		return collect($this->openRouterCatalogue())
			->map(fn (array $model): array => [
				'model_id' => (string) $model['id'],
				'owned_by' => Str::before((string) $model['id'], '/'),
				'provider_created_at' => isset($model['created']) ? Carbon::createFromTimestamp((int) $model['created']) : null,
				...self::openRouterAttributes($model),
			])
			->values()
			->all();
	}

	/**
	 * The public OpenRouter models list, fetched once per sync (with the API key when set).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function openRouterCatalogue(): array
	{
		if ($this->openRouterCatalogue === null) {
			$request = AiProviders::isConfigured('openrouter')
				? Http::withToken((string) config('ai.providers.openrouter.key'))
				: Http::withHeaders([]);

			$this->openRouterCatalogue = array_values((array) ($this->request($request, self::OPENROUTER_MODELS_URL)['data'] ?? []));
		}

		return $this->openRouterCatalogue;
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>
	 */
	private function request(PendingRequest $request, string $url, array $query = []): array
	{
		$response = $request->timeout(30)->acceptJson()->get($url, $query);

		if ( ! $response->successful()) {
			throw new RuntimeException(sprintf('The models request to %s failed with HTTP status %d.', parse_url($url, PHP_URL_HOST), $response->status()));
		}

		return (array) $response->json();
	}

	/**
	 * Find the OpenRouter entry of a provider model, e.g. "anthropic/claude-haiku-4.5" for
	 * "claude-haiku-4-5-20251001": ids are compared without dots, dates and "-latest".
	 *
	 * @param Collection<string, array<string, mixed>> $catalogue
	 * @return array<string, mixed>|null
	 */
	private function findInCatalogue(Collection $catalogue, AiModel $model): ?array
	{
		$prefix = AiProviders::PROVIDERS[$model->provider]['openrouter_prefix'] ?? null;

		if ($prefix === null) {
			return null;
		}

		if ($catalogue->has($prefix . '/' . $model->model_id)) {
			return $catalogue->get($prefix . '/' . $model->model_id);
		}

		$entries = $catalogue->filter(fn (array $entry, string $id): bool => str_starts_with($id, $prefix . '/') && ! str_contains($id, ':'));
		$exact = self::normalizeId($model->model_id, stripDate: false);
		$undated = self::normalizeId($model->model_id, stripDate: true);

		return $entries->first(fn (array $entry, string $id): bool => self::normalizeId(Str::after($id, '/'), stripDate: false) === $exact)
			?? $entries->first(fn (array $entry, string $id): bool => self::normalizeId(Str::after($id, '/'), stripDate: true) === $undated);
	}

	public static function normalizeId(string $id, bool $stripDate): string
	{
		$id = str_replace('.', '-', Str::lower($id));
		$id = (string) preg_replace('/-latest$/', '', $id);

		if ($stripDate) {
			$id = (string) preg_replace('/-(\d{4}-\d{2}-\d{2}|\d{8}|\d{4})$/', '', $id);
		}

		return $id;
	}

	/**
	 * @param array<string, mixed> $entry
	 * @return array<string, mixed>
	 */
	private static function openRouterAttributes(array $entry): array
	{
		$perMillion = fn (mixed $price): ?float => is_numeric($price) ? round((float) $price * 1_000_000, 4) : null;

		return [
			'name' => $entry['name'] ?? null,
			'description' => $entry['description'] ?? null,
			'context_length' => $entry['context_length'] ?? null,
			'max_completion_tokens' => $entry['top_provider']['max_completion_tokens'] ?? null,
			'modalities' => [
				'input' => $entry['architecture']['input_modalities'] ?? [],
				'output' => $entry['architecture']['output_modalities'] ?? [],
			],
			'supported_parameters' => $entry['supported_parameters'] ?? [],
			'input_price_per_million' => $perMillion($entry['pricing']['prompt'] ?? null),
			'cached_input_price_per_million' => $perMillion($entry['pricing']['input_cache_read'] ?? null),
			'cache_write_price_per_million' => $perMillion($entry['pricing']['input_cache_write'] ?? null),
			'output_price_per_million' => $perMillion($entry['pricing']['completion'] ?? null),
			'openrouter_id' => $entry['id'] ?? null,
			'openrouter_synced_at' => now(),
		];
	}
}
