<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Livewire\Livewire;
use Illuminate\Support\Facades\Http;
use SpaanProductions\LaravelAiUsage\Models\AiModel;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Livewire\Models;
use SpaanProductions\LaravelAiUsage\Livewire\Records;
use SpaanProductions\LaravelAiUsage\Livewire\Dashboard;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Tests\Fixtures\Document;
use SpaanProductions\LaravelAiUsage\Support\AiModelSynchronizer;

class AiUsagePagesTest extends TestCase
{
	public function test_the_pages_need_the_gate(): void
	{
		$record = AiUsageRecord::factory()->create();

		$urls = [route('ai-usage.dashboard'), route('ai-usage.records.index'), route('ai-usage.models.index'), $this->url($record)];

		foreach ($urls as $url) {
			$this->get($url)->assertRedirect();
		}

		$this->actingAs($this->createUser());

		foreach ($urls as $url) {
			$this->get($url)->assertForbidden();
		}

		$this->actingAs($this->createAdmin());

		foreach ($urls as $url) {
			$this->get($url)->assertOk();
		}
	}

	public function test_the_dashboard_shows_the_totals_and_breakdowns(): void
	{
		AiUsageRecord::factory()->create(['model' => 'gpt-6-luna', 'feature' => 'link_analysis', 'total_cost' => 1.25, 'total_tokens' => 1000]);
		AiUsageRecord::factory()->create(['model' => 'claude-haiku-5-5', 'provider' => 'anthropic', 'feature' => 'document_extraction', 'total_cost' => 0.75, 'total_tokens' => 500]);
		AiUsageRecord::factory()->failed()->create();
		AiUsageRecord::factory()->create(['total_cost' => 100, 'created_at' => now()->subYear()]);

		Livewire::actingAs($this->createAdmin())
			->test(Dashboard::class)
			->assertSee(['$2.00', 'gpt-6-luna', 'claude-haiku-5-5', 'Link Analysis', 'Document Extraction'])
			->assertDontSee('$102.00')
			->set('range', 'custom')
			->set('from', now()->subYears(2)->toDateString())
			->assertSee('$102.00');
	}

	public function test_the_cost_chart_follows_the_period_of_the_page(): void
	{
		AiUsageRecord::factory()->create(['total_cost' => 1.5, 'created_at' => now()->subDays(2)]);
		AiUsageRecord::factory()->create(['total_cost' => 4, 'created_at' => now()->subDays(45)]);

		$component = Livewire::actingAs($this->createAdmin())->test(Dashboard::class);

		$this->assertSame('day', $component->viewData('chart')['granularity']);
		$this->assertCount(30, $component->viewData('chart')['bars']);
		$this->assertSame(1.5, $component->viewData('chart')['bars']->sum('cost'));

		$component->set('range', '90d');
		$this->assertCount(90, $component->viewData('chart')['bars']);
		$this->assertSame(5.5, $component->viewData('chart')['bars']->sum('cost'));

		$component->set('range', 'this_year');
		$this->assertSame('month', $component->viewData('chart')['granularity']);
	}

	public function test_the_chart_shows_hours_for_short_periods(): void
	{
		$this->travelTo(now()->setTime(15, 30));

		AiUsageRecord::factory()->create(['total_cost' => 1, 'created_at' => now()->setTime(9, 10)]);
		AiUsageRecord::factory()->create(['total_cost' => 2, 'created_at' => now()->setTime(9, 50)]);
		AiUsageRecord::factory()->create(['total_cost' => 4, 'created_at' => now()->setTime(14, 5)]);
		AiUsageRecord::factory()->create(['total_cost' => 8, 'created_at' => now()->subDay()->setTime(23, 59)]);

		$component = Livewire::actingAs($this->createAdmin())
			->test(Dashboard::class)
			->set('range', 'today')
			->assertSee('Cost per hour');

		$bars = $component->viewData('chart')['bars'];

		$this->assertSame('hour', $component->viewData('chart')['granularity']);
		$this->assertCount(16, $bars);
		$this->assertSame(3.0, $bars[9]['cost']);
		$this->assertSame(2, $bars[9]['requests']);
		$this->assertSame(4.0, $bars[14]['cost']);
		$this->assertSame(7.0, $bars->sum('cost'));

		$component->set('range', 'yesterday');
		$this->assertCount(24, $component->viewData('chart')['bars']);
		$this->assertSame(8.0, $component->viewData('chart')['bars'][23]['cost']);
	}

