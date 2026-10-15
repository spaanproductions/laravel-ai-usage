<?php

namespace SpaanProductions\LaravelAiUsage\Support;

use Illuminate\Support\Str;

/**
 * Formatting for the views: AI calls often cost fractions of a cent, so small amounts get
 * more decimals.
 */
final class Format
{
	public static function cost(float|int|string|null $amount, string $currency = 'USD'): string
	{
		$amount = (float) $amount;
		$symbol = match (strtoupper($currency)) {
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			default => strtoupper($currency) . ' ',
		};

		$decimals = match (true) {
			$amount == 0.0 => 2,
			abs($amount) < 0.01 => 6,
			abs($amount) < 1 => 4,
			default => 2,
		};

		return $symbol . number_format($amount, $decimals);
	}

	/** A duration in milliseconds, e.g. "<1 ms", "1.4 ms", "27 ms" or "2.3 s". */
	public static function duration(float|int|null $milliseconds): string
	{
		return match (true) {
			$milliseconds === null => '—',
			$milliseconds < 1 => '<1 ms',
			$milliseconds < 10 => number_format($milliseconds, 1) . ' ms',
			$milliseconds < 1000 => number_format($milliseconds) . ' ms',
			default => number_format($milliseconds / 1000, 1) . ' s',
		};
	}

	public static function tokens(float|int|string|null $tokens): string
	{
		return number_format((int) $tokens);
	}

	/**
	 * A model price per 1M tokens in USD (OpenRouter), with at least the given decimals so the
	 * column lines up, e.g. "$3.00". More decimals (up to 4) only when the price needs them,
	 * so "$0.0001" is not rounded to "$0.00".
	 */
	public static function pricePerMillion(?float $price, int $decimals = 2): string
	{
		if ($price === null) {
			return '—';
		}

		$formatted = rtrim(number_format($price, max($decimals, 4), '.', ','), '0');
		$minimum = strlen(Str::before($formatted, '.')) + 1 + $decimals;

		return '$' . str_pad($formatted, $minimum, '0');
	}

	/** A context window, e.g. "200K" or "1.05M". */
	public static function contextLength(?int $tokens): string
	{
		return match (true) {
			$tokens === null => '—',
			$tokens >= 1_000_000 => rtrim(rtrim(number_format($tokens / 1_000_000, 2), '0'), '.') . 'M',
			$tokens >= 1_000 => rtrim(rtrim(number_format($tokens / 1_000, 1), '0'), '.') . 'K',
			default => (string) $tokens,
		};
	}

	public static function subjectType(?string $type): string
	{
		return $type === null ? '—' : class_basename($type);
	}

	public static function feature(?string $feature): string
	{
		return $feature === null ? '—' : Str::headline($feature);
	}
}
