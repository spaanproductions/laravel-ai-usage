<?php

namespace SpaanProductions\LaravelAiUsage\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Remembers that a budget threshold was reached in a period, so the alert fires once.
 *
 * @property int $id
 * @property string $budget
 * @property \Illuminate\Support\Carbon $period_start
 * @property float $threshold
 * @property float $limit
 * @property float $spent
 * @property \Illuminate\Support\Carbon $notified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
class AiUsageBudgetAlert extends Model
{
	protected $table = 'ai_usage_budget_alerts';

	public $guarded = [];

	protected function casts(): array
	{
		return [
			'period_start' => 'date',
			'threshold' => 'float',
			'limit' => 'float',
			'spent' => 'float',
			'notified_at' => 'datetime',
		];
	}
}
