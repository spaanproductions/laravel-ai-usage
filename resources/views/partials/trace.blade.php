@php
	use SpaanProductions\LaravelAiUsage\Support\Format;
	use SpaanProductions\LaravelAiUsage\Support\TraceAttachments;

	// The execution trace as a waterfall: server-rendered bars, no JS. Each row is a native
	// <details> element that opens its evidence (input, output, arguments, result).
	$aiUsageDuration = $trace->duration();
	$aiUsageIcons = [
		'agent' => ['text-indigo-600 bg-indigo-50', 'M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z'],
		'step' => ['text-violet-600 bg-violet-50', 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z'],
		'tool' => ['text-amber-600 bg-amber-50', 'M21.75 6.75a4.5 4.5 0 0 1-4.884 4.484c-1.076-.091-2.264.071-2.95.904l-7.152 8.684a2.548 2.548 0 1 1-3.586-3.586l8.684-7.152c.833-.686.995-1.874.904-2.95a4.5 4.5 0 0 1 6.336-4.486l-3.276 3.276a3.004 3.004 0 0 0 2.25 2.25l3.276-3.276c.256.565.398 1.192.398 1.852Z'],
		'call' => ['text-sky-600 bg-sky-50', 'm3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z'],
		'failover' => ['text-rose-600 bg-rose-50', 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99'],
	];
	$aiUsageBars = ['agent' => 'bg-indigo-500', 'step' => 'bg-violet-400', 'tool' => 'bg-amber-400', 'call' => 'bg-sky-400', 'failover' => 'bg-rose-400'];
	$aiUsageJson = fn (mixed $value): string => is_string($value) ? $value : (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
	<div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
		<div class="flex items-center gap-2">
			<h2 class="text-sm font-semibold text-slate-900">Execution</h2>
			<span class="rounded-md border border-slate-200 px-1.5 py-0.5 text-xs text-slate-500">{{ count($trace->rows) }} spans</span>
			@if ($trace->failed())
				<span class="rounded-md bg-rose-50 px-1.5 py-0.5 text-xs text-rose-700">Has problems</span>
			@endif
		</div>
		<dl class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
			<div class="flex gap-1.5"><dt class="text-slate-500">Elapsed</dt><dd class="tabular-nums text-slate-900">{{ Format::duration($aiUsageDuration) }}</dd></div>
			<div class="flex gap-1.5"><dt class="text-slate-500">Tokens</dt><dd class="tabular-nums text-slate-900">{{ Format::tokens($trace->tokens()) }}</dd></div>
			<div class="flex gap-1.5">
				<dt class="text-slate-500">Cost</dt>
				<dd class="tabular-nums text-slate-900">
					{{ Format::cost($trace->cost(), $currency) }}
					@if ($trace->partial())
						<span class="text-xs text-amber-600" title="Some calls have no pricing">Partial</span>
					@endif
				</dd>
			</div>
		</dl>
	</div>

	<div class="overflow-x-auto">
		<div class="min-w-[44rem]">
			<div class="grid grid-cols-[minmax(0,5fr)_minmax(0,4fr)_8rem] gap-4 bg-slate-50 px-5 py-2 text-xs text-slate-500">
				<span>Span</span>
				<span class="flex justify-between tabular-nums">
					<span>0 ms</span>
					<span>{{ Format::duration($aiUsageDuration / 2) }}</span>
					<span>{{ Format::duration($aiUsageDuration) }}</span>
				</span>
				<span class="text-right">Duration</span>
			</div>

			@foreach ($trace->rows as $row)
				@php
					[$aiUsageIconClass, $aiUsageIconPath] = $aiUsageIcons[$row['kind']] ?? $aiUsageIcons['call'];
					$aiUsageFailed = $row['status'] === 'failed';
					$aiUsageCurrent = $row['record']?->is($record) && in_array($row['kind'], ['agent', 'call'], true);
					$aiUsageLeft = $row['offset'] !== null ? min(100, $row['offset'] / $aiUsageDuration * 100) : null;
					$aiUsageWidth = $row['offset'] !== null && $row['duration'] !== null ? min(100 - $aiUsageLeft, $row['duration'] / $aiUsageDuration * 100) : null;
				@endphp
				<details @class(['group border-t border-slate-100', 'bg-indigo-50/60' => $aiUsageCurrent])>
					<summary class="grid cursor-pointer list-none grid-cols-[minmax(0,5fr)_minmax(0,4fr)_8rem] items-center gap-4 px-5 py-2 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
						<span class="flex min-w-0 items-center gap-2" style="padding-left: {{ $row['depth'] * 1.25 }}rem">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3 shrink-0 text-slate-400 transition group-open:rotate-90" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
							</svg>
							<span class="inline-flex size-6 shrink-0 items-center justify-center rounded-md {{ $aiUsageIconClass }}">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-3.5" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" d="{{ $aiUsageIconPath }}" />
								</svg>
							</span>
							<span class="min-w-0">
								<span @class(['block truncate text-sm', 'text-rose-700' => $aiUsageFailed, 'text-slate-900' => ! $aiUsageFailed])>{{ $row['label'] }}</span>
								<span class="block truncate text-xs text-slate-500">
									{{ $row['sublabel'] }}
									@if ($aiUsageFailed)
										· <span class="text-rose-600">Failed</span>
									@endif
								</span>
							</span>
						</span>

						<span class="relative h-2">
							@if ($aiUsageLeft !== null)
								<span @class(['absolute top-0 h-2 rounded-full', 'bg-rose-500' => $aiUsageFailed, $aiUsageBars[$row['kind']] ?? 'bg-slate-400' => ! $aiUsageFailed]) style="left: {{ $aiUsageLeft }}%; width: max({{ $aiUsageWidth ?? 0 }}%, 0.375rem)"></span>
							@endif
						</span>

						<span class="text-right text-xs tabular-nums">
							<span class="block text-sm text-slate-900">{{ Format::duration($row['duration']) }}</span>
							@if ($row['tokens'] !== null && $row['tokens'] > 0)
								<span class="text-slate-500">{{ Format::tokens($row['tokens']) }}</span>
							@endif
							@if ($row['priced'] ?? false)
								@if ($row['cost'] === null)
									<span class="text-amber-600">Unpriced</span>
								@else
									<span class="text-slate-500">{{ Format::cost($row['cost'], $currency) }}</span>
								@endif
							@endif
						</span>
					</summary>

					<div class="space-y-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4" style="padding-left: {{ 1.25 + $row['depth'] * 1.25 }}rem">
						@forelse ($row['details'] as $label => $value)
							<div>
								<h3 class="mb-1 text-xs font-medium text-slate-500">{{ $label }}</h3>
								<pre @class(['max-h-80 overflow-auto whitespace-pre-wrap rounded-lg border p-3 text-xs', 'border-rose-200 bg-rose-50 text-rose-800' => $label === 'Error', 'border-slate-200 bg-white text-slate-700' => $label !== 'Error'])>{{ $aiUsageJson($value) }}</pre>
							</div>
						@empty
							<p class="text-xs text-slate-500">No details stored. Turn on <code>ai-usage.trace.enabled</code> to keep the full trace.</p>
						@endforelse

						@if ($row['attachments'] !== [])
							<div>
								<h3 class="mb-1 text-xs font-medium text-slate-500">Attachments</h3>
								<ul class="space-y-1 text-xs">
									@foreach ($row['attachments'] as $index => $attachment)
										<li class="flex flex-wrap gap-2">
											@if (isset($attachment['path']) && ! TraceAttachments::pruned($attachment['path']))
												<a href="{{ route('ai-usage.records.attachment', [$row['record'], $index]) }}" class="text-indigo-600 hover:underline">{{ $attachment['name'] ?? basename($attachment['path']) }}</a>
											@elseif (isset($attachment['path']))
												<span class="text-slate-700">{{ $attachment['name'] ?? basename($attachment['path']) }} <span class="text-slate-400">(deleted)</span></span>
											@elseif (isset($attachment['url']))
												<span class="break-all text-slate-700">{{ $attachment['name'] ?? $attachment['url'] }}</span>
											@else
												<span class="text-slate-700">{{ $attachment['name'] ?? $attachment['type'] ?? 'Attachment' }}</span>
											@endif
											<span class="text-slate-400">{{ $attachment['type'] ?? '' }} {{ $attachment['mime'] ?? '' }}{{ isset($attachment['size']) ? ' · ' . number_format($attachment['size'] / 1024, 1) . ' KB' : '' }}</span>
										</li>
									@endforeach
								</ul>
							</div>
						@endif

						@if ($row['record'] !== null && ! $row['record']->is($record) && in_array($row['kind'], ['agent', 'call', 'failover'], true))
							<a href="{{ route('ai-usage.records.show', $row['record']) }}" wire:navigate class="inline-block rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-indigo-300 hover:text-indigo-600">Open this record</a>
						@endif
					</div>
				</details>
			@endforeach
		</div>
	</div>
</section>
