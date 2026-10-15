<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Unit;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use Laravel\Ai\Exceptions\RateLimitedException;
use SpaanProductions\LaravelAiUsage\Enums\ErrorKind;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use SpaanProductions\LaravelAiUsage\Support\ErrorClassifier;

class ErrorClassifierTest extends TestCase
{
	public function test_it_classifies_by_exception_class(): void
	{
		$this->assertSame(ErrorKind::Overloaded, ErrorClassifier::classify(ProviderOverloadedException::forProvider('openai')));

		if (class_exists(RateLimitedException::class)) {
			$this->assertSame(ErrorKind::RateLimited, ErrorClassifier::classify(RateLimitedException::forProvider('openai')));
		}
	}

	public function test_it_classifies_by_http_status(): void
	{
		$this->assertSame(ErrorKind::RateLimited, ErrorClassifier::classify(new RuntimeException('Slow down', 429)));
		$this->assertSame(ErrorKind::Credits, ErrorClassifier::classify(new RuntimeException('Pay up', 402)));
		$this->assertSame(ErrorKind::Overloaded, ErrorClassifier::classify(new RuntimeException('Busy', 529)));
	}

	public function test_it_classifies_by_message(): void
	{
		$this->assertSame(ErrorKind::RateLimited, ErrorClassifier::classify('Rate limit reached for gpt-6'));
		$this->assertSame(ErrorKind::Connection, ErrorClassifier::classify(new RuntimeException('The request timed out.')));
		$this->assertSame(ErrorKind::Exception, ErrorClassifier::classify(new RuntimeException('Undefined index: summary')));
	}
}
