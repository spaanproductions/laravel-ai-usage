<?php

namespace SpaanProductions\LaravelAiUsage\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View as ViewFactory;
use SpaanProductions\LaravelAiUsage\Models\AiModel;
use SpaanProductions\LaravelAiUsage\Support\AiModelSynchronizer;

#[ConfiguredLayout]
#[Title('AI models')]
class Models extends Component
{
	use WithPagination;

	/** @var array<int, string> */
	public const SORTABLE = ['model_id', 'provider', 'context_length', 'input_price_per_million', 'output_price_per_million', 'provider_created_at'];

	#[Url]
	public string $search = '';

	#[Url]
	public string $provider = '';

	#[Url]
	public string $pricing = '';

	/** The options of the sort dropdown: "column:direction" => label. */
	public const ORDERS = [
		'provider_created_at:desc' => 'Newest first',
		'provider_created_at:asc' => 'Oldest first',
		'model_id:asc' => 'Model (A–Z)',
		'provider:asc' => 'Provider (A–Z)',
		'input_price_per_million:asc' => 'Cheapest input',
		'input_price_per_million:desc' => 'Most expensive input',
		'output_price_per_million:asc' => 'Cheapest output',
		'output_price_per_million:desc' => 'Most expensive output',
		'context_length:desc' => 'Largest context',
	];

	/** Columns that start descending when you sort on them: the largest or newest first. */
	private const DESCENDING_FIRST = ['provider_created_at', 'context_length'];

	#[Url]
	public string $sort = 'provider_created_at';

	#[Url]
	public string $direction = 'desc';

	public string $order = '';

	/** @var array<int, array{label: string, message: string, failed: bool}> */
	public array $results = [];

	public function updated(string $property): void
	{
		if (in_array($property, ['search', 'provider', 'pricing'], true)) {
			$this->resetPage();
		}
	}

	public function sortBy(string $column): void
	{
		if ( ! in_array($column, self::SORTABLE, true)) {
			return;
		}

		$this->direction = $this->sort === $column
			? ($this->direction === 'asc' ? 'desc' : 'asc')
			: (in_array($column, self::DESCENDING_FIRST, true) ? 'desc' : 'asc');
		$this->sort = $column;
		$this->resetPage();
	}

	/** The sort dropdown, kept in step with the sortable column headers. */
	public function updatedOrder(string $order): void
	{
		if ( ! array_key_exists($order, self::ORDERS)) {
			return;
		}

		[$this->sort, $this->direction] = explode(':', $order);
		$this->resetPage();
	}

	public function resetFilters(): void
	{
		$this->reset('search', 'provider', 'pricing');
		$this->resetPage();
	}

	/** Run the same import as `ai-usage:sync-models`. */
	public function sync(): void
	{
		Gate::authorize((string) config('ai-usage.gate', 'viewAiUsage'));

		set_time_limit(300);

		$this->results = resolve(AiModelSynchronizer::class)->syncAll();
	}

	public function render(): View
	{
		$sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'provider_created_at';
		$direction = $this->direction === 'asc' ? 'asc' : 'desc';
		$this->order = array_key_exists($sort . ':' . $direction, self::ORDERS) ? $sort . ':' . $direction : '';
		$lastSynced = AiModel::query()->max('openrouter_synced_at');

		return ViewFactory::make('ai-usage::livewire.models', [
			'models' => $this->query()
				// Models without a value (e.g. no release date) go last in both directions.
				->orderByRaw($sort . ' IS NULL')
				->orderBy($sort, $direction)
				->orderBy('model_id')
				->paginate(50),
			'orders' => self::ORDERS,
			'providers' => AiModel::query()->distinct()->orderBy('provider')->pluck('provider'),
			'lastSynced' => $lastSynced !== null ? Carbon::parse($lastSynced) : null,
		]);
	}

	/** @return Builder<AiModel> */
	private function query(): Builder
	{
		$search = trim($this->search);

		return AiModel::query()
			->when($this->provider !== '', fn (Builder $query) => $query->where('provider', $this->provider))
			->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
				foreach (['model_id', 'name', 'openrouter_id'] as $column) {
					$query->orWhere($column, 'like', '%' . $search . '%');
				}
			}))
			->when($this->pricing === 'priced', fn (Builder $query) => $query
				->where('input_price_per_million', '>=', 0)
				->where('output_price_per_million', '>=', 0))
			->when($this->pricing === 'missing', fn (Builder $query) => $query->where(fn (Builder $query) => $query
				->whereNull('input_price_per_million')
				->orWhereNull('output_price_per_million')
				->orWhere('input_price_per_million', '<', 0)
				->orWhere('output_price_per_million', '<', 0)));
	}
}
