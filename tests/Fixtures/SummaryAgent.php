<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Fixtures;

use Stringable;
use Laravel\Ai\Promptable;
use Laravel\Ai\Contracts\Agent;
use Illuminate\Database\Eloquent\Model;
use SpaanProductions\LaravelAiUsage\Contracts\ProvidesAiUsageContext;

/**
 * An agent that names its feature.
 */
class SummaryAgent implements Agent, ProvidesAiUsageContext
{
	use Promptable;

	public function instructions(): Stringable|string
	{
		return 'Summarize the text.';
	}

	public function aiUsageFeature(): string
	{
		return 'summaries';
	}

	public function aiUsageSubject(): ?Model
	{
		return null;
	}
}