	public function test_the_chart_can_show_tokens_or_requests(): void
	{
		AiUsageRecord::factory()->create(['total_cost' => 1, 'total_tokens' => 300]);
		AiUsageRecord::factory()->create(['total_cost' => 1, 'total_tokens' => 200]);

		$component = Livewire::actingAs($this->createAdmin())->test(Dashboard::class);
		$this->assertSame(2.0, $component->viewData('chart')['max']);

		$component->set('metric', 'tokens')->assertSee('Tokens per day');
		$this->assertSame(500.0, $component->viewData('chart')['max']);

		$component->set('metric', 'requests')->assertSee('Requests per day');
		$this->assertSame(2.0, $component->viewData('chart')['max']);

		$component->set('metric', 'nonsense')->assertSee('Cost per day');
		$this->assertSame('cost', $component->viewData('chart')['metric']);
	}

	public function test_the_records_can_be_filtered_and_sorted(): void
	{
		$luna = AiUsageRecord::factory()->create(['model' => 'gpt-6-luna', 'feature' => 'link_analysis', 'total_cost' => 0.5]);
		$haiku = AiUsageRecord::factory()->create(['model' => 'claude-haiku-5-5', 'provider' => 'anthropic', 'feature' => 'document_extraction', 'total_cost' => 2]);
		$failed = AiUsageRecord::factory()->failed()->create(['model' => 'gpt-6-sol']);
		$limited = AiUsageRecord::factory()->failed()->create(['model' => 'gpt-6-sol', 'error_kind' => 'rate_limited']);

		$component = Livewire::actingAs($this->createAdmin())
			->test(Records::class)
			->assertSee([$this->url($luna), $this->url($haiku), $this->url($failed)])
			->set('provider', 'anthropic')
			->assertSee($this->url($haiku))
			->assertDontSee($this->url($luna))
			->call('resetFilters')
			->set('status', 'failed')
			->assertSee($this->url($failed))
			->assertDontSee($this->url($haiku))
			->set('errorKind', 'rate_limited')
			->assertSee([$this->url($limited), 'Rate limited'])
			->assertDontSee($this->url($failed))
			->call('resetFilters')
			->call('sortBy', 'total_cost');

		$html = $component->html();
		$this->assertLessThan(strpos($html, $this->url($luna) . '"'), strpos($html, $this->url($haiku) . '"'));
		$this->assertLessThan(strpos($html, $this->url($failed) . '"'), strpos($html, $this->url($luna) . '"'));
	}

	public function test_the_filtered_records_can_be_exported(): void
	{
		AiUsageRecord::factory()->create(['model' => 'gpt-6-luna', 'feature' => 'link_analysis']);
		AiUsageRecord::factory()->create(['model' => 'claude-haiku-5-5', 'feature' => 'document_extraction']);

		$response = Livewire::actingAs($this->createAdmin())
			->test(Records::class)
			->set('feature', 'link_analysis')
			->call('export');

		$response->assertFileDownloaded();
		$this->assertStringContainsString('ai-usage-', (string) $response->effects['download']['name']);

		$csv = base64_decode((string) $response->effects['download']['content']);
		$this->assertStringContainsString('gpt-6-luna', $csv);
		$this->assertStringNotContainsString('claude-haiku-5-5', $csv);
	}

	public function test_the_record_detail_shows_the_cost_breakdown(): void
	{
		$record = AiUsageRecord::factory()->create(['model' => 'gpt-6-luna', 'input_tokens' => 1234, 'conversation_id' => 'conversation-1', 'metadata' => ['requested_model' => 'gpt-6']]);
		$other = AiUsageRecord::factory()->create(['conversation_id' => 'conversation-1']);

		$this->actingAs($this->createAdmin())
			->get(route('ai-usage.records.show', $record))
			->assertOk()
			->assertSee(['gpt-6-luna', '1,234', 'requested_model', $this->url($other)]);
	}

