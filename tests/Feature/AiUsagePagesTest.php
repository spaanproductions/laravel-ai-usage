<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Livewire\Livewire;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Livewire\Records;
use SpaanProductions\LaravelAiUsage\Livewire\Dashboard;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Tests\Fixtures\Document;

class AiUsagePagesTest extends TestCase
{
	public function test_the_pages_need_the_gate(): void
	{
		$record = AiUsageRecord::factory()->create();

		$urls = [route('ai-usage.dashboard'), route('ai-usage.records.index'), $this->url($record)];

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

		$this->assertFalse($component->viewData('chart')['monthly']);
		$this->assertCount(30, $component->viewData('chart')['bars']);
		$this->assertSame(1.5, $component->viewData('chart')['bars']->sum('cost'));

		$component->set('range', '90d');
		$this->assertCount(90, $component->viewData('chart')['bars']);
		$this->assertSame(5.5, $component->viewData('chart')['bars']->sum('cost'));

		$component->set('range', 'this_year');
		$this->assertTrue($component->viewData('chart')['monthly']);
	}

	public function test_the_records_can_be_filtered_and_sorted(): void
	{
		$luna = AiUsageRecord::factory()->create(['model' => 'gpt-6-luna', 'feature' => 'link_analysis', 'total_cost' => 0.5]);
		$haiku = AiUsageRecord::factory()->create(['model' => 'claude-haiku-5-5', 'provider' => 'anthropic', 'feature' => 'document_extraction', 'total_cost' => 2]);
		$failed = AiUsageRecord::factory()->failed()->create(['model' => 'gpt-6-sol']);

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

	private function url(AiUsageRecord $record): string
	{
		return route('ai-usage.records.show', $record);
	}
}
