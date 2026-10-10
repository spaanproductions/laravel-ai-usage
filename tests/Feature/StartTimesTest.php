<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use ReflectionProperty;
use SpaanProductions\LaravelAiUsage\AiUsage;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;

class StartTimesTest extends TestCase
{
	public function test_a_completed_call_forgets_its_start_time(): void
	{
		$usage = resolve(AiUsage::class);

		$usage->start('invocation-1');

		$this->assertSame(1, $usage->running());
		$this->assertNotNull($usage->stop('invocation-1'));
		$this->assertSame(0, $usage->running());
		$this->assertNull($usage->stop('invocation-1'));
	}

	public function test_stale_start_times_of_calls_that_never_completed_are_dropped(): void
	{
		$usage = resolve(AiUsage::class);
		$startedAt = new ReflectionProperty(AiUsage::class, 'startedAt');
		$startedAt->setValue($usage, ['never-completed' => hrtime(true) - (AiUsage::STALE_AFTER_SECONDS + 1) * 1_000_000_000]);

		$usage->start('invocation-1');

		$this->assertSame(['invocation-1'], array_keys($startedAt->getValue($usage)));
	}

	public function test_the_number_of_start_times_is_capped(): void
	{
		$usage = resolve(AiUsage::class);

		foreach (range(1, AiUsage::MAX_RUNNING + 5) as $call) {
			$usage->start('invocation-' . $call);
		}

		$this->assertSame(AiUsage::MAX_RUNNING, $usage->running());
		$this->assertNull($usage->stop('invocation-1'));
		$this->assertNotNull($usage->stop('invocation-' . (AiUsage::MAX_RUNNING + 5)));
	}
}
