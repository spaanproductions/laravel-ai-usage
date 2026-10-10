<?php

namespace SpaanProductions\LaravelAiUsage\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use SpaanProductions\LaravelAiUsage\Enums\UsageStatus;
use SpaanProductions\LaravelAiUsage\Support\UsageFilters;

#[ConfiguredLayout]
#[Title('AI usage')]
class Dashboard extends Component
{
	#[Url]
	public string $range = '30d';

	#[Url]
	public ?string $from = null;

	#[Url]
	public ?string $to = null;

	public function render(): View
	{
		[$from, $until] = UsageFilters::range($this->range, $this->from, $this->to);

		$totals = UsageFilters::query($from, $until)
			->selectRaw('COUNT(*) as requests, COALESCE(SUM(total_cost), 0) as cost, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(input_tokens + cache_read_tokens + cache_write_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens')
			->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failures', [UsageStatus::Failed->value])
			->selectRaw('SUM(CASE WHEN pricing_missing = ? THEN 1 ELSE 0 END) as unpriced', [true])
			->toBase()
			->first();

		return ViewFactory::make('ai-usage::livewire.dashboard', [
			'periodFrom' => $from,
			'periodUntil' => $until,
			'totals' => $totals,
			'chart' => $this->chart($from, $until),
			'breakdowns' => [
				'model' => ['label' => 'By model', 'rows' => $this->breakdown($from, $until, 'model', ['provider'])],
				'feature' => ['label' => 'By feature', 'rows' => $this->breakdown($from, $until, 'feature')],
				'provider' => ['label' => 'By provider', 'rows' => $this->breakdown($from, $until, 'provider')],
				'subject_type' => ['label' => 'By subject', 'rows' => $this->breakdown($from, $until, 'subject_type')],
			],
			'currency' => (string) config('ai-usage.currency', 'USD'),
		]);
	}

	/**
	 * The cost per day of the period, or per month for periods longer than three months.
	 *
	 * @return array{monthly: bool, max: float, bars: Collection<int, array{date: Carbon, monthly: bool, cost: float, requests: int, tokens: int}>}
	 */
	private function chart(Carbon $from, Carbon $until): array
	{
		$monthly = $from->diffInDays($until) > 92;
		$format = $monthly ? 'Y-m' : 'Y-m-d';

		$buckets = UsageFilters::query($from, $until)
			->selectRaw('DATE(created_at) as day, SUM(total_cost) as cost, COUNT(*) as requests, SUM(total_tokens) as tokens')
			->groupBy(DB::raw('DATE(created_at)'))
			->toBase()
			->get()
			->groupBy(fn (object $row): string => Carbon::parse($row->day)->format($format));

		$bars = collect();
		$cursor = $monthly ? $from->copy()->startOfMonth() : $from->copy()->startOfDay();

		while ($cursor->lte($until) && $cursor->lte(now())) {
			$rows = $buckets->get($cursor->format($format), collect());

			$bars->push([
				'date' => $cursor->copy(),
				'monthly' => $monthly,
				'cost' => (float) $rows->sum('cost'),
				'requests' => (int) $rows->sum('requests'),
				'tokens' => (int) $rows->sum('tokens'),
			]);

			$monthly ? $cursor->addMonthNoOverflow() : $cursor->addDay();
		}

		return ['monthly' => $monthly, 'max' => (float) $bars->max('cost'), 'bars' => $bars];
	}

	/**
	 * @param array<int, string> $extra
	 * @return Collection<int, \stdClass>
	 */
	private function breakdown(Carbon $from, Carbon $until, string $column, array $extra = []): Collection
	{
		return UsageFilters::query($from, $until)
			->select([$column, ...$extra])
			->selectRaw('COUNT(*) as requests, SUM(total_cost) as cost, SUM(total_tokens) as tokens')
			->groupBy([$column, ...$extra])
			->orderByDesc('cost')
			->orderByDesc('requests')
			->limit(10)
			->toBase()
			->get();
	}
}
