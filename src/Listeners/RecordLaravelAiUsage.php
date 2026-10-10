<?php

namespace SpaanProductions\LaravelAiUsage\Listeners;

use Throwable;
use Laravel\Ai\Events\Reranked;
use Laravel\Ai\Events\Reranking;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\AudioGenerated;
use Laravel\Ai\Events\ImageGenerated;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\GeneratingAudio;
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
use SpaanProductions\LaravelAiUsage\Support\UsageTokens;

/**
 * Records the usage of every laravel/ai call from the events the SDK dispatches, for both
 * laravel/ai 0.x and 1.x (see UsageTokens for the differences in the token counts). A
 * failure to record is reported and never breaks the AI call.
 */
class RecordLaravelAiUsage
{
	public function __construct(private AiUsage $usage)
	{
	}

	public function subscribe(Dispatcher $events): void
	{
		$events->listen([PromptingAgent::class, StreamingAgent::class, GeneratingEmbeddings::class, GeneratingImage::class, GeneratingAudio::class, GeneratingTranscription::class, Reranking::class], [$this, 'started']);
		$events->listen([AgentPrompted::class, AgentStreamed::class], [$this, 'agentPrompted']);
		$events->listen(EmbeddingsGenerated::class, [$this, 'embeddingsGenerated']);
		$events->listen(ImageGenerated::class, [$this, 'imageGenerated']);
		$events->listen(AudioGenerated::class, [$this, 'audioGenerated']);
		$events->listen(TranscriptionGenerated::class, [$this, 'transcriptionGenerated']);
		$events->listen(Reranked::class, [$this, 'reranked']);
		$events->listen([ProviderFailedOver::class, AgentFailedOver::class], [$this, 'failedOver']);

		// laravel/ai 0.11+: an agent call that threw.
		if (class_exists(AgentFailed::class)) {
			$events->listen(AgentFailed::class, [$this, 'agentFailed']);
		}
	}

	public function started(PromptingAgent|GeneratingEmbeddings|GeneratingImage|GeneratingAudio|GeneratingTranscription|Reranking $event): void
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
				...UsageTokens::from($response->usage),
				'conversation_id' => $response->conversationId,
			], $event->prompt->agent, $event->prompt->prompt, $response->text);
		});
	}

	public function agentFailed(AgentFailed $event): void
	{
		$this->safely(fn () => $this->usage->record([
			'invocation_id' => $event->invocationId,
			'latency_ms' => $this->usage->stop($event->invocationId),
			'operation' => Operation::Chat,
			'provider' => $event->prompt->provider->name(),
			'model' => $event->prompt->model,
			'status' => UsageStatus::Failed,
			'error' => $event->exception->getMessage(),
		], $event->prompt->agent, $event->prompt->prompt));
	}

	public function embeddingsGenerated(EmbeddingsGenerated $event): void
	{
		$this->safely(function () use ($event): void {
			$call = $this->call($event->invocationId, Operation::Embeddings, $event->provider->name(), $event->model, $event->response->meta);

			$this->usage->record([
				...$call,
				...UsageTokens::fromEmbeddings($event->response),
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
				...UsageTokens::from($event->response->usage),
				'metadata' => [
					...$call['metadata'],
					...array_filter(['images' => $event->response->count(), 'size' => $event->prompt->size, 'quality' => $event->prompt->quality]),
					...UsageTokens::details($event->response->usage),
				],
			], prompt: $event->prompt->prompt);
		});
	}

	/** Audio responses carry usage since laravel/ai 1.x; before that only the call is recorded. */
	public function audioGenerated(AudioGenerated $event): void
	{
		$this->safely(function () use ($event): void {
			$usage = $event->response->usage ?? null;

			$this->usage->record([
				...$this->call($event->invocationId, Operation::Audio, $event->provider->name(), $event->model, $event->response->meta),
				...UsageTokens::from($usage),
			], prompt: $event->prompt->text);
		});
	}

	public function transcriptionGenerated(TranscriptionGenerated $event): void
	{
		$this->safely(function () use ($event): void {
			$call = $this->call($event->invocationId, Operation::Transcription, $event->provider->name(), $event->model, $event->response->meta);

			$this->usage->record([
				...$call,
				...UsageTokens::from($event->response->usage),
				'metadata' => [...$call['metadata'], ...UsageTokens::details($event->response->usage)],
			], response: $event->response->text);
		});
	}

	/** Reranking responses carry usage (tokens, search units) since laravel/ai 1.x. */
	public function reranked(Reranked $event): void
	{
		$this->safely(function () use ($event): void {
			$call = $this->call($event->invocationId, Operation::Rerank, $event->provider->name(), $event->model, $event->response->meta);
			$usage = $event->response->usage ?? null;

			$this->usage->record([
				...$call,
				...UsageTokens::from($usage),
				'metadata' => [...$call['metadata'], 'documents' => $event->prompt->count(), ...UsageTokens::details($usage)],
			], prompt: $event->prompt->query);
		});
	}

	/** laravel/ai 0.11+ adds the invocation id to AgentFailedOver. */
	public function failedOver(ProviderFailedOver $event): void
	{
		$invocationId = property_exists($event, 'invocationId') ? $event->invocationId : null;

		$this->safely(fn () => $this->usage->record([
			'invocation_id' => $invocationId,
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
