@php($aiUsageBackLink = config('ai-usage.back_link'))

<header class="border-b border-slate-200 bg-white">
	<div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
		<div class="flex items-center gap-3">
			<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-semibold text-white">AI</span>
			<span class="text-base font-semibold text-slate-900">AI usage</span>
		</div>

		@if (($aiUsageBackLink['route'] ?? null) || ($aiUsageBackLink['url'] ?? null))
			<a href="{{ ($aiUsageBackLink['route'] ?? null) ? route($aiUsageBackLink['route']) : $aiUsageBackLink['url'] }}"
				class="text-sm text-slate-500 transition hover:text-indigo-600">
				&larr; {{ $aiUsageBackLink['label'] ?? 'Back' }}
			</a>
		@endif
	</div>
</header>
