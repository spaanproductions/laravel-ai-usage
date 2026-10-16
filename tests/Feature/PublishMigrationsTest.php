<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Illuminate\Support\Facades\File;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\LaravelAiUsageServiceProvider;

class PublishMigrationsTest extends TestCase
{
	private string $directory;

	protected function setUp(): void
	{
		parent::setUp();

		$this->directory = database_path('migrations');
		File::ensureDirectoryExists($this->directory);
		$this->clean();
	}

	protected function tearDown(): void
	{
		$this->clean();

		parent::tearDown();
	}

	public function test_republishing_after_an_update_only_adds_the_new_migrations(): void
	{
		File::put($this->directory . '/2026_10_10_223033_create_ai_usage_records_table.php', '<?php // published');
		File::put($this->directory . '/2026_10_10_223034_create_ai_usage_budget_alerts_table.php', '<?php // published');

		// Publish paths are resolved when the provider boots.
		$this->app->register(LaravelAiUsageServiceProvider::class, force: true);

		$this->artisan('vendor:publish', ['--tag' => 'ai-usage-migrations'])->assertSuccessful();

		$this->assertCount(1, $this->published('create_ai_usage_records_table'));
		$this->assertCount(1, $this->published('create_ai_usage_budget_alerts_table'));
		$this->assertCount(1, $this->published('add_trace_columns_to_ai_usage_records_table'));
		$this->assertSame('<?php // published', File::get($this->directory . '/2026_10_10_223033_create_ai_usage_records_table.php'));
	}

	/** @return array<int, string> */
	private function published(string $name): array
	{
		return glob($this->directory . '/*_' . $name . '.php') ?: [];
	}

	private function clean(): void
	{
		foreach (['create_ai_usage_records_table', 'create_ai_usage_budget_alerts_table', 'add_trace_columns_to_ai_usage_records_table', 'create_ai_models_table'] as $name) {
			File::delete($this->published($name));
		}
	}
}
