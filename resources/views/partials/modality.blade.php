@php
	// A coloured badge per modality, like OpenRouter. Heroicons (outline) are inlined: the
	// standalone layout loads no icon library. Text is a plain "T".
	[$aiUsageBadgeClass, $aiUsageIconPath] = match ($modality) {
		'text' => ['bg-sky-100 text-sky-600', 'M5.25 5.25h13.5M12 5.25v13.5'],
		'image' => ['bg-emerald-100 text-emerald-600', 'm2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z'],
		'video' => ['bg-orange-100 text-orange-600', 'm15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z'],
		'file' => ['bg-amber-100 text-amber-600', 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
		'audio' => ['bg-violet-100 text-violet-600', 'M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z'],
		default => ['bg-slate-100 text-slate-500', null],
	};
@endphp

<span title="{{ ucfirst($modality) }}" class="inline-flex size-6 shrink-0 items-center justify-center rounded-md {{ $aiUsageBadgeClass }}">
	@if ($aiUsageIconPath !== null)
		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="{{ $modality === 'text' ? 2.5 : 1.75 }}" stroke="currentColor" class="size-3.5 shrink-0" aria-hidden="true">
			<path stroke-linecap="round" stroke-linejoin="round" d="{{ $aiUsageIconPath }}" />
		</svg>
		<span class="sr-only">{{ ucfirst($modality) }}</span>
	@else
		<span class="text-[10px] font-semibold uppercase">{{ \Illuminate\Support\Str::substr($modality, 0, 2) }}</span>
	@endif
</span>
