<?php

namespace SpaanProductions\LaravelAiUsage\Support;

/**
 * The laravel/ai text providers whose models can be listed and selected in the admin, with
 * the OpenRouter prefix used to enrich them with prices, context and capabilities.
 */
class AiProviders
{
	/** @var array<string, array{label: string, env: string, openrouter_prefix: string|null}> */
	public const PROVIDERS = [
		'openai' => ['label' => 'OpenAI', 'env' => 'OPENAI_API_KEY', 'openrouter_prefix' => 'openai'],
		'anthropic' => ['label' => 'Anthropic', 'env' => 'ANTHROPIC_API_KEY', 'openrouter_prefix' => 'anthropic'],
		'gemini' => ['label' => 'Google Gemini', 'env' => 'GEMINI_API_KEY', 'openrouter_prefix' => 'google'],
		'mistral' => ['label' => 'Mistral', 'env' => 'MISTRAL_API_KEY', 'openrouter_prefix' => 'mistralai'],
		'xai' => ['label' => 'xAI', 'env' => 'XAI_API_KEY', 'openrouter_prefix' => 'x-ai'],
		'deepseek' => ['label' => 'DeepSeek', 'env' => 'DEEPSEEK_API_KEY', 'openrouter_prefix' => 'deepseek'],
		'groq' => ['label' => 'Groq', 'env' => 'GROQ_API_KEY', 'openrouter_prefix' => null],
		'openrouter' => ['label' => 'OpenRouter', 'env' => 'OPENROUTER_API_KEY', 'openrouter_prefix' => null],
	];

	public static function label(string $provider): string
	{
		return self::PROVIDERS[$provider]['label'] ?? ucfirst($provider);
	}

	public static function envKey(string $provider): string
	{
		return self::PROVIDERS[$provider]['env'] ?? strtoupper($provider) . '_API_KEY';
	}

	/** Whether an API key for the provider is set in config/ai.php (.env). */
	public static function isConfigured(string $provider): bool
	{
		return filled(config("ai.providers.{$provider}.key"));
	}

	/** @return array<int, string> */
	public static function keys(): array
	{
		return array_keys(self::PROVIDERS);
	}
}
