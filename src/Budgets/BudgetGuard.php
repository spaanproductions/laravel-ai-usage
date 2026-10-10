<?php

namespace SpaanProductions\LaravelAiUsage\Budgets;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Models\AiUsageBudgetAlert;
use SpaanProductions\LaravelAiUsage\Events\BudgetThresholdReached;
use SpaanProductions\LaravelAiUsage\Notifications\BudgetThresholdReachedNotification;

/**
 * Checks the configured budgets after a usage row is recorded and alerts once per budget,
 * period and threshold that is crossed.
 */
class BudgetGuard
{
	public function evaluate(AiUsageRecord $record): void
	{
		if ($record->total_cost <= 0) {
			return;
		}

		foreach ((array) config('ai-usage.budgets', []) as $name => $budget) {
			$feature = $budget['feature'] ?? null;

			if ($feature !== null && $feature !== $record->feature) {
				continue;
			}

			$this->evaluateBudget((string) $name, (array) $budget);
		}
	}

	/** @param array<string, mixed> $budget */
	private function evaluateBudget(string $name, array $budget): void
	{
		$limit = (float) ($budget['limit'] ?? 0);

		if ($limit <= 0) {
			return;
		}

		$period = (string) ($budget['period'] ?? 'month');
		$periodStart = self::periodStart($period);
		$feature = $budget['feature'] ?? null;

		$spent = (float) AiUsageRecord::query()
			->where('created_at', '>=', $periodStart)
			->when($feature !== null, fn ($query) => $query->where('feature', $feature))
			->sum('total_cost');

		$thresholds = collect((array) ($budget['thresholds'] ?? [1.0]))->map(fn (mixed $threshold): float => (float) $threshold)->sort();

		foreach ($thresholds as $threshold) {
			if ($spent < $limit * $threshold) {
				continue;
			}

			$inserted = AiUsageBudgetAlert::query()->insertOrIgnore([
				'budget' => $name,
				'period_start' => $periodStart->toDateString(),
				'threshold' => $threshold,
				'limit' => $limit,
				'spent' => $spent,
				'notified_at' => now(),
				'created_at' => now(),
				'updated_at' => now(),
			]);

			if ($inserted === 0) {
				continue;
			}

			$event = new BudgetThresholdReached($name, $period, $periodStart, $threshold, $limit, $spent, $feature);

			event($event);

			$addresses = array_filter((array) ($budget['mail'] ?? []));

			if ($addresses !== []) {
				Notification::route('mail', $addresses)->notify(new BudgetThresholdReachedNotification($event));
			}
		}
	}

	public static function periodStart(string $period): Carbon
	{
		return match ($period) {
			'day' => now()->startOfDay(),
			'week' => now()->startOfWeek(),
			default => now()->startOfMonth(),
		};
	}
}
