<div class="flex flex-wrap items-center gap-2">
	<select wire:model.live="range" class="rounded-lg border border-slate-300 bg-white py-1.5 pl-3 pr-9 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
		@foreach (\SpaanProductions\LaravelAiUsage\Support\UsageFilters::RANGES as $value => $label)
			<option value="{{ $value }}">{{ $label }}</option>
		@endforeach
	</select>

	@if ($range === 'custom')
		<input type="date" wire:model.live="from" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
		<span class="text-sm text-slate-400">to</span>
		<input type="date" wire:model.live="to" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
	@endif
</div>
