<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Fixtures;

use RuntimeException;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Contracts\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * A tool that looks up a policy, or throws for the query "fail".
 */
class LookupTool implements Tool
{
	public function description(): string
	{
		return 'Look up a policy.';
	}

	public function handle(Request $request): string
	{
		if ($request['query'] === 'fail') {
			throw new RuntimeException('The policy database is down.');
		}

		return 'Trekking poles have a two-year warranty.';
	}

	public function schema(JsonSchema $schema): array
	{
		return [
			'query' => $schema->string()->required(),
			'api_token' => $schema->string(),
		];
	}
}