	public function test_the_record_detail_links_to_the_subject(): void
	{
		$document = Document::create(['title' => 'Invoice']);
		$record = AiUsageRecord::factory()->for($document, 'subject')->create();

		$this->actingAs($this->createAdmin())
			->get(route('ai-usage.records.show', $record))
			->assertOk()
			->assertSee('https://example.com/documents/' . $document->id);
	}

	public function test_the_models_can_be_searched_and_filtered(): void
	{
		AiModel::factory()->withPricing(input: 0.15, output: 0.6)->create(['model_id' => 'gpt-6-luna', 'description' => 'A small and fast model.']);
		AiModel::factory()->create(['model_id' => 'gpt-6-sol']);
		AiModel::factory()->withPricing(input: 1, output: 5)->create(['provider' => 'anthropic', 'model_id' => 'claude-haiku-5-5', 'name' => 'Claude Haiku 5.5']);

		Livewire::actingAs($this->createAdmin())
			->test(Models::class)
			->assertSee(['gpt-6-luna', 'gpt-6-sol', 'Claude Haiku 5.5', 'A small and fast model.', '$0.15', '$0.60', '/M input', '/M output'])
			->set('search', 'haiku')
			->assertSee('Claude Haiku 5.5')
			->assertDontSee('gpt-6-luna')
			->call('resetFilters')
			->set('provider', 'openai')
			->assertSee('gpt-6-luna')
			->assertDontSee('Claude Haiku 5.5')
			->set('pricing', 'missing')
			->assertSee('gpt-6-sol')
			->assertDontSee('gpt-6-luna');
	}

	public function test_the_models_are_sorted_newest_first(): void
	{
		AiModel::factory()->withPricing(input: 5, output: 20)->create(['model_id' => 'gpt-6-old', 'provider_created_at' => now()->subYear()]);
		AiModel::factory()->withPricing(input: 1, output: 4)->create(['model_id' => 'gpt-6-new', 'provider_created_at' => now()->subDay()]);
		AiModel::factory()->create(['model_id' => 'gpt-6-undated', 'provider_created_at' => null]);

		$component = Livewire::actingAs($this->createAdmin())->test(Models::class);
		$this->assertSame(['gpt-6-new', 'gpt-6-old', 'gpt-6-undated'], $component->viewData('models')->pluck('model_id')->all());
		$this->assertSame('provider_created_at:desc', $component->get('order'));

		$component->set('order', 'provider_created_at:asc');
		$this->assertSame(['gpt-6-old', 'gpt-6-new', 'gpt-6-undated'], $component->viewData('models')->pluck('model_id')->all());

		$component->set('order', 'input_price_per_million:desc');
		$this->assertSame(['input_price_per_million', 'desc'], [$component->get('sort'), $component->get('direction')]);
		$this->assertSame('gpt-6-old', $component->viewData('models')->first()->model_id);

		$component->call('sortBy', 'output_price_per_million');
		$this->assertSame('output_price_per_million:asc', $component->get('order'));
		$this->assertSame('gpt-6-new', $component->viewData('models')->first()->model_id);
	}

	public function test_the_models_can_be_synced_from_the_page(): void
	{
		Http::fake([
			'api.openai.com/*' => Http::response(['data' => [['id' => 'gpt-6-luna', 'owned_by' => 'openai', 'created' => 1_780_000_000]]]),
			AiModelSynchronizer::OPENROUTER_MODELS_URL => Http::response(['data' => [[
				'id' => 'openai/gpt-6-luna',
				'name' => 'OpenAI: GPT-6 Luna',
				'context_length' => 400_000,
				'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['text']],
				'pricing' => ['prompt' => '0.00000015', 'completion' => '0.0000006'],
			]]]),
		]);

		Livewire::actingAs($this->createAdmin())
			->test(Models::class)
			->call('sync')
			->assertSee(['OpenAI:', '1 model(s) synced.', 'model(s) enriched with prices and capabilities.', 'OpenAI: GPT-6 Luna', '400K']);

		$model = AiModel::query()->where('provider', 'openai')->where('model_id', 'gpt-6-luna')->firstOrFail();
		$this->assertSame(0.15, $model->input_price_per_million);
		$this->assertSame(0.6, $model->output_price_per_million);
		$this->assertTrue($model->acceptsInput('image'));
	}

	private function url(AiUsageRecord $record): string
	{
		return route('ai-usage.records.show', $record);
	}
}
