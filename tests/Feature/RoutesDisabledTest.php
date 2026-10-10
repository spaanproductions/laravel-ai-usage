<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Illuminate\Support\Facades\Route;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;

class RoutesDisabledTest extends TestCase
{
	protected function defineEnvironment($app): void
	{
		parent::defineEnvironment($app);

		$app['config']->set('ai-usage.routes.enabled', false);
	}

	public function test_no_routes_are_registered_when_disabled(): void
	{
		$this->assertFalse(Route::has('ai-usage.dashboard'));
		$this->assertFalse(Route::has('ai-usage.records.index'));
	}
}
