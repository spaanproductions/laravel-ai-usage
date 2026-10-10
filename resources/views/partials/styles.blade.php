{{-- Prefer the published static stylesheet (served straight by the web server); fall back to the route.
     The version ends the long browser cache whenever the stylesheet changes. --}}
@php($aiUsageVersion = \SpaanProductions\LaravelAiUsage\Http\Controllers\StylesheetController::version())

@if (is_file(public_path('vendor/ai-usage/ai-usage.css')))
	<link rel="stylesheet" href="{{ asset('vendor/ai-usage/ai-usage.css') }}{{ $aiUsageVersion ? '?v=' . $aiUsageVersion : '' }}">
@else
	<link rel="stylesheet" href="{{ route('ai-usage.assets.css', array_filter(['v' => $aiUsageVersion])) }}">
@endif

<style>
	[x-cloak] { display: none !important; }
	body { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
</style>
