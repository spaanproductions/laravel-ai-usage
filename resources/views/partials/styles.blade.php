{{-- Prefer the published static stylesheet (served straight by the web server); fall back to the route. --}}
@if (is_file(public_path('vendor/ai-usage/ai-usage.css')))
	<link rel="stylesheet" href="{{ asset('vendor/ai-usage/ai-usage.css') }}">
@else
	<link rel="stylesheet" href="{{ route('ai-usage.assets.css') }}">
@endif

<style>
	[x-cloak] { display: none !important; }
	body { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
</style>
