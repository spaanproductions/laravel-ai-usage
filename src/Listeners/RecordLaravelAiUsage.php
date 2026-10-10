<?php

namespace SpaanProductions\LaravelAiUsage\Listeners;

use Throwable;
use Laravel\Ai\Events\Reranked;
use Laravel\Ai\Events\Reranking;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Events\ImageGenerated;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\GeneratingImage;
use Laravel\Ai\Events\ProviderFailedOver;
use Laravel\Ai\Events\EmbeddingsGenerated;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Events\GeneratingEmbeddings;
use SpaanProductions\LaravelAiUsage\AiUsage;
use Laravel\Ai\Events\TranscriptionGenerated;
use Laravel\Ai\Events\GeneratingTranscription;
use SpaanProductions\LaravelAiUsage\Enums\Operation;
use SpaanProductions\LaravelAiUsage\Enums\UsageStatus;

/**
 * Records the usage of every laravel/ai call from the events the SDK dispatches. A failure
 * to record is reported and never breaks the AI call.
 */
class RecordLaravelAiUsage
{
	public function __construct(private AiUsage $usage)
	{
	}

	public function subscribe(Dispatcher $events): void
	{
		$events->listen([PromptingAgent::class, StreamingAgent::class, GeneratingEmbeddings::class, GeneratingImage::class, GeneratingTranscription::class, Reranking::class], [$this, 'started']);
		$events->listen([AgentPrompted::class, AgentStreamed::class], [$this, 'agentPrompted']);
		$events->listen(EmbeddingsGenerated::class, [$this, 'embeddingsGenerated']);
		$events->listen(ImageGenerated::class, [$this, 'imageGenerated']);
		$events->listen(TranscriptionGenerated::class, [$this, 'transcriptionGenerated']);
		$events->listen(Reranked::class, [$this, 'reranked']);
		$events->listen([ProviderFailedOver::class, AgentFailedOver::class], [$this, 'failedOver']);
	}

	public function started(PromptingAgent|GeneratingEmbeddings|GeneratingImage|GeneratingTranscription|Reranking $event): void
	{
		if ($this->usage->enabled()) {
			$this->usage->start($event->invocationId);
		}
	}

	public function agentPrompted(AgentPrompted $event): void
	{
		$this->safely(function () use ($event): void {
			$response = $event->response;

			$this->usage->record([
				...$this->call($event->invocationId, $event instanceof AgentStreamed ? Operation::Stream : Operation::Chat, $event->prompt->provider->name(), $event->prompt->model, $response->meta),
				...self::tokens($response->usage),
				'conversation_id' => $response->conversationId,
			], $event->prompt->agent, $event->prompt->prompt, $response->text);
		});
	}

	public function embeddingsGenerated(EmbeddingsGenerated $event): void
	{
		$this->safely(function () use ($event): void {
			$call = $this->call($event->invocationId, Operation::Embeddings, $event->provider->name(), $event->model, $event->response->meta);

			$this->usage->record([
				...$call,
				'input_tokens' => $event->response->tokens,
				'metadata' => [...$call['metadata'], 'inputs' => count($event->prompt->inputs)],
			]);
		});
	}

	public function imageGenerated(ImageGenerated $event): void
	{
		$this->safely(function () use ($event): void {
			$call = $this->call($event->invocationId, Operation::Image, $event->provider->name(), $event->model, $event->response->meta);

			$this->usage->record([
				...$call,
				...self::tokens($event->response->usage),
				'metadata' => [...$call['metadata'], ...array_filter(['images' => $event->response->count(), 'size' => $event->prompt->size, 'quality' => $event->prompt->quality])],
			], prompt: $event->prompt->prompt);
		});
	}

	public function transcriptionGenerated(TranscriptionGenerated $event): void
	{
		$this->safely(fn () => $this->usage->record([
			...$this->call($event->invocationId, Operation::Transcription, $event->provider->name(), $event->model, $event->response->meta),
			...self::tokens($event->response->usage),
		], response: $event->response->text));
	}

	public function reranked(Reranked $event): void
	{
		$this->safely(function () use ($event): void {
			$call = $this->call($event->invocationId, Operation::Rerank, $event->provider->name(), $event->model, $event->response->meta);

			$this->usage->record([
				...$call,
				'metadata' => [...$call['metadata'], 'documents' => $event->prompt->count()],
			], prompt: $event->prompt->query);
		});
	}

	public function failedOver(ProviderFailedOver $event): void
	{
		$this->safely(fn () => $this->usage->record([
			'operation' => Operation::Failover,
			'provider' => $event->provider->name(),
			'model' => $event->model,
			'status' => UsageStatus::Failed,
			'error' => $event->exception->getMessage(),
		], $event instanceof AgentFailedOver ? $event->agent : null));
	}

	/** @return array<string, mixed> */
	private function call(string $invocationId, Operation $operation, string $provider, string $model, Meta $meta): array
	{
		$respondedModel = $meta->model ?: $model;

		return [
			'invocation_id' => $invocationId,
			'operation' => $operation,
			'provider' => $meta->provider ?: $provider,
			'model' => $respondedModel,
			'status' => UsageStatus::Success,
			'latency_ms' => $this->usage->stop($invocationId),
			'metadata' => $respondedModel !== $model ? ['requested_model' => $model] : [],
		];
	}

	/** @return array<string, int> */
	private static function tokens(Usage $usage): array
	{
		return [
			'input_tokens' => $usage->promptTokens,
			'cache_read_tokens' => $usage->cacheReadInputTokens,
			'cache_write_tokens' => $usage->cacheWriteInputTokens,
			'output_tokens' => $usage->completionTokens,
			'reasoning_tokens' => $usage->reasoningTokens,
		];
	}

	private function safely(callable $record): void
	{
		if ( ! $this->usage->enabled()) {
			return;
		}

		try {
			$record();
		} catch (Throwable $exception) {
			report($exception);
		}
	}
}
