<?php

namespace SpaanProductions\LaravelAiUsage\Support;

use Illuminate\Support\Str;

/**
 * Masks sensitive values before content is stored: array values under configured keys
 * (with `*` wildcards, e.g. `*_token`) and regex matches in strings. On unless
 * `ai-usage.redaction.enabled` is turned off.
 */
class Redactor
{
	public static function enabled(): bool
	{
		return (bool) config('ai-usage.redaction.enabled', true);
	}

	public static function redact(mixed $value): mixed
	{
		if ( ! self::enabled()) {
			return $value;
		}

		return self::walk($value);
	}

	private static function walk(mixed $value): mixed
	{
		if (is_string($value)) {
			return self::text($value);
		}

		if ( ! is_array($value)) {
			return $value;
		}

		foreach ($value as $key => $item) {
			$value[$key] = is_string($key) && self::sensitive($key)
				? (string) config('ai-usage.redaction.replacement', '[redacted]')
				: self::walk($item);
		}

		return $value;
	}

	private static function sensitive(string $key): bool
	{
		$key = strtolower($key);

		foreach ((array) config('ai-usage.redaction.keys', []) as $pattern) {
			if (Str::is(strtolower((string) $pattern), $key)) {
				return true;
			}
		}

		return false;
	}

	/** Patterns are `regex => replacement`, or a plain list of regexes using the default replacement. */
	private static function text(string $text): string
	{
		$default = (string) config('ai-usage.redaction.replacement', '[redacted]');

		foreach ((array) config('ai-usage.redaction.patterns', []) as $pattern => $replacement) {
			[$pattern, $replacement] = is_int($pattern) ? [$replacement, $default] : [$pattern, $replacement];

			$text = (string) preg_replace((string) $pattern, (string) $replacement, $text);
		}

		return $text;
	}
}
