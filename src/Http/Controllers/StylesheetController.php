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
		$path = self::path();

		return response(is_file($path) ? (string) file_get_contents($path) : '', 200, [
			'Content-Type' => 'text/css; charset=UTF-8',
			'Cache-Control' => 'public, max-age=31536000, immutable',
		]);
	}

	public static function path(): string
	{
		$published = public_path('vendor/ai-usage/ai-usage.css');

		return is_file($published) ? $published : __DIR__ . '/../../../dist/ai-usage.css';
	}

	/** A hash of the stylesheet, added to its URL so the long browser cache ends when it changes. */
	public static function version(): ?string
	{
		$path = self::path();

		return is_file($path) ? substr((string) md5_file($path), 0, 12) : null;
	}
}
