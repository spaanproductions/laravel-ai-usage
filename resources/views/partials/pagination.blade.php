@if ($paginator->hasPages())
	<nav class="flex items-center justify-between text-sm text-slate-500">
		<span>
			{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}
		</span>

		<div class="flex gap-2">
			<button type="button" wire:click="previousPage" @disabled($paginator->onFirstPage())
				class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 transition hover:border-indigo-400 disabled:cursor-default disabled:opacity-40">
				&larr; Previous
			</button>
			<span class="px-2 py-1.5">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
			<button type="button" wire:click="nextPage" @disabled(! $paginator->hasMorePages())
				class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 transition hover:border-indigo-400 disabled:cursor-default disabled:opacity-40">
				Next &rarr;
			</button>
		</div>
	</nav>
@endif
