<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use SpaanProductions\LaravelAiUsage\Models\AiModel;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;

class AiUsageCommandsTest extends TestCase
{
	public function test_reprice_recalculates_rows_that_had_no_pricing(): void
	{
		$record = AiUsageRecord::factory()->pricingMissing()->create(['model' => 'gpt-6-luna', 'input_tokens' => 1_000_000, 'output_tokens' => 100_000]);
		AiModel::factory()->withPricing(input: 2, output: 10)->create(['model_id' => 'gpt-6-luna']);

		$this->artisan('ai-usage:reprice', ['--missing-only' => true, '--dry-run' => true])
			->expectsOutputToContain('Would reprice 1 row(s)')
			->assertSuccessful();
		$this->assertTrue($record->refresh()->pricing_missing);

		$this->artisan('ai-usage:reprice', ['--missing-only' => true])->assertSuccessful();

		$record->refresh();
		$this->assertFalse($record->pricing_missing);
		$this->assertSame(3.0, $record->total_cost);
		$this->assertEquals(2, $record->prices['input']);
	}

	public function test_prune_deletes_rows_older_than_the_retention(): void
	{
		$old = AiUsageRecord::factory()->create(['created_at' => now()->subDays(40)]);
		$recent = AiUsageRecord::factory()->create(['created_at' => now()->subDays(5)]);

		$this->artisan('model:prune', ['--model' => [AiUsageRecord::class]])->assertSuccessful();
		$this->assertSame(2, AiUsageRecord::query()->count());

		config(['ai-usage.retention_days' => 30]);
		$this->artisan('model:prune', ['--model' => [AiUsageRecord::class]])->assertSuccessful();

		$this->assertModelMissing($old);
		$this->assertModelExists($recent);
	}
}
