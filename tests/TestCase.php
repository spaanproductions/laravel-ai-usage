<?php

namespace SpaanProductions\LaravelAiUsage\Tests;

use Laravel\Ai\AiServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\Data\Usage;
use Illuminate\Support\Facades\Route;
use Livewire\LivewireServiceProvider;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Responses\Data\TextUsage;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use SpaanProductions\LaravelAiUsage\Tests\Fixtures\User;
use SpaanProductions\LaravelAiUsage\LaravelAiUsageServiceProvider;

class TestCase extends Orchestra
{
	protected function setUp(): void
	{
		parent::setUp();

		Http::preventStrayRequests();
	}

	protected function getPackageProviders($app): array
	{
		return [
			LivewireServiceProvider::class,
			AiServiceProvider::class,
			LaravelAiUsageServiceProvider::class,
		];
	}

	protected function defineDatabaseMigrations(): void
	{
		// The provider does not auto-load its migrations (applications publish them).
		$this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

		Schema::create('users', function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->boolean('is_admin')->default(false);
			$table->timestamps();
		});

		Schema::create('documents', function (Blueprint $table) {
			$table->id();
			$table->string('title');
			$table->timestamps();
		});
	}

	protected function defineEnvironment($app): void
	{
		$app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');
		$app['config']->set('ai.providers.openai.key', 'test-key');
		$app['config']->set('ai-usage.user_model', User::class);
		$app['config']->set('auth.providers.users.model', User::class);

		$app['config']->set('database.default', 'testing');
		$app['config']->set('database.connections.testing', [
			'driver' => 'sqlite',
			'database' => ':memory:',
			'prefix' => '',
		]);
	}

	protected function defineRoutes($router): void
	{
		// The auth middleware redirects guests to the login route.
		Route::get('/login', fn (): string => 'login')->name('login');
	}

	protected function defineGates(): void
	{
		Gate::define('viewAiUsage', fn (User $user): bool => $user->is_admin);
	}

	protected function getEnvironmentSetUp($app): void
	{
		parent::getEnvironmentSetUp($app);

		$app->booted(fn () => $this->defineGates());
	}

	/**
	 * A text usage object for the installed laravel/ai version. $input excludes the cached
	 * tokens, as in 0.x; 1.x reports the total input with the cached tokens as subsets.
	 */
	protected function usage(int $input = 0, int $output = 0, int $cacheRead = 0, int $cacheWrite = 0, int $reasoning = 0): Usage
	{
		if (class_exists(TextUsage::class)) {
			return new TextUsage($input + $cacheRead + $cacheWrite, $output, $cacheRead, $cacheWrite, $reasoning);
		}

		return new Usage($input, $output, $cacheWrite, $cacheRead, $reasoning);
	}

	protected function createAdmin(): User
	{
		return User::create(['name' => 'Admin', 'is_admin' => true]);
	}

	protected function createUser(string $name = 'User'): User
	{
		return User::create(['name' => $name, 'is_admin' => false]);
	}
}
