<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Laravel\Ai\Ai;
use ReflectionClass;
use RuntimeException;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Responses\Data\Usage;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\TextUsage;
use SpaanProductions\LaravelAiUsage\Models\AiModel;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Enums\ErrorKind;
use SpaanProductions\LaravelAiUsage\Enums\Operation;
use SpaanProductions\LaravelAiUsage\Facades\AiUsage;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use SpaanProductions\LaravelAiUsage\Enums\UsageStatus;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Events\AiUsageRecorded;
use SpaanProductions\LaravelAiUsage\Tests\Fixtures\Document;
use SpaanProductions\LaravelAiUsage\Tests\Fixtures\PlainAgent;
use SpaanProductions\LaravelAiUsage\Tests\Fixtures\SummaryAgent;

class RecordsLaravelAiUsageTest extends TestCase
{
	public function test_a_prompted_agent_is_recorded_with_its_tokens_cost_and_context(): void
	{
		AiModel::factory()->withPricing(input: 2, output: 10, cachedInput: 0.5)->create(['model_id' => 'gpt-6-luna']);
		$user = $this->createUser();
		$document = Document::create(['title' => 'Invoice']);

		$this->actingAs($user);
		AiUsage::context(fn () => $this->prompted($this->usage(input: 1000, output: 500, cacheRead: 2000, reasoning: 100)), subject: $document);

		$record = AiUsageRecord::query()->sole();
		$this->assertSame(Operation::Chat, $record->operation);
		$this->assertSame(UsageStatus::Success, $record->status);
		$this->assertSame('openai', $record->provider);
		$this->assertSame('gpt-6-luna', $record->model);
		$this->assertSame('summaries', $record->feature);
		$this->assertSame(SummaryAgent::class, $record->agent);
		$this->assertTrue($record->subject->is($document));
		$this->assertSame($user->id, $record->user_id);
		$this->assertSame('invocation-1', $record->invocation_id);
		$this->assertSame(3500, $record->total_tokens);
		$this->assertSame(100, $record->reasoning_tokens);
		$this->assertSame(0.002, $record->input_cost);
		$this->assertSame(0.001, $record->cache_read_cost);
		$this->assertSame(0.005, $record->output_cost);
		$this->assertSame(0.008, $record->total_cost);
		$this->assertFalse($record->pricing_missing);
		$this->assertNull($record->prompt);
	}

	public function test_the_latency_is_measured_from_the_start_event(): void
	{
		$prompt = $this->agentPrompt();

		event(new PromptingAgent('invocation-1', $prompt));
		event(new AgentPrompted('invocation-1', $prompt, $this->response($this->usage(10, 5))));

		$this->assertNotNull(AiUsageRecord::query()->sole()->latency_ms);
	}

	public function test_a_streamed_agent_is_recorded_as_a_stream(): void
	{
		event(new AgentStreamed('invocation-1', $this->agentPrompt(), $this->response($this->usage(10, 5))));

		$this->assertSame(Operation::Stream, AiUsageRecord::query()->sole()->operation);
	}

	public function test_a_model_without_pricing_is_flagged(): void
	{
		$this->prompted($this->usage(1000, 500));

		$record = AiUsageRecord::query()->sole();
		$this->assertTrue($record->pricing_missing);
		$this->assertSame(0.0, $record->total_cost);
	}

	public function test_a_failover_is_recorded_as_failed(): void
	{
		$arguments = [new SummaryAgent, Ai::textProvider('openai'), 'gpt-6-luna', ProviderOverloadedException::forProvider('openai')];

		// laravel/ai 0.11+ passes the invocation id first.
		if ((new ReflectionClass(AgentFailedOver::class))->getConstructor()?->getParameters()[0]->getName() === 'invocationId') {
			array_unshift($arguments, 'invocation-1');
		}

		event(new AgentFailedOver(...$arguments));

		$record = AiUsageRecord::query()->sole();
		$this->assertSame(Operation::Failover, $record->operation);
		$this->assertSame(UsageStatus::Failed, $record->status);
		$this->assertSame('AI provider [openai] is overloaded.', $record->error);
		$this->assertSame(ErrorKind::Overloaded, $record->error_kind);
		$this->assertSame('summaries', $record->feature);
	}

