<?php

namespace SpaanProductions\LaravelAiUsage\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implement on a laravel/ai agent to attribute its usage to a feature and a subject. A scoped
 * AiUsage::context() around the call takes precedence.
 */
interface ProvidesAiUsageContext
{
	/** The feature the usage belongs to, e.g. "document_extraction". */
	public function aiUsageFeature(): ?string;

	/** The record the call was made for, e.g. the mail whose document was read. */
	public function aiUsageSubject(): ?Model;
}
