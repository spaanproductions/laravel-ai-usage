<?php

namespace SpaanProductions\LaravelAiUsage\Support;

/**
 * Reads the token counts of a laravel/ai usage object, for both SDK versions:
 *
 * - 0.x `Usage`: `promptTokens` excludes the cached tokens, which are reported separately,
 *   and `completionTokens` includes the reasoning tokens.
 * - 1.x `Usage` / `TextUsage`: `inputTokens` includes the cache read and cache write tokens
 *   (nullable subsets), and `outputTokens` includes the reasoning tokens.
 *
 * Both are normalized to the ai_usage_records columns, where input_tokens excludes the
 * cached tokens, so every token is charged once at its own rate.
 */
final class UsageTokens
{
	/** @return array{input_tokens: int, cache_read_tokens: int, cache_write_tokens: int, output_tokens: int, reasoning_tokens: int} */
	public static function from(?object $usage): array
	{
		if ($usage === null) {
			return self::tokens();
		}

		if (property_exists($usage, 'inputTokens')) {
			$cacheRead = (int) ($usage->cacheReadInputTokens ?? 0);
			$cacheWrite = (int) ($usage->cacheWriteInputTokens ?? 0);

			return self::tokens(
				input: max(0, (int) $usage->inputTokens - $cacheRead - $cacheWrite),
				cacheRead: $cacheRead,
				cacheWrite: $cacheWrite,
				output: (int) ($usage->outputTokens ?? 0),
				reasoning: (int) ($usage->reasoningTokens ?? 0),
			);
		}

		return self::tokens(
			input: (int) ($usage->promptTokens ?? 0),
			cacheRead: (int) ($usage->cacheReadInputTokens ?? 0),
			cacheWrite: (int) ($usage->cacheWriteInputTokens ?? 0),
			output: (int) ($usage->completionTokens ?? 0),
			reasoning: (int) ($usage->reasoningTokens ?? 0),
		);
	}

	/**
	 * The usage of an embeddings response: a `Usage` object in 1.x, a `tokens` integer in 0.x.
	 *
	 * @return array{input_tokens: int, cache_read_tokens: int, cache_write_tokens: int, output_tokens: int, reasoning_tokens: int}
	 */
	public static function fromEmbeddings(object $response): array
	{
		if (property_exists($response, 'usage')) {
			return self::from($response->usage);
		}

		return self::tokens(input: (int) ($response->tokens ?? 0));
	}

	/**
	 * Billing details of the usage that are not tokens, such as 1.x audio seconds, image
	 * tokens and search units, for the record's metadata.
	 *
	 * @return array<string, int|float>
	 */
	public static function details(?object $usage): array
	{
		if ($usage === null) {
			return [];
		}

		return array_filter([
			'audio_seconds' => $usage->audioSeconds ?? null,
			'image_input_tokens' => $usage->imageInputTokens ?? null,
			'image_output_tokens' => $usage->imageOutputTokens ?? null,
			'search_units' => $usage->searchUnits ?? null,
		], fn (mixed $value): bool => $value !== null);
	}

	/** @return array{input_tokens: int, cache_read_tokens: int, cache_write_tokens: int, output_tokens: int, reasoning_tokens: int} */
	private static function tokens(int $input = 0, int $cacheRead = 0, int $cacheWrite = 0, int $output = 0, int $reasoning = 0): array
	{
		return [
			'input_tokens' => $input,
			'cache_read_tokens' => $cacheRead,
			'cache_write_tokens' => $cacheWrite,
			'output_tokens' => $output,
			'reasoning_tokens' => $reasoning,
		];
	}
}
