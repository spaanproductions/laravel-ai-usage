<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Illuminate\Support\Facades\Http;
use SpaanProductions\LaravelAiUsage\Models\AiModel;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Support\AiModelSynchronizer;

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

	public function test_a_failing_provider_does_not_stop_the_sync(): void
	{
		Http::fake([
			'api.openai.com/*' => Http::response([], 500),
			AiModelSynchronizer::OPENROUTER_MODELS_URL => Http::response(['data' => []]),
		]);

		$this->artisan('ai-usage:sync-models')
			->expectsOutputToContain('failed with HTTP status 500')
			->expectsOutputToContain('OpenRouter: 0 model(s) synced.')
			->assertFailed();
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

	public function test_prune_clears_traces_older_than_the_trace_retention(): void
	{
		$old = AiUsageRecord::factory()->create(['created_at' => now()->subDays(40), 'trace' => ['steps' => []], 'prompt' => 'Hi', 'trace_id' => 'trace-1']);
		$recent = AiUsageRecord::factory()->create(['created_at' => now()->subDays(5), 'trace' => ['steps' => []]]);

		config(['ai-usage.trace_retention_days' => 30]);
		$this->artisan('model:prune', ['--model' => [AiUsageRecord::class]])->assertSuccessful();

		$old->refresh();
		$this->assertNull($old->trace);
		$this->assertSame('Hi', $old->prompt);
		$this->assertSame('trace-1', $old->trace_id);
		$this->assertNotNull($recent->fresh()?->trace);
	}

	public function test_prune_clears_content_older_than_the_content_retention(): void
	{
		$old = AiUsageRecord::factory()->create(['created_at' => now()->subDays(40), 'prompt' => 'Hi', 'response' => 'Hello', 'trace' => ['steps' => []]]);
		$recent = AiUsageRecord::factory()->create(['created_at' => now()->subDays(5), 'prompt' => 'Hi', 'response' => 'Hello']);

		config(['ai-usage.content_retention_days' => 30]);
		$this->artisan('model:prune', ['--model' => [AiUsageRecord::class]])->assertSuccessful();

		$old->refresh();
		$this->assertNull($old->prompt);
		$this->assertNull($old->response);
		$this->assertNotNull($old->trace);
		$this->assertSame('Hello', $recent->fresh()?->response);
	}
}
