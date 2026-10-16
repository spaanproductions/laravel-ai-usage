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
use SpaanProductions\LaravelAiUsage\Support\TraceCollector;
use SpaanProductions\LaravelAiUsage\Listeners\RecordLaravelAiUsage;
use SpaanProductions\LaravelAiUsage\Console\Commands\RepriceUsageCommand;
use SpaanProductions\LaravelAiUsage\Console\Commands\SyncAiModelsCommand;
use SpaanProductions\LaravelAiUsage\Http\Controllers\AttachmentController;
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
		$this->app->singleton(TraceCollector::class);
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
				Route::get('/records/{record}/attachments/{index}', AttachmentController::class)->whereNumber('index')->name('ai-usage.records.attachment');
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
		$this->publishesMigrations(self::migrations([
			'2026_10_11_000001_create_ai_usage_records_table.php',
			'2026_10_11_000002_create_ai_usage_budget_alerts_table.php',
			'2026_10_15_000003_add_trace_columns_to_ai_usage_records_table.php',
		]), 'ai-usage-migrations');

		// Separate, so an application that already has an ai_models table can skip it.
		$this->publishesMigrations(self::migrations([
			'2026_10_11_000000_create_ai_models_table.php',
		]), 'ai-usage-models-migrations');

		$this->publishes([
			__DIR__ . '/../dist/ai-usage.css' => public_path('vendor/ai-usage/ai-usage.css'),
		], 'ai-usage-assets');

		$this->publishes([
			__DIR__ . '/../resources/views' => resource_path('views/vendor/ai-usage'),
		], 'ai-usage-views');
	}

	/**
	 * The publish paths of the migrations. A migration the application already published (under
	 * its own date) targets that file, so vendor:publish skips it and an update only adds the
	 * new migrations.
	 *
	 * @param array<int, string> $files
	 * @return array<string, string>
	 */
	private static function migrations(array $files): array
	{
		$paths = [];

		foreach ($files as $file) {
			$name = (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $file);
			$published = collect(glob(database_path('migrations/*_' . $name)) ?: [])
				->first(fn (string $path): bool => preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_' . preg_quote($name, '/') . '$/', basename($path)) === 1);

			$paths[__DIR__ . '/../database/migrations/' . $file] = $published ?? database_path('migrations/' . $file);
		}

		return $paths;
	}
}
