<?php

namespace SpaanProductions\LaravelAiUsage\Support;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;

/**
 * The date ranges and filters shared by the dashboard, the record list and the export.
 */
final class UsageFilters
{
	/** @var array<string, string> */
	public const RANGES = [
		'today' => 'Today',
		'yesterday' => 'Yesterday',
		'7d' => 'Last 7 days',
		'30d' => 'Last 30 days',
		'90d' => 'Last 90 days',
		'this_month' => 'This month',
		'last_month' => 'Last month',
		'this_year' => 'This year',
		'custom' => 'Custom',
	];

	/** @var array<int, string> The filters that match a column exactly. */
	public const COLUMNS = ['provider', 'model', 'feature', 'operation', 'status', 'subject_type', 'user_id', 'conversation_id'];

	/** @return array{0: Carbon, 1: Carbon} */
	public static function range(string $range, ?string $from = null, ?string $to = null): array
	{
		return match ($range) {
			'today' => [now()->startOfDay(), now()->endOfDay()],
			'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
			'7d' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
			'90d' => [now()->subDays(89)->startOfDay(), now()->endOfDay()],
			'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
			'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
			'this_year' => [now()->startOfYear(), now()->endOfYear()],
			'custom' => [
				self::date($from)?->startOfDay() ?? now()->subDays(29)->startOfDay(),
				self::date($to)?->endOfDay() ?? now()->endOfDay(),
			],
			default => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
		};
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return Builder<AiUsageRecord>
	 */
	public static function query(Carbon $from, Carbon $until, array $filters = []): Builder
	{
		$query = AiUsageRecord::query()->whereBetween('created_at', [$from, $until]);

		foreach (self::COLUMNS as $column) {
			$value = $filters[$column] ?? null;

			if ($value === null || $value === '') {
				continue;
			}

			$value === 'none' && in_array($column, ['feature', 'subject_type', 'user_id'], true)
				? $query->whereNull($column)
				: $query->where($column, $value);
		}

		if ( ! empty($filters['pricing_missing'])) {
			$query->where('pricing_missing', true);
		}

		return $query;
	}

	private static function date(?string $date): ?Carbon
	{
		if (blank($date)) {
			return null;
		}

		try {
			return Carbon::parse($date);
		} catch (\Throwable) {
			return null;
		}
	}
}
