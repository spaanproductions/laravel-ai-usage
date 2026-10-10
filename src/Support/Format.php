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

	public static function tokens(float|int|string|null $tokens): string
	{
		return number_format((int) $tokens);
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
