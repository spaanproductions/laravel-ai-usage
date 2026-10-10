<?php

namespace SpaanProductions\LaravelAiUsage\Console\Commands;

use Throwable;
use Illuminate\Console\Command;
use SpaanProductions\LaravelAiUsage\Support\AiProviders;
use SpaanProductions\LaravelAiUsage\Support\AiModelSynchronizer;

class SyncAiModelsCommand extends Command
{
	protected $signature = 'ai-usage:sync-models
		{--provider=* : Only sync these providers (default: every provider with an API key, and always OpenRouter)}';

	protected $description = 'Sync the AI models of every configured provider and the public OpenRouter catalogue, with prices from OpenRouter';

	public function handle(): int
	{
		$synchronizer = resolve(AiModelSynchronizer::class);
		$providers = (array) $this->option('provider') ?: AiModelSynchronizer::syncableProviders();
		$failed = false;

		foreach ($providers as $provider) {
			try {
				$count = $synchronizer->syncProvider($provider);
				$this->components->info(sprintf('%s: %d model(s) synced.', AiProviders::label($provider), $count));
			} catch (Throwable $exception) {
				$failed = true;
				$this->components->error(sprintf('%s: %s', AiProviders::label($provider), $exception->getMessage()));
				report($exception);
			}
		}

		try {
			$this->components->info(sprintf('OpenRouter: %d model(s) enriched with prices and capabilities.', $synchronizer->enrichFromOpenRouter()));
		} catch (Throwable $exception) {
			$failed = true;
			$this->components->error('OpenRouter: ' . $exception->getMessage());
			report($exception);
		}

		return $failed ? self::FAILURE : self::SUCCESS;
	}
}
