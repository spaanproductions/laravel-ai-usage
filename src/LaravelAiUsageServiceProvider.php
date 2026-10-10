<?php

namespace SpaanProductions\LaravelAiUsage;

use Livewire\Livewire;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use SpaanProductions\LaravelAiUsage\Livewire\Models;
use SpaanProductions\LaravelAiUsage\Livewire\Records;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use SpaanProductions\LaravelAiUsage\Livewire\Dashboard;
use SpaanProductions\LaravelAiUsage\Livewire\RecordDetail;
use SpaanProductions\LaravelAiUsage\Listeners\RecordLaravelAiUsage;
use SpaanProductions\LaravelAiUsage\Console\Commands\RepriceUsageCommand;
use SpaanProductions\LaravelAiUsage\Console\Commands\SyncAiModelsCommand;
use SpaanProductions\LaravelAiUsage\Http\Controllers\StylesheetController;

class LaravelAiUsageServiceProvider extends PackageServiceProvider
{
	public function configurePackage(Package $package): void
	{
		$package
			->name('laravel-ai-usage')
			->hasConfigFile('ai-usage')
			->hasViews('ai-usage')
			->hasCommands([SyncAiModelsCommand::class, RepriceUsageCommand::class]);
	}

	public function packageRegistered(): void
	{
		$this->app->singleton(AiUsage::class);
	}

	public function packageBooted(): void
	{
		Event::subscribe(RecordLaravelAiUsage::class);

		$this->registerGate();
		$this->registerLivewireComponents();
		$this->registerRoutes();
		$this->registerPublishables();
	}

	/** Deny everyone until the application defines the gate itself. */
	private function registerGate(): void
	{
		$gate = (string) config('ai-usage.gate', 'viewAiUsage');

		$this->app->booted(function () use ($gate): void {
			if ( ! Gate::has($gate)) {
				Gate::define($gate, fn (): bool => false);
			}
		});
	}

	private function registerLivewireComponents(): void
	{
		Livewire::component('ai-usage.dashboard', Dashboard::class);
		Livewire::component('ai-usage.records', Records::class);
		Livewire::component('ai-usage.record', RecordDetail::class);
		Livewire::component('ai-usage.models', Models::class);
	}

	private function registerRoutes(): void
	{
		$routes = (array) config('ai-usage.routes');

		if ( ! ($routes['enabled'] ?? true)) {
			return;
		}

		Route::prefix($routes['prefix'] ?? 'ai-usage')
			->middleware([...($routes['middleware'] ?? ['web']), 'can:' . config('ai-usage.gate', 'viewAiUsage')])
			->group(function (): void {
				Route::livewire('/', Dashboard::class)->name('ai-usage.dashboard');
				Route::livewire('/records', Records::class)->name('ai-usage.records.index');
				Route::livewire('/records/{record}', RecordDetail::class)->name('ai-usage.records.show');
				Route::livewire('/models', Models::class)->name('ai-usage.models.index');
				Route::get('/assets/ai-usage.css', StylesheetController::class)->name('ai-usage.assets.css');
			});
	}

	private function registerPublishables(): void
	{
		if ( ! $this->app->runningInConsole()) {
			return;
		}

		// The migrations are not auto-loaded: publish them so the application owns them
		// (vendor:publish stamps them with the current date).
		$migrations = __DIR__ . '/../database/migrations/';

		$this->publishesMigrations([
			$migrations . '2026_10_11_000001_create_ai_usage_records_table.php' => database_path('migrations/2026_10_11_000001_create_ai_usage_records_table.php'),
			$migrations . '2026_10_11_000002_create_ai_usage_budget_alerts_table.php' => database_path('migrations/2026_10_11_000002_create_ai_usage_budget_alerts_table.php'),
		], 'ai-usage-migrations');

		// Separate, so an application that already has an ai_models table can skip it.
		$this->publishesMigrations([
			$migrations . '2026_10_11_000000_create_ai_models_table.php' => database_path('migrations/2026_10_11_000000_create_ai_models_table.php'),
		], 'ai-usage-models-migrations');

		$this->publishes([
			__DIR__ . '/../dist/ai-usage.css' => public_path('vendor/ai-usage/ai-usage.css'),
		], 'ai-usage-assets');

		$this->publishes([
			__DIR__ . '/../resources/views' => resource_path('views/vendor/ai-usage'),
		], 'ai-usage-views');
	}
}
