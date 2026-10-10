<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Fixtures;

use Stringable;
use Laravel\Ai\Promptable;
use Laravel\Ai\Contracts\Agent;

/**
 * An agent without usage context: its feature falls back to its class name.
 */
class PlainAgent implements Agent
{
	use Promptable;

	public function instructions(): Stringable|string
	{
		return 'Answer the question.';
	}
}
