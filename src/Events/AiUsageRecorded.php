<?php

namespace SpaanProductions\LaravelAiUsage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;

class AiUsageRecorded
{
	use Dispatchable;

	public function __construct(public AiUsageRecord $record)
	{
	}
}
