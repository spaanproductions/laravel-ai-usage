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
		@include('ai-usage::partials.nav', ['current' => 'ai-usage.records.index'])
		@include('ai-usage::partials.range')
	</div>

	<div class="flex flex-wrap items-center gap-2">
		<select wire:model.live="provider" class="{{ $selectClass }}">
			<option value="">All providers</option>
			@foreach ($providers as $option)
				<option value="{{ $option }}">{{ $option }}</option>
			@endforeach
		</select>

		<select wire:model.live="model" class="{{ $selectClass }}">
			<option value="">All models</option>
			@foreach ($models as $option)
				<option value="{{ $option }}">{{ $option }}</option>
			@endforeach
		</select>

		<select wire:model.live="feature" class="{{ $selectClass }}">
			<option value="">All features</option>
			<option value="none">No feature</option>
			@foreach ($features as $option)
				<option value="{{ $option }}">{{ Format::feature($option) }}</option>
			@endforeach
		</select>

		<select wire:model.live="subjectType" class="{{ $selectClass }}">
			<option value="">All subjects</option>
			<option value="none">No subject</option>
			@foreach ($subjectTypes as $option)
				<option value="{{ $option }}">{{ Format::subjectType($option) }}</option>
			@endforeach
		</select>

		<select wire:model.live="operation" class="{{ $selectClass }}">
			<option value="">All operations</option>
			@foreach ($operations as $option)
				<option value="{{ $option->value }}">{{ $option->label() }}</option>
			@endforeach
		</select>

		<select wire:model.live="status" class="{{ $selectClass }}">
			<option value="">All statuses</option>
			@foreach ($statuses as $option)
				<option value="{{ $option->value }}">{{ $option->label() }}</option>
			@endforeach
		</select>

		<input type="text" wire:model.live.debounce.400ms="userId" placeholder="User id" class="{{ $inputClass }} w-24">

		<label class="inline-flex items-center gap-2 text-sm text-slate-600">
			<input type="checkbox" wire:model.live="pricingMissing" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
			Without pricing
		</label>

		@if ($conversationId !== '')
			<span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs text-indigo-700">
				Conversation {{ \Illuminate\Support\Str::limit($conversationId, 12) }}
				<button type="button" wire:click="$set('conversationId', '')" class="hover:text-indigo-900">&times;</button>
			</span>
		@endif

		<button type="button" wire:click="resetFilters" class="text-sm text-slate-500 hover:text-slate-900">Reset</button>

		<button type="button" wire:click="export" class="ml-auto rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
			Export CSV
		</button>
	</div>

	<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
		<div class="overflow-x-auto">
			<table class="min-w-full text-sm">
				<thead class="bg-slate-50 text-left text-xs text-slate-500">
					<tr>
						<th class="px-4 py-2">{!! $sortHeader('created_at', 'Date') !!}</th>
						<th class="px-4 py-2">{!! $sortHeader('model', 'Model') !!}</th>
						<th class="px-4 py-2">{!! $sortHeader('feature', 'Feature') !!}</th>
						<th class="px-4 py-2 font-medium uppercase tracking-wide">Subject</th>
						<th class="px-4 py-2 font-medium uppercase tracking-wide">User</th>
						<th class="px-4 py-2 text-right">{!! $sortHeader('total_tokens', 'Tokens', true) !!}</th>
						<th class="px-4 py-2 text-right">{!! $sortHeader('latency_ms', 'Latency', true) !!}</th>
						<th class="px-4 py-2 text-right">{!! $sortHeader('total_cost', 'Cost', true) !!}</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($records as $record)
						<tr wire:key="record-{{ $record->id }}" class="hover:bg-slate-50">
							<td class="whitespace-nowrap px-4 py-2">
								<a href="{{ route('ai-usage.records.show', $record) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $record->created_at?->format('Y-m-d H:i:s') }}</a>
								<div class="text-xs text-slate-400">
									{{ $record->operation->label() }}
									@if ($record->isFailed())
										· <span class="text-rose-600">{{ $record->status->label() }}</span>
									@endif
								</div>
							</td>
							<td class="px-4 py-2">
								<div class="text-slate-900">{{ $record->model ?? '—' }}</div>
								<div class="text-xs text-slate-400">{{ $record->provider }}</div>
							</td>
							<td class="px-4 py-2">
								{{ Format::feature($record->feature) }}
								@if ($record->agent)
									<div class="text-xs text-slate-400">{{ class_basename($record->agent) }}</div>
								@endif
							</td>
							<td class="px-4 py-2">
								@if ($record->subject_type)
									{{ Format::subjectType($record->subject_type) }} <span class="text-slate-400">#{{ $record->subject_id }}</span>
								@else
									<span class="text-slate-300">—</span>
								@endif
							</td>
							<td class="px-4 py-2">
								{{ $record->user?->name ?? ($record->user_id ? '#' . $record->user_id : '—') }}
							</td>
							<td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">
								{{ Format::tokens($record->total_tokens) }}
								<div class="text-xs text-slate-400">{{ Format::tokens($record->input_tokens + $record->cache_read_tokens + $record->cache_write_tokens) }} / {{ Format::tokens($record->output_tokens) }}</div>
							</td>
							<td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-slate-500">
								{{ $record->latency_ms !== null ? number_format($record->latency_ms / 1000, 1) . ' s' : '—' }}
							</td>
							<td class="whitespace-nowrap px-4 py-2 text-right font-medium tabular-nums">
								@if ($record->pricing_missing)
									<span class="text-amber-600" title="No pricing for this model">?</span>
								@else
									{{ Format::cost($record->total_cost, $record->currency) }}
								@endif
							</td>
						</tr>
					@empty
						<tr><td colspan="8" class="px-4 py-12 text-center text-slate-400">No usage matches these filters.</td></tr>
					@endforelse
				</tbody>
				@if ($records->isNotEmpty())
					<tfoot class="bg-slate-50 text-sm font-medium text-slate-700">
						<tr>
							<td colspan="5" class="px-4 py-2">{{ number_format($totals->requests) }} requests</td>
							<td class="px-4 py-2 text-right tabular-nums">{{ Format::tokens($totals->tokens) }}</td>
							<td></td>
							<td class="px-4 py-2 text-right tabular-nums">{{ Format::cost($totals->cost, $currency) }}</td>
						</tr>
					</tfoot>
				@endif
			</table>
		</div>
	</div>

	{{ $records->links('ai-usage::partials.pagination') }}
</div>
