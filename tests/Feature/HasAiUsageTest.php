<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Tests\Fixtures\Document;

class HasAiUsageTest extends TestCase
{
	public function test_a_subject_has_its_usage_records_and_total_cost(): void
	{
		$document = Document::create(['title' => 'Invoice']);
		$other = Document::create(['title' => 'Receipt']);

		AiUsageRecord::factory()->for($document, 'subject')->create(['total_cost' => 0.25]);
		AiUsageRecord::factory()->for($document, 'subject')->create(['total_cost' => 0.5]);
		AiUsageRecord::factory()->for($other, 'subject')->create(['total_cost' => 9]);

		$this->assertCount(2, $document->aiUsage);
		$this->assertSame(0.75, $document->aiUsageCost());

		$loaded = Document::query()->withSum('aiUsage', 'total_cost')->findOrFail($document->id);
		$this->assertSame(0.75, $loaded->aiUsageCost());
	}
}
