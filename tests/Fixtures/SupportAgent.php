<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Fixtures;

use Stringable;
use Laravel\Ai\Promptable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;

/**
 * An agent with a tool and a sub-agent (PlainAgent, run as a tool), for execution traces.
 */
class SupportAgent implements Agent, HasTools
{
	use Promptable;

	public function instructions(): Stringable|string
	{
		return 'Answer support questions.';
	}

	public function tools(): iterable
	{
		return [new LookupTool, new PlainAgent];
	}
}
