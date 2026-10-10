<?php

namespace SpaanProductions\LaravelAiUsage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What the AI calls inside an AiUsage::context() are attributed to.
 */
final readonly class UsageContext
{
	/** @param array<string, mixed> $metadata */
	public function __construct(
		public ?string $feature = null,
		public ?Model $subject = null,
		public ?Authenticatable $user = null,
		public array $metadata = [],
	) {
	}

	/** This context with the values of the given (inner) context on top. */
	public function merge(self $inner): self
	{
		return new self(
			feature: $inner->feature ?? $this->feature,
			subject: $inner->subject ?? $this->subject,
			user: $inner->user ?? $this->user,
			metadata: [...$this->metadata, ...$inner->metadata],
		);
	}
}
