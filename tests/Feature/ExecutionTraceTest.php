<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Throwable;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Files\RemoteImage;
use Laravel\Ai\Events\StartingStep;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\Data\ToolCall;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Enums\ErrorKind;
use SpaanProductions\LaravelAiUsage\Facades\AiUsage;
use SpaanProductions\LaravelAiUsage\Enums\UsageStatus;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Support\TraceCollector;
use SpaanProductions\LaravelAiUsage\Tests\Fixtures\PlainAgent;
use SpaanProductions\LaravelAiUsage\Tests\Fixtures\SupportAgent;

class ExecutionTraceTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		if ( ! class_exists(StartingStep::class)) {
			$this->markTestSkipped('Step and tool events exist since laravel/ai 1.x.');
		}
	}

	public function test_the_rows_of_one_run_are_linked_into_a_trace(): void
	{
		$this->run_support_agent();

		[$root, $sub] = $this->rows();

		$this->assertSame($root->invocation_id, $root->trace_id);
		$this->assertNull($root->parent_invocation_id);
		$this->assertSame($root->trace_id, $sub->trace_id);
		$this->assertSame($root->invocation_id, $sub->parent_invocation_id);
		$this->assertNotNull($sub->parent_tool_invocation_id);
		$this->assertNull($root->trace);
		$this->assertNull($sub->trace);
	}

	public function test_the_full_trace_is_stored_when_tracing_is_on(): void
	{
		config(['ai-usage.trace.enabled' => true]);

		$this->run_support_agent();

		[$root, $sub] = $this->rows();
		$spans = $root->trace['spans'];

		$this->assertSame('Answer support questions.', $root->trace['instructions']);
		$this->assertSame('Where is my warranty?', $root->trace['input']);
		$this->assertSame('Two years.', $root->trace['output']);
		$this->assertSame(['step', 'tool', 'step', 'tool', 'step'], array_column($spans, 'type'));
		$this->assertSame(['LookupTool'], $spans[0]['tool_calls']);
		$this->assertSame('LookupTool', $spans[1]['name']);
		$this->assertSame(['query' => 'warranty', 'api_token' => '[redacted]'], $spans[1]['arguments']);
		$this->assertSame('Trekking poles have a two-year warranty.', $spans[1]['result']);
		$this->assertSame('success', $spans[1]['status']);
		$this->assertSame('PlainAgent', $spans[3]['name']);
		$this->assertSame($spans[3]['id'], $sub->parent_tool_invocation_id);
		$this->assertSame('Check the policy', $sub->trace['input']);

		foreach ($spans as $span) {
			$this->assertIsNumeric($span['offset_ms']);
			$this->assertIsNumeric($span['duration_ms']);
		}

		$this->assertSame(0, resolve(TraceCollector::class)->running());
	}

	public function test_the_trace_has_its_own_content_limit(): void
	{
		config(['ai-usage.trace.enabled' => true, 'ai-usage.store_content' => true, 'ai-usage.content_limit' => 10]);
		PlainAgent::fake(['A short answer.', 'A short answer.']);
		$prompt = str_repeat('a', 100);

		(new PlainAgent)->prompt($prompt, provider: 'openai', model: 'gpt-6-luna');
		$record = AiUsageRecord::query()->sole();

		$this->assertSame(str_repeat('a', 10) . '...', $record->prompt);
		$this->assertSame(str_repeat('a', 10) . '...', $record->trace['input']);

		config(['ai-usage.trace.content_limit' => 50]);
		(new PlainAgent)->prompt($prompt, provider: 'openai', model: 'gpt-6-luna');
		$record = AiUsageRecord::query()->latest('id')->firstOrFail();

		$this->assertSame(str_repeat('a', 10) . '...', $record->prompt);
		$this->assertSame(str_repeat('a', 50) . '...', $record->trace['input']);
	}

	public function test_a_failed_tool_is_kept_in_the_trace_of_the_failed_row(): void
	{
		config(['ai-usage.trace.enabled' => true]);
		SupportAgent::fake([new ToolCall('call-1', 'LookupTool', ['query' => 'fail'])]);

		try {
			(new SupportAgent)->prompt('Where is my warranty?', provider: 'openai', model: 'gpt-6-luna');
		} catch (Throwable) {
		}

		$record = AiUsageRecord::query()->sole();
		$tool = collect($record->trace['spans'])->firstWhere('type', 'tool');

		$this->assertSame(UsageStatus::Failed, $record->status);
		$this->assertSame(ErrorKind::Exception, $record->error_kind);
		$this->assertSame('failed', $tool['status']);
		$this->assertSame('tool_error', $tool['error_kind']);
		$this->assertSame('The policy database is down.', $tool['error']);
	}

	public function test_sensitive_values_are_redacted(): void
	{
		config([
			'ai-usage.trace.enabled' => true,
			'ai-usage.redaction.patterns' => ['/two-year/' => '[term]'],
		]);

		$this->run_support_agent();

		$tool = $this->rows()[0]->trace['spans'][1];

		$this->assertSame(['query' => 'warranty', 'api_token' => '[redacted]'], $tool['arguments']);
		$this->assertSame('Trekking poles have a [term] warranty.', $tool['result']);
	}

	public function test_redaction_can_be_turned_off(): void
	{
		config(['ai-usage.trace.enabled' => true, 'ai-usage.redaction.enabled' => false]);

		$this->run_support_agent();

		$this->assertSame(['query' => 'warranty', 'api_token' => 'abc123'], $this->rows()[0]->trace['spans'][1]['arguments']);
	}

	public function test_nothing_is_recorded_without_recording(): void
	{
		config(['ai-usage.trace.enabled' => true]);

		AiUsage::withoutRecording(fn () => $this->run_support_agent());

		$this->assertSame(0, AiUsageRecord::query()->count());
		$this->assertSame(0, resolve(TraceCollector::class)->running());
	}

	public function test_attachments_are_described_and_optionally_stored(): void
	{
		Storage::fake('local');
		config(['ai-usage.trace.enabled' => true, 'ai-usage.trace.attachments' => true, 'ai-usage.retention_days' => 30]);
		PlainAgent::fake(['A cat.']);

		(new PlainAgent)->prompt('What is this?', [
			(new Base64Image(base64_encode('png-bytes'), 'image/png'))->as('cat.png'),
			new RemoteImage('https://example.com/dog.png'),
		], provider: 'openai', model: 'gpt-6-luna');

		$record = AiUsageRecord::query()->sole();
		[$stored, $remote] = $record->trace['attachments'];

		$this->assertSame('cat.png', $stored['name']);
		$this->assertSame(9, $stored['size']);
		$this->assertArrayNotHasKey('base64', $stored);
		Storage::disk('local')->assertExists($stored['path']);
		$this->assertSame('https://example.com/dog.png', $remote['url']);
		$this->assertArrayNotHasKey('path', $remote);

		$this->travel(31)->days();
		$record->pruneAll();

		Storage::disk('local')->assertMissing($stored['path']);
		$this->assertSame(0, AiUsageRecord::query()->count());
	}

	public function test_attachments_can_be_pruned_while_the_row_and_trace_are_kept(): void
	{
		Storage::fake('local');
		config(['ai-usage.trace.enabled' => true, 'ai-usage.trace.attachments' => true, 'ai-usage.attachment_retention_days' => 30]);
		PlainAgent::fake(['A cat.']);

		(new PlainAgent)->prompt('What is this?', [
			(new Base64Image(base64_encode('png-bytes'), 'image/png'))->as('cat.png'),
		], provider: 'openai', model: 'gpt-6-luna');

		$record = AiUsageRecord::query()->sole();
		$path = $record->trace['attachments'][0]['path'];
		$link = route('ai-usage.records.attachment', [$record, 0]);

		$this->actingAs($this->createAdmin())
			->get(route('ai-usage.records.show', $record))
			->assertSee($link, false);

		$this->travel(31)->days();
		$record->pruneAll();

		Storage::disk('local')->assertMissing($path);
		$this->assertNotNull($record->fresh()?->trace);

		$this->get(route('ai-usage.records.show', $record))
			->assertOk()
			->assertSee('cat.png')
			->assertSee('(deleted)')
			->assertDontSee($link, false);
	}

	public function test_a_trace_retention_also_deletes_the_attachments(): void
	{
		Storage::fake('local');
		config(['ai-usage.trace.enabled' => true, 'ai-usage.trace.attachments' => true, 'ai-usage.trace_retention_days' => 30]);
		PlainAgent::fake(['A cat.']);

		(new PlainAgent)->prompt('What is this?', [
			(new Base64Image(base64_encode('png-bytes'), 'image/png'))->as('cat.png'),
		], provider: 'openai', model: 'gpt-6-luna');

		$record = AiUsageRecord::query()->sole();
		$path = $record->trace['attachments'][0]['path'];

		$this->travel(31)->days();
		$record->pruneAll();

		Storage::disk('local')->assertMissing($path);
		$this->assertNull($record->fresh()?->trace);
		$this->assertModelExists($record);
	}

	public function test_the_record_page_shows_the_execution_trace(): void
	{
		config(['ai-usage.trace.enabled' => true]);
		$this->run_support_agent();
		[$root, $sub] = $this->rows();

		$this->actingAs($this->createAdmin())
			->get(route('ai-usage.records.show', $root))
			->assertOk()
			->assertSee('Execution')
			->assertSee('Model step 2')
			->assertSee('LookupTool')
			->assertSee('Delegated agent')
			->assertSee('Trekking poles have a two-year warranty.');

		$this->get(route('ai-usage.records.show', $sub))
			->assertOk()
			->assertSee('Part of the trace of');
	}

	private function run_support_agent(): void
	{
		SupportAgent::fake([
			new ToolCall('call-1', 'LookupTool', ['query' => 'warranty', 'api_token' => 'abc123']),
			new ToolCall('call-2', 'PlainAgent', ['task' => 'Check the policy']),
			'Two years.',
		]);
		PlainAgent::fake(['Two years, confirmed.']);

		(new SupportAgent)->prompt('Where is my warranty?', provider: 'openai', model: 'gpt-6-luna');
	}

	/** @return array{AiUsageRecord, AiUsageRecord} The root row and the sub-agent row. */
	private function rows(): array
	{
		$records = AiUsageRecord::query()->orderBy('id')->get();
		$this->assertCount(2, $records);

		return [
			$records->firstOrFail(fn (AiUsageRecord $record): bool => $record->agent === SupportAgent::class),
			$records->firstOrFail(fn (AiUsageRecord $record): bool => $record->agent === PlainAgent::class),
		];
	}
}
