<?php

namespace SpaanProductions\LaravelAiUsage\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the pre-compiled stylesheet of the standalone pages, so they need no Tailwind build
 * of the host. A published copy in public/ takes precedence.
 */
class StylesheetController
{
	public function __invoke(): Response
	{
		$published = public_path('vendor/ai-usage/ai-usage.css');
		$path = is_file($published) ? $published : __DIR__ . '/../../../dist/ai-usage.css';

		return response(is_file($path) ? (string) file_get_contents($path) : '', 200, [
			'Content-Type' => 'text/css; charset=UTF-8',
			'Cache-Control' => 'public, max-age=31536000, immutable',
		]);
	}
}
