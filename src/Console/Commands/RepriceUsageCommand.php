<?php

namespace SpaanProductions\LaravelAiUsage\Console\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Pricing\CostCalculator;

class RepriceUsageCommand extends Command
{
	protected $signature = 'ai-usage:reprice
		{--missing-only : Only reprice rows that had no pricing when they were recorded}
		{--from= : Only rows recorded on or after this date}
		{--to= : Only rows recorded on or before this date}
		{--dry-run : Show what would change without saving}';

	protected $description = 'Recalculate the cost of recorded AI usage with the current prices of the AI models';

	public function handle(): int
	{
		$calculator = resolve(CostCalculator::class);
		$dryRun = (bool) $this->option('dry-run');
		$changed = 0;
		$difference = 0.0;

		AiUsageRecord::query()
			->when($this->option('missing-only'), fn ($query) => $query->where('pricing_missing', true))
			->betweenDates(
				$this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null,
				$this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null,
			)
			->chunkById(500, function ($records) use ($calculator, $dryRun, &$changed, &$difference): void {
				/** @var AiUsageRecord $record */
				foreach ($records as $record) {
					$cost = $calculator->calculate($record->provider, $record->model, $record->input_tokens, $record->output_tokens, $record->cache_read_tokens, $record->cache_write_tokens);

					if (abs($cost->total() - $record->total_cost) < 0.00000001 && $cost->pricingMissing === $record->pricing_missing) {
						continue;
					}

					$changed++;
					$difference += $cost->total() - $record->total_cost;

					if ( ! $dryRun) {
						$record->fill($cost->toAttributes())->saveQuietly();
					}
				}
			});

		$this->components->info(sprintf(
			'%s %d row(s), a difference of %s %s.',
			$dryRun ? 'Would reprice' : 'Repriced',
			$changed,
			number_format($difference, 6),
			config('ai-usage.currency', 'USD'),
		));

		return self::SUCCESS;
	}
}
