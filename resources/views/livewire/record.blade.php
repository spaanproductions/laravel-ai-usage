@php
	use SpaanProductions\LaravelAiUsage\Support\Format;

	$rows = [
		['Input', $record->input_tokens, $record->prices['input'] ?? null, $record->input_cost],
		['Cache read', $record->cache_read_tokens, $record->prices['cache_read'] ?? null, $record->cache_read_cost],
		['Cache write', $record->cache_write_tokens, $record->prices['cache_write'] ?? null, $record->cache_write_cost],
		['Output', $record->output_tokens, $record->prices['output'] ?? null, $record->output_cost],
	];
@endphp

<div class="space-y-6 py-6">
	<div class="flex flex-wrap items-center justify-between gap-3">
		@include('ai-usage::partials.nav', ['current' => 'ai-usage.records.show'])
		<a href="{{ route('ai-usage.records.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-indigo-600">&larr; All records</a>
	</div>

	<div class="flex flex-wrap items-start justify-between gap-4">
		<div>
			<h1 class="text-xl font-semibold text-slate-900">{{ $record->model ?? 'Unknown model' }}</h1>
			<p class="text-sm text-slate-500">
				{{ $record->provider }} · {{ $record->operation->label() }} · {{ $record->created_at?->format('Y-m-d H:i:s') }}
			</p>
		</div>
		<div class="text-right">
			<p class="text-2xl font-semibold text-slate-900">
				{{ $record->pricing_missing ? 'No pricing' : Format::cost($record->total_cost, $currency) }}
			</p>
			<span @class([
				'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
				'bg-emerald-50 text-emerald-700' => ! $record->isFailed(),
				'bg-rose-50 text-rose-700' => $record->isFailed(),
			])>{{ $record->status->label() }}</span>
		</div>
	</div>

	@if ($record->error)
		<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
			@if ($record->error_kind)
				<span class="mr-2 rounded-md bg-rose-100 px-1.5 py-0.5 text-xs font-medium">{{ $record->error_kind->label() }}</span>
			@endif
			{{ $record->error }}
		</div>
	@endif

	@if ($trace !== null)
		@if (! $trace->root()->is($record))
			<p class="text-sm text-slate-500">
				Part of the trace of
				<a href="{{ route('ai-usage.records.show', $trace->root()) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $trace->root()->agent ? class_basename($trace->root()->agent) : $trace->root()->operation->label() }}</a>.
			</p>
		@endif

		@include('ai-usage::partials.trace', ['trace' => $trace, 'record' => $record, 'currency' => $currency])
	@endif

	<div class="grid gap-6 lg:grid-cols-3">
		<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
			<h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Tokens and cost</h2>
			<table class="min-w-full text-sm">
				<thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-2 font-medium"></th>
						<th class="px-5 py-2 text-right font-medium">Tokens</th>
						<th class="px-5 py-2 text-right font-medium">Price per 1M</th>
						<th class="px-5 py-2 text-right font-medium">Cost</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@foreach ($rows as [$label, $tokens, $price, $cost])
						<tr>
							<td class="px-5 py-2 text-slate-600">{{ $label }}</td>
							<td class="px-5 py-2 text-right tabular-nums">{{ Format::tokens($tokens) }}</td>
							<td class="px-5 py-2 text-right tabular-nums text-slate-500">{{ $price !== null ? Format::cost($price, $currency) : '—' }}</td>
							<td class="px-5 py-2 text-right tabular-nums">{{ Format::cost($cost, $currency) }}</td>
						</tr>
					@endforeach
					@if ($record->reasoning_tokens > 0)
						<tr>
							<td class="px-5 py-2 text-slate-400">of which reasoning</td>
							<td class="px-5 py-2 text-right tabular-nums text-slate-400">{{ Format::tokens($record->reasoning_tokens) }}</td>
							<td colspan="2"></td>
						</tr>
					@endif
				</tbody>
				<tfoot class="bg-slate-50 font-medium text-slate-900">
					<tr>
						<td class="px-5 py-2">Total</td>
						<td class="px-5 py-2 text-right tabular-nums">{{ Format::tokens($record->total_tokens) }}</td>
						<td></td>
						<td class="px-5 py-2 text-right tabular-nums">{{ Format::cost($record->total_cost, $currency) }}</td>
					</tr>
				</tfoot>
			</table>
		</section>

		<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
			<h2 class="mb-3 text-sm font-semibold text-slate-900">Details</h2>
			<dl class="space-y-2 text-sm">
				<div class="flex justify-between gap-4">
					<dt class="text-slate-500">Feature</dt>
					<dd class="text-right">{{ Format::feature($record->feature) }}</dd>
				</div>
				<div class="flex justify-between gap-4">
					<dt class="text-slate-500">Agent</dt>
					<dd class="text-right break-all">{{ $record->agent ? class_basename($record->agent) : '—' }}</dd>
				</div>
				<div class="flex justify-between gap-4">
					<dt class="text-slate-500">Subject</dt>
					<dd class="text-right">
						@if ($record->subject_type)
							@if ($subjectUrl)
								<a href="{{ $subjectUrl }}" class="text-indigo-600 hover:underline">{{ Format::subjectType($record->subject_type) }} #{{ $record->subject_id }}</a>
							@else
								{{ Format::subjectType($record->subject_type) }} #{{ $record->subject_id }}
							@endif
							@if ($record->subject === null)
								<span class="text-xs text-slate-400">(deleted)</span>
							@endif
						@else
							—
						@endif
					</dd>
				</div>
				<div class="flex justify-between gap-4">
					<dt class="text-slate-500">User</dt>
					<dd class="text-right">{{ $record->user?->name ?? ($record->user_id ? '#' . $record->user_id : '—') }}</dd>
				</div>
				<div class="flex justify-between gap-4">
					<dt class="text-slate-500">Latency</dt>
					<dd class="text-right">{{ $record->latency_ms !== null ? number_format($record->latency_ms) . ' ms' : '—' }}</dd>
				</div>
				<div class="flex justify-between gap-4">
					<dt class="text-slate-500">Priced with</dt>
					<dd class="text-right">
						@if ($record->aiModel)
							{{ $record->aiModel->title }}
						@else
							—
						@endif
					</dd>
				</div>
				<div class="flex justify-between gap-4">
					<dt class="text-slate-500">Invocation</dt>
					<dd class="text-right font-mono text-xs break-all">{{ $record->invocation_id ?? '—' }}</dd>
				</div>
				@if ($record->trace_id && $record->trace_id !== $record->invocation_id)
					<div class="flex justify-between gap-4">
						<dt class="text-slate-500">Trace</dt>
						<dd class="text-right font-mono text-xs break-all">{{ $record->trace_id }}</dd>
					</div>
				@endif
				<div class="flex justify-between gap-4">
					<dt class="text-slate-500">Conversation</dt>
					<dd class="text-right font-mono text-xs break-all">
						@if ($record->conversation_id)
							<a href="{{ route('ai-usage.records.index', ['conversation' => $record->conversation_id, 'range' => 'this_year']) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $record->conversation_id }}</a>
						@else
							—
						@endif
					</dd>
				</div>
			</dl>
		</section>
	</div>

	@if (! empty($record->metadata))
		<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
			<h2 class="mb-3 text-sm font-semibold text-slate-900">Metadata</h2>
			<pre class="overflow-x-auto rounded-lg bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode($record->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
		</section>
	@endif

	@foreach (['prompt' => 'Prompt', 'response' => 'Response'] as $field => $label)
		@if ($record->{$field} !== null)
			<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
				<h2 class="mb-3 text-sm font-semibold text-slate-900">{{ $label }}</h2>
				<pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-xs text-slate-700">{{ $record->{$field} }}</pre>
			</section>
		@endif
	@endforeach

	@if ($conversation->isNotEmpty())
		<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
			<h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Other calls in this conversation</h2>
			<ul class="divide-y divide-slate-100 text-sm">
				@foreach ($conversation as $other)
					<li class="flex justify-between gap-4 px-5 py-2">
						<a href="{{ route('ai-usage.records.show', $other) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $other->created_at?->format('Y-m-d H:i:s') }} · {{ $other->model }}</a>
						<span class="tabular-nums">{{ Format::cost($other->total_cost, $other->currency) }}</span>
					</li>
				@endforeach
			</ul>
		</section>
	@endif
</div>
