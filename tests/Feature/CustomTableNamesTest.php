<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use SpaanProductions\LaravelAiUsage\Models\AiModel;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Budgets\BudgetGuard;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Models\AiUsageBudgetAlert;

class CustomTableNamesTest extends TestCase
{
	protected function defineEnvironment($app): void
	{
		parent::defineEnvironment($app);

		$app['config']->set('ai-usage.tables', [
			'models' => 'llm_models',
			'records' => 'llm_usage_records',
			'budget_alerts' => 'llm_budget_alerts',
		]);
		$app['config']->set('ai-usage.budgets', [
			'daily' => ['period' => 'day', 'limit' => 1, 'thresholds' => [1.0]],
		]);
	}

	public function test_the_migrations_create_the_configured_tables(): void
	{
		$this->assertTrue(Schema::hasTable('llm_models'));
		$this->assertTrue(Schema::hasTable('llm_usage_records'));
		$this->assertTrue(Schema::hasTable('llm_budget_alerts'));

		$this->assertFalse(Schema::hasTable('ai_models'));
		$this->assertFalse(Schema::hasTable('ai_usage_records'));
		$this->assertFalse(Schema::hasTable('ai_usage_budget_alerts'));
	}

	public function test_the_models_use_the_configured_tables(): void
	{
		$model = AiModel::factory()->create();
		$record = AiUsageRecord::factory()->create(['ai_model_id' => $model->id, 'total_cost' => 2]);

		resolve(BudgetGuard::class)->evaluate($record);

		$this->assertSame('llm_usage_records', $record->getTable());
		$this->assertTrue($record->fresh()?->aiModel?->is($model));
		$this->assertSame(1, AiUsageBudgetAlert::query()->count());
		$this->assertDatabaseCount('llm_budget_alerts', 1);
	}
}
