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
	/** @var array<int, string> */
	public const METRICS = ['cost', 'tokens', 'requests'];

	#[Url]
	public string $range = '30d';

	#[Url]
	public ?string $from = null;

	#[Url]
	public ?string $to = null;

	#[Url]
	public string $metric = 'cost';

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
	 * The chosen metric per hour for periods up to two days, per month for periods longer than three
	 * months and per day otherwise.
	 *
	 * @return array{granularity: string, metric: string, max: float, bars: Collection<int, array{date: Carbon, cost: float, requests: int, tokens: int}>}
	 */
	private function chart(Carbon $from, Carbon $until): array
	{
		$metric = $this->metric();
		$granularity = match (true) {
			$from->diffInHours($until) <= 48 => 'hour',
			$from->diffInDays($until) > 92 => 'month',
			default => 'day',
		};
		$format = ['hour' => 'Y-m-d H', 'day' => 'Y-m-d', 'month' => 'Y-m'][$granularity];

		$query = UsageFilters::query($from, $until);
		$bucket = $granularity === 'hour' ? $this->hourExpression($query->getModel()->getConnection()->getDriverName()) : 'DATE(created_at)';

		$buckets = $query
			->selectRaw("{$bucket} as bucket, SUM(total_cost) as cost, COUNT(*) as requests, SUM(total_tokens) as tokens")
			->groupBy(DB::raw($bucket))
			->toBase()
			->get()
			->groupBy(fn (object $row): string => Carbon::parse($row->bucket)->format($format));

		$bars = collect();
		$cursor = match ($granularity) {
			'hour' => $from->copy()->startOfHour(),
			'month' => $from->copy()->startOfMonth(),
			default => $from->copy()->startOfDay(),
		};

		while ($cursor->lte($until) && $cursor->lte(now())) {
			$rows = $buckets->get($cursor->format($format), collect());

			$bars->push([
				'date' => $cursor->copy(),
				'cost' => (float) $rows->sum('cost'),
				'requests' => (int) $rows->sum('requests'),
				'tokens' => (int) $rows->sum('tokens'),
			]);

			match ($granularity) {
				'hour' => $cursor->addHour(),
				'month' => $cursor->addMonthNoOverflow(),
				default => $cursor->addDay(),
			};
		}

		return ['granularity' => $granularity, 'metric' => $metric, 'max' => (float) $bars->max($metric), 'bars' => $bars];
	}

	/** The metric the chart shows; unknown values fall back to cost. */
	private function metric(): string
	{
		return in_array($this->metric, self::METRICS, true) ? $this->metric : 'cost';
	}

	/** An SQL expression truncating `created_at` to the hour, as a parseable date string. */
	private function hourExpression(string $driver): string
	{
		return match ($driver) {
			'mysql', 'mariadb' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
			'pgsql' => "to_char(created_at, 'YYYY-MM-DD HH24:00:00')",
			'sqlsrv' => "FORMAT(created_at, 'yyyy-MM-dd HH:00:00')",
			default => "strftime('%Y-%m-%d %H:00:00', created_at)",
		};
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
