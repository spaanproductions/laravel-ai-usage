<?php

namespace SpaanProductions\LaravelAiUsage\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View as ViewFactory;
use SpaanProductions\LaravelAiUsage\Enums\ErrorKind;
use SpaanProductions\LaravelAiUsage\Enums\Operation;
use SpaanProductions\LaravelAiUsage\Enums\UsageStatus;
use Symfony\Component\HttpFoundation\StreamedResponse;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Support\UsageFilters;

#[ConfiguredLayout]
#[Title('AI usage records')]
class Records extends Component
{
	use WithPagination;

	/** @var array<int, string> */
	public const SORTABLE = ['created_at', 'provider', 'model', 'feature', 'total_tokens', 'total_cost', 'latency_ms'];

	#[Url]
	public string $range = '30d';

	#[Url]
	public ?string $from = null;

	#[Url]
	public ?string $to = null;

	#[Url]
	public string $provider = '';

	#[Url]
	public string $model = '';

	#[Url]
	public string $feature = '';

	#[Url]
	public string $operation = '';

	#[Url]
	public string $status = '';

	#[Url(as: 'error')]
	public string $errorKind = '';

	#[Url(as: 'subject')]
	public string $subjectType = '';

	#[Url(as: 'user')]
	public string $userId = '';

	#[Url(as: 'conversation')]
	public string $conversationId = '';

	#[Url(as: 'unpriced')]
	public bool $pricingMissing = false;

	#[Url]
	public string $sort = 'created_at';

	#[Url]
	public string $direction = 'desc';

	public function updated(string $property): void
	{
		if ( ! in_array($property, ['sort', 'direction'], true)) {
			$this->resetPage();
		}
	}

	public function sortBy(string $column): void
	{
		if ( ! in_array($column, self::SORTABLE, true)) {
			return;
		}

		$this->direction = $this->sort === $column && $this->direction === 'desc' ? 'asc' : 'desc';
		$this->sort = $column;
		$this->resetPage();
	}

	public function resetFilters(): void
	{
		$this->reset('provider', 'model', 'feature', 'operation', 'status', 'errorKind', 'subjectType', 'userId', 'conversationId', 'pricingMissing');
		$this->resetPage();
	}

	public function export(): StreamedResponse
	{
		$query = $this->query()->with('user');

		return response()->streamDownload(function () use ($query): void {
			$output = fopen('php://output', 'w');

			fputcsv($output, ['id', 'date', 'operation', 'status', 'provider', 'model', 'feature', 'agent', 'subject_type', 'subject_id', 'user_id', 'input_tokens', 'cache_read_tokens', 'cache_write_tokens', 'output_tokens', 'reasoning_tokens', 'total_tokens', 'input_cost', 'cache_read_cost', 'cache_write_cost', 'output_cost', 'total_cost', 'currency', 'pricing_missing', 'latency_ms', 'invocation_id', 'conversation_id', 'trace_id', 'parent_invocation_id', 'error', 'error_kind'], escape: '');

			$query->lazyById(500)->each(function (AiUsageRecord $record) use ($output): void {
				fputcsv($output, [
					$record->id,
					$record->created_at?->toDateTimeString(),
					$record->operation->value,
					$record->status->value,
					$record->provider,
					$record->model,
					$record->feature,
					$record->agent,
					$record->subject_type,
					$record->subject_id,
					$record->user_id,
					$record->input_tokens,
					$record->cache_read_tokens,
					$record->cache_write_tokens,
					$record->output_tokens,
					$record->reasoning_tokens,
					$record->total_tokens,
					$record->input_cost,
					$record->cache_read_cost,
					$record->cache_write_cost,
					$record->output_cost,
					$record->total_cost,
					$record->currency,
					$record->pricing_missing ? 1 : 0,
					$record->latency_ms,
					$record->invocation_id,
					$record->conversation_id,
					$record->trace_id,
					$record->parent_invocation_id,
					$record->error,
					$record->error_kind?->value,
				], escape: '');
			});

			fclose($output);
		}, 'ai-usage-' . now()->format('Y-m-d-His') . '.csv', ['Content-Type' => 'text/csv']);
	}

	public function render(): View
	{
		[$from, $until] = $this->period();
		$sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'created_at';

		$records = $this->query()
			->with(['subject', 'user'])
			->orderBy($sort, $this->direction === 'asc' ? 'asc' : 'desc')
			->orderByDesc('id')
			->paginate(50);

		$options = fn (string $column) => UsageFilters::query($from, $until)->whereNotNull($column)->distinct()->orderBy($column)->pluck($column);

		return ViewFactory::make('ai-usage::livewire.records', [
			'records' => $records,
			'totals' => $this->query()->selectRaw('COUNT(*) as requests, COALESCE(SUM(total_cost), 0) as cost, COALESCE(SUM(total_tokens), 0) as tokens')->toBase()->first(),
			'providers' => $options('provider'),
			'models' => $options('model'),
			'features' => $options('feature'),
			'subjectTypes' => $options('subject_type'),
			'operations' => Operation::cases(),
			'statuses' => UsageStatus::cases(),
			'errorKinds' => ErrorKind::cases(),
			'currency' => (string) config('ai-usage.currency', 'USD'),
		]);
	}

	/** @return array{0: Carbon, 1: Carbon} */
	private function period(): array
	{
		return UsageFilters::range($this->range, $this->from, $this->to);
	}

	/** @return Builder<AiUsageRecord> */
	private function query(): Builder
	{
		[$from, $until] = $this->period();

		return UsageFilters::query($from, $until, [
			'provider' => $this->provider,
			'model' => $this->model,
			'feature' => $this->feature,
			'operation' => $this->operation,
			'status' => $this->status,
			'error_kind' => $this->errorKind,
			'subject_type' => $this->subjectType,
			'user_id' => $this->userId,
			'conversation_id' => $this->conversationId,
			'pricing_missing' => $this->pricingMissing,
		]);
	}
}
