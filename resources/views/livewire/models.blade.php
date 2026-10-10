@php
	use SpaanProductions\LaravelAiUsage\Support\Format;

	$inputClass = 'rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
	$selectClass = $inputClass . ' pr-9';
	$sortHeader = function (string $column, string $label, bool $right = false) use ($sort, $direction): string {
		$arrow = $sort === $column ? ($direction === 'asc' ? ' ↑' : ' ↓') : '';

		return sprintf('<button type="button" wire:click="sortBy(\'%s\')" class="inline-flex w-full items-center gap-1 font-medium uppercase tracking-wide hover:text-slate-900 %s">%s%s</button>', $column, $right ? 'justify-end' : '', e($label), $arrow);
	};
@endphp

<div class="space-y-6 py-6">
	<div class="flex flex-wrap items-center justify-between gap-3">
		@include('ai-usage::partials.nav', ['current' => 'ai-usage.models.index'])

		<div class="flex items-center gap-3">
			<span class="text-sm text-slate-500">
				{{ $lastSynced ? 'Prices synced ' . $lastSynced->diffForHumans() : 'Never synced' }}
			</span>
			<button type="button" wire:click="sync" wire:loading.attr="disabled" wire:target="sync" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60">
				<span wire:loading.remove wire:target="sync">Sync models</span>
				<span wire:loading wire:target="sync">Syncing…</span>
			</button>
		</div>
	</div>

	@if ($results !== [])
		<ul class="space-y-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
			@foreach ($results as $result)
				<li @class(['text-emerald-700' => ! $result['failed'], 'text-rose-600' => $result['failed']])>
					<span class="font-medium">{{ $result['label'] }}:</span> {{ $result['message'] }}
				</li>
			@endforeach
		</ul>
	@endif

	<div class="flex flex-wrap items-center gap-2">
		<input type="search" wire:model.live.debounce.400ms="search" placeholder="Search models" class="{{ $inputClass }} w-56">

		<select wire:model.live="provider" class="{{ $selectClass }}">
			<option value="">All providers</option>
			@foreach ($providers as $option)
				<option value="{{ $option }}">{{ \SpaanProductions\LaravelAiUsage\Support\AiProviders::label($option) }}</option>
			@endforeach
		</select>

		<select wire:model.live="pricing" class="{{ $selectClass }}">
			<option value="">All prices</option>
			<option value="priced">With pricing</option>
			<option value="missing">Without pricing</option>
		</select>

		<select wire:model.live="order" class="{{ $selectClass }}" aria-label="Sort">
			@if ($order === '')
				<option value="">Custom sort</option>
			@endif
			@foreach ($orders as $value => $label)
				<option value="{{ $value }}">{{ $label }}</option>
			@endforeach
		</select>

		<button type="button" wire:click="resetFilters" class="text-sm text-slate-500 hover:text-slate-900">Reset</button>

		<span class="ml-auto text-sm text-slate-500">{{ number_format($models->total()) }} models</span>
	</div>

	<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
		<div class="overflow-x-auto">
			<table class="min-w-full text-sm">
				<thead class="bg-slate-50 text-left text-xs text-slate-500">
					<tr>
						<th class="px-4 py-2">{!! $sortHeader('model_id', 'Model') !!}</th>
						<th class="px-4 py-2">{!! $sortHeader('provider', 'Provider') !!}</th>
						<th class="px-4 py-2 font-medium uppercase tracking-wide">Modalities</th>
						<th class="px-4 py-2 text-right">{!! $sortHeader('context_length', 'Context', true) !!}</th>
						<th class="px-4 py-2 text-right">{!! $sortHeader('input_price_per_million', 'Price', true) !!}</th>
						<th class="px-4 py-2 text-right font-medium uppercase tracking-wide">Cache</th>
						<th class="px-4 py-2 text-right">{!! $sortHeader('provider_created_at', 'Released', true) !!}</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($models as $model)
						<tr wire:key="model-{{ $model->id }}" class="align-top hover:bg-slate-50">
							<td class="max-w-md px-4 py-2">
								@if ($url = $model->externalUrl())
									<a href="{{ $url }}" target="_blank" rel="noopener" title="View on {{ $model->externalSiteName() }}" class="font-medium text-indigo-600 hover:underline">{{ $model->title }}</a>
								@else
									<span class="font-medium text-slate-900">{{ $model->title }}</span>
								@endif
								@if ($model->title !== $model->model_id)
									<div class="text-xs text-slate-400">{{ $model->model_id }}</div>
								@endif
								@if ($model->description)
									<p class="mt-1 line-clamp-2 text-xs text-slate-500" title="{{ $model->description }}">{{ $model->description }}</p>
								@endif
							</td>
							<td class="whitespace-nowrap px-4 py-2">
								{{ $model->providerLabel() }}
								@if ($model->owned_by && $model->owned_by !== $model->provider)
									<div class="text-xs text-slate-400">{{ $model->owned_by }}</div>
								@endif
							</td>
							<td class="whitespace-nowrap px-4 py-2 text-slate-500">
								@if (($model->modalities['input'] ?? []) !== [] || ($model->modalities['output'] ?? []) !== [])
									<div class="flex items-center gap-1">
										@foreach ($model->modalities['input'] ?? [] as $modality)
											@include('ai-usage::partials.modality', ['modality' => $modality])
										@endforeach
										<span class="px-1 text-slate-400">→</span>
										@foreach ($model->modalities['output'] ?? [] as $modality)
											@include('ai-usage::partials.modality', ['modality' => $modality])
										@endforeach
									</div>
								@else
									<span class="text-slate-300">—</span>
								@endif
							</td>
							<td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">
								{{ Format::contextLength($model->context_length) }}
								@if ($model->max_completion_tokens)
									<div class="text-xs text-slate-400">{{ Format::contextLength($model->max_completion_tokens) }} out</div>
								@endif
							</td>
							@if ($model->hasVariablePricing())
								<td colspan="2" class="whitespace-nowrap px-4 py-2 text-right text-slate-500">Variable</td>
							@else
								<td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">
									@if ($model->input_price_per_million === null && $model->output_price_per_million === null)
										<span class="text-slate-300">—</span>
									@else
										<div>{{ Format::pricePerMillion($model->input_price_per_million) }}<span class="text-xs text-slate-400">/M input</span></div>
										<div>{{ Format::pricePerMillion($model->output_price_per_million) }}<span class="text-xs text-slate-400">/M output</span></div>
									@endif
								</td>
								<td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-slate-500">
									@if ($model->cached_input_price_per_million === null && $model->cache_write_price_per_million === null)
										<span class="text-slate-300">—</span>
									@else
										@if ($model->cached_input_price_per_million !== null)
											<div>{{ Format::pricePerMillion($model->cached_input_price_per_million, 3) }}<span class="text-xs text-slate-400">/M read</span></div>
										@endif
										@if ($model->cache_write_price_per_million !== null)
											<div>{{ Format::pricePerMillion($model->cache_write_price_per_million, 3) }}<span class="text-xs text-slate-400">/M write</span></div>
										@endif
									@endif
								</td>
							@endif
							<td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-slate-500">{{ $model->provider_created_at?->format('Y-m-d') ?? '—' }}</td>
						</tr>
					@empty
						<tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">No models match these filters. Sync the models to import them.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
		<p class="border-t border-slate-100 bg-slate-50 px-4 py-2 text-xs text-slate-500">Prices in USD per million tokens (/M), from OpenRouter.</p>
	</div>

	{{ $models->links('ai-usage::partials.pagination') }}
</div>