	public function test_an_agent_call_that_threw_is_recorded_as_failed(): void
	{
		if ( ! class_exists(AgentFailed::class)) {
			$this->markTestSkipped('AgentFailed exists since laravel/ai 0.11.');
		}

		event(new AgentFailed('invocation-1', $this->agentPrompt(), new RuntimeException('The request timed out.')));

		$record = AiUsageRecord::query()->sole();
		$this->assertSame(Operation::Chat, $record->operation);
		$this->assertSame(UsageStatus::Failed, $record->status);
		$this->assertSame('The request timed out.', $record->error);
		$this->assertSame(ErrorKind::Connection, $record->error_kind);
		$this->assertSame('summaries', $record->feature);
		$this->assertSame('gpt-6-luna', $record->model);
	}

	public function test_the_prompt_and_response_are_only_stored_when_enabled(): void
	{
		config(['ai-usage.store_content' => true, 'ai-usage.content_limit' => 9]);

		$this->prompted($this->usage(10, 5));

		$record = AiUsageRecord::query()->sole();
		$this->assertSame('Summarize...', $record->prompt);
		$this->assertSame('{"summary...', $record->response);
	}

	public function test_nothing_is_recorded_when_disabled(): void
	{
		config(['ai-usage.enabled' => false]);

		$this->prompted($this->usage(10, 5));

		$this->assertSame(0, AiUsageRecord::query()->count());
	}

	public function test_a_failure_to_record_never_breaks_the_ai_call(): void
	{
		Event::listen(AiUsageRecorded::class, fn () => throw new RuntimeException('Broken listener'));

		$this->prompted($this->usage(10, 5));

		$this->assertSame(1, AiUsageRecord::query()->count());
	}

	public function test_a_faked_agent_call_is_recorded(): void
	{
		SummaryAgent::fake(['A short summary.']);

		(new SummaryAgent)->prompt('Summarize this', provider: 'openai', model: 'gpt-6-sol');

		$record = AiUsageRecord::query()->sole();
		$this->assertSame('summaries', $record->feature);
		$this->assertSame('gpt-6-sol', $record->model);
	}

	public function test_an_agent_without_context_falls_back_to_its_class_name(): void
	{
		PlainAgent::fake(['An answer.']);

		(new PlainAgent)->prompt('A question', provider: 'openai', model: 'gpt-6-sol');

		$this->assertSame('plain_agent', AiUsageRecord::query()->sole()->feature);
	}

	public function test_contexts_nest_with_the_inner_values_winning(): void
	{
		$outer = Document::create(['title' => 'Outer']);
		$inner = Document::create(['title' => 'Inner']);

		AiUsage::context(function () use ($inner): void {
			AiUsage::context(fn () => $this->prompted($this->usage(10, 5)), subject: $inner);
		}, feature: 'bulk_import', subject: $outer, metadata: ['batch' => 7]);

		$record = AiUsageRecord::query()->sole();
		$this->assertSame('bulk_import', $record->feature);
		$this->assertTrue($record->subject->is($inner));
		$this->assertSame(['batch' => 7], $record->metadata);
		$this->assertNull(AiUsage::currentContext()->feature);
	}

	private function prompted(Usage $usage): void
	{
		event(new AgentPrompted('invocation-1', $this->agentPrompt(), $this->response($usage)));
	}

	private function agentPrompt(): AgentPrompt
	{
		return new AgentPrompt(new SummaryAgent, 'Summarize this page', [], Ai::textProvider('openai'), 'gpt-6-luna');
	}

	private function response(Usage|TextUsage $usage): AgentResponse
	{
		return new AgentResponse('invocation-1', '{"summary":"A page"}', $usage, new Meta('openai', 'gpt-6-luna'));
	}
}
