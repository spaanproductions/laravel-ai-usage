<?php

namespace SpaanProductions\LaravelAiUsage\Events;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The spend of a budget's period crossed one of its thresholds. Fires once per budget,
 * period and threshold.
 */
class BudgetThresholdReached
{
	use Dispatchable;

	public function __construct(
		public string $budget,
		public string $period,
		public Carbon $periodStart,
		public float $threshold,
		public float $limit,
		public float $spent,
		public ?string $feature = null,
	) {
	}
}
