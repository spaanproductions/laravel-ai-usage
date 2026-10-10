<?php

namespace SpaanProductions\LaravelAiUsage\Livewire;

use Attribute;
use Livewire\Features\SupportPageComponents\BaseLayout;

/**
 * Renders the page in the layout of config('ai-usage.layout'): the standalone layout of the
 * package, or a layout of the application.
 */
#[Attribute]
class ConfiguredLayout extends BaseLayout
{
	public function __construct()
	{
		parent::__construct((string) config('ai-usage.layout', 'ai-usage::layout'));
	}
}
