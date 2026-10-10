<?php

namespace SpaanProductions\LaravelAiUsage\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;

/**
 * For models that AI calls are made for (the subject of AiUsage::context()): their usage
 * records and total AI cost. Eager load the cost with withSum('aiUsage', 'total_cost').
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
trait HasAiUsage
{
	/** @return MorphMany<AiUsageRecord, $this> */
	public function aiUsage(): MorphMany
	{
		return $this->morphMany(AiUsageRecord::class, 'subject');
	}

	/** The total AI cost of this model, using the eager loaded withSum() when present. */
	public function aiUsageCost(): float
	{
		return (float) ($this->getAttribute('ai_usage_sum_total_cost') ?? $this->aiUsage()->sum('total_cost'));
	}
}
