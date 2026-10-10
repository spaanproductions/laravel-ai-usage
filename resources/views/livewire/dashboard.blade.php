@php
	use SpaanProductions\LaravelAiUsage\Support\Format;

	$recordsLink = fn (array $filters = []): string => route('ai-usage.records.index', array_filter(['range' => $range, 'from' => $from, 'to' => $to, ...$filters], fn ($value) => $value !== null && $value !== ''));
@endphp

<div class="space-y-6 py-6">
	<div class="flex flex-wrap items-center justify-between gap-3">
		@include('ai-usage::partials.nav', ['current' => 'ai-usage.dashboard'])
		@include('ai-usage::partials.range')
	</div>

	<p class="text-sm text-slate-500">
		{{ $periodFrom->translatedFormat('j M Y') }} – {{ $periodUntil->translatedFormat('j M Y') }}
	</p>

	<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
		<a href="{{ $recordsLink() }}" wire:navigate class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
			<p class="text-sm text-slate-500">Cost</p>
			<p class="mt-1 text-2xl font-semibold text-slate-900">{{ Format::cost($totals->cost, $currency) }}</p>
			@if ($totals->unpriced > 0)
				<p class="mt-1 text-xs text-amber-600">{{ number_format($totals->unpriced) }} without pricing</p>
			@endif
		</a>
		<a href="{{ $recordsLink() }}" wire:navigate class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
			<p class="text-sm text-slate-500">Requests</p>
			<p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($totals->requests) }}</p>
			@if ($totals->requests > 0)
				<p class="mt-1 text-xs text-slate-400">{{ Format::cost($totals->cost / $totals->requests, $currency) }} per request</p>
			@endif
		</a>
		<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
			<p class="text-sm text-slate-500">Tokens</p>
			<p class="mt-1 text-2xl font-semibold text-slate-900">{{ Format::tokens($totals->tokens) }}</p>
			<p class="mt-1 text-xs text-slate-400">{{ Format::tokens($totals->input_tokens) }} in · {{ Format::tokens($totals->output_tokens) }} out</p>
		</div>
		<a href="{{ $recordsLink(['status' => 'failed']) }}" wire:navigate class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
			<p class="text-sm text-slate-500">Failures</p>
			<p @class(['mt-1 text-2xl font-semibold', 'text-rose-600' => $totals->failures > 0, 'text-slate-900' => ! $totals->failures])>{{ number_format((int) $totals->failures) }}</p>
		</a>
	</div>

	<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
		<div class="flex items-center justify-between">
			<h2 class="text-sm font-semibold text-slate-900">Cost per {{ $chart['monthly'] ? 'month' : 'day' }}</h2>
			<span class="text-xs text-slate-400">max {{ Format::cost($chart['max'], $currency) }}</span>
		</div>

		@if ($chart['max'] > 0)
			{{-- The top padding leaves room for the tooltips above the highest bars. --}}
			<div class="relative h-60 pt-16">
				<div class="flex h-full items-end gap-px">
					@foreach ($chart['bars'] as $bar)
						@php
							$position = $loop->index / max(1, $loop->count - 1);
						@endphp
						<a href="{{ route('ai-usage.records.index', ['range' => 'custom', 'from' => $bar['date']->toDateString(), 'to' => ($bar['monthly'] ? $bar['date']->copy()->endOfMonth() : $bar['date'])->toDateString()]) }}" wire:navigate
							class="group relative flex h-full flex-1 items-end">
							<div @class([
								'w-full rounded-t transition',
								'bg-indigo-500 group-hover:bg-indigo-700' => $bar['cost'] > 0,
								'bg-slate-100 group-hover:bg-slate-200' => $bar['cost'] <= 0,
							]) style="height: {{ $bar['cost'] > 0 ? max(1, round($bar['cost'] / $chart['max'] * 100, 2)) : 1 }}%"></div>

							<div @class([
								'pointer-events-none absolute bottom-full z-10 mb-2 hidden w-max rounded-lg bg-slate-900 px-3 py-2 text-xs text-white shadow-lg group-hover:block',
								'left-0' => $position < 0.2,
								'right-0' => $position > 0.8,
								'left-1/2 -translate-x-1/2' => $position >= 0.2 && $position <= 0.8,
							])>
								<p class="font-medium">{{ $bar['date']->translatedFormat($bar['monthly'] ? 'F Y' : 'l j F Y') }}</p>
								<p class="mt-0.5 text-base font-semibold">{{ Format::cost($bar['cost'], $currency) }}</p>
								<p class="text-slate-300">{{ number_format($bar['requests']) }} requests · {{ Format::tokens($bar['tokens']) }} tokens</p>
							</div>
						</a>
					@endforeach
				</div>
			</div>
			<div class="mt-2 flex justify-between text-xs text-slate-400">
				<span>{{ $chart['bars']->first()['date']->translatedFormat($chart['monthly'] ? 'M Y' : 'D j M') }}</span>
				<span>{{ $chart['bars']->get(intdiv($chart['bars']->count(), 2))['date']->translatedFormat($chart['monthly'] ? 'M Y' : 'D j M') }}</span>
				<span>{{ $chart['bars']->last()['date']->translatedFormat($chart['monthly'] ? 'M Y' : 'D j M') }}</span>
			</div>
		@else
			<p class="py-12 text-center text-sm text-slate-400">No costs in this period.</p>
		@endif
	</section>

	<div class="grid gap-6 lg:grid-cols-2">
		@foreach ($breakdowns as $column => $breakdown)
			<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
				<h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">{{ $breakdown['label'] }}</h2>
				<table class="min-w-full text-sm">
					<thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
						<tr>
							<th class="px-5 py-2 font-medium">{{ str($column)->replace('_type', '')->headline() }}</th>
							<th class="px-5 py-2 text-right font-medium">Requests</th>
							<th class="px-5 py-2 text-right font-medium">Tokens</th>
							<th class="px-5 py-2 text-right font-medium">Cost</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-slate-100">
						@forelse ($breakdown['rows'] as $row)
							@php
								$value = $row->{$column};
								$filters = [$column === 'subject_type' ? 'subject' : $column => $value ?? 'none'];
								if ($column === 'model') {
									$filters['provider'] = $row->provider;
								}
							@endphp
							<tr class="hover:bg-slate-50">
								<td class="px-5 py-2">
									<a href="{{ $recordsLink($filters) }}" wire:navigate class="text-indigo-600 hover:underline">
										@switch($column)
											@case('subject_type') {{ Format::subjectType($value) }} @break
											@case('feature') {{ Format::feature($value) }} @break
											@case('model') {{ $value ?? '—' }} <span class="text-xs text-slate-400">{{ $row->provider }}</span> @break
											@default {{ $value ?? '—' }}
										@endswitch
									</a>
								</td>
								<td class="px-5 py-2 text-right tabular-nums">{{ number_format($row->requests) }}</td>
								<td class="px-5 py-2 text-right tabular-nums">{{ Format::tokens($row->tokens) }}</td>
								<td class="px-5 py-2 text-right font-medium tabular-nums">{{ Format::cost($row->cost, $currency) }}</td>
							</tr>
						@empty
							<tr><td colspan="4" class="px-5 py-6 text-center text-slate-400">No usage in this period.</td></tr>
						@endforelse
					</tbody>
				</table>
			</section>
		@endforeach
	</div>
</div>
