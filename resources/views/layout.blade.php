<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="csrf-token" content="{{ csrf_token() }}">

		<title>{{ $title ?? 'AI usage' }}</title>

		@include('ai-usage::partials.head')
		@include('ai-usage::partials.styles')

		@livewireStyles
	</head>
	<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
		@include('ai-usage::partials.header')

		<main class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
			{{ $slot }}
		</main>

		@livewireScripts
	</body>
</html>
