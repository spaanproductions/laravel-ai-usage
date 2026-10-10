<?php

namespace SpaanProductions\LaravelAiUsage\Console\Commands;

use Illuminate\Console\Command;
use SpaanProductions\LaravelAiUsage\Support\AiModelSynchronizer;

class SyncAiModelsCommand extends Command
{
	protected $signature = 'ai-usage:sync-models
		{--provider=* : Only sync these providers (default: every provider with an API key, and always OpenRouter)}';

	protected $description = 'Sync the AI models of every configured provider and the public OpenRouter catalogue, with prices from OpenRouter';

	public function handle(): int
	{
		$results = resolve(AiModelSynchronizer::class)->syncAll((array) $this->option('provider'));

		foreach ($results as $result) {
			$result['failed']
				? $this->components->error($result['label'] . ': ' . $result['message'])
				: $this->components->info($result['label'] . ': ' . $result['message']);
		}

		return collect($results)->contains('failed', true) ? self::FAILURE : self::SUCCESS;
	}
}
