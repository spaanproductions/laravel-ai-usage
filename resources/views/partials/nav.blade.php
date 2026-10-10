@php($aiUsageQuery = array_filter(['range' => $range ?? null, 'from' => $from ?? null, 'to' => $to ?? null]))

<nav class="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm font-medium">
	@foreach (['ai-usage.dashboard' => 'Dashboard', 'ai-usage.records.index' => 'Records'] as $aiUsageRoute => $aiUsageLabel)
		<a href="{{ route($aiUsageRoute, $aiUsageQuery) }}" wire:navigate
			@class([
				'rounded-md px-3 py-1.5 transition',
				'bg-white text-slate-900 shadow-sm' => $current === $aiUsageRoute,
				'text-slate-500 hover:text-slate-900' => $current !== $aiUsageRoute,
			])>
			{{ $aiUsageLabel }}
		</a>
	@endforeach
</nav>
