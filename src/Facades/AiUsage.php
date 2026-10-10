<?php

namespace SpaanProductions\LaravelAiUsage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed context(\Closure $callback, ?string $feature = null, ?\Illuminate\Database\Eloquent\Model $subject = null, ?\Illuminate\Contracts\Auth\Authenticatable $user = null, array $metadata = [])
 * @method static \SpaanProductions\LaravelAiUsage\UsageContext currentContext()
 * @method static \SpaanProductions\LaravelAiUsage\Models\AiUsageRecord record(array $attributes, ?object $agent = null, ?string $prompt = null, ?string $response = null)
 * @see \SpaanProductions\LaravelAiUsage\AiUsage
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
class AiUsage extends Facade
{
	protected static function getFacadeAccessor(): string
	{
		return \SpaanProductions\LaravelAiUsage\AiUsage::class;
	}
}
