<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpaanProductions\LaravelAiUsage\Support\UsageTokens;

/**
 * Both laravel/ai usage shapes, independent of the installed version.
 */
class UsageTokensTest extends TestCase
{
	public function test_it_reads_the_0x_usage_where_prompt_tokens_exclude_the_cache(): void
	{
		$usage = (object) ['promptTokens' => 1000, 'completionTokens' => 500, 'cacheWriteInputTokens' => 300, 'cacheReadInputTokens' => 2000, 'reasoningTokens' => 100];

		$this->assertSame([
			'input_tokens' => 1000,
			'cache_read_tokens' => 2000,
			'cache_write_tokens' => 300,
			'output_tokens' => 500,
			'reasoning_tokens' => 100,
		], UsageTokens::from($usage));
	}

	public function test_it_reads_the_1x_usage_where_input_tokens_include_the_cache(): void
	{
		$usage = (object) ['inputTokens' => 3300, 'outputTokens' => 500, 'cacheReadInputTokens' => 2000, 'cacheWriteInputTokens' => 300, 'reasoningTokens' => 100];

		$this->assertSame([
			'input_tokens' => 1000,
			'cache_read_tokens' => 2000,
			'cache_write_tokens' => 300,
			'output_tokens' => 500,
			'reasoning_tokens' => 100,
		], UsageTokens::from($usage));
	}

	public function test_unreported_1x_cache_and_reasoning_counts_are_zero(): void
	{
		$usage = (object) ['inputTokens' => 1200, 'outputTokens' => 300, 'cacheReadInputTokens' => null, 'cacheWriteInputTokens' => null, 'reasoningTokens' => null];

		$this->assertSame(['input_tokens' => 1200, 'cache_read_tokens' => 0, 'cache_write_tokens' => 0, 'output_tokens' => 300, 'reasoning_tokens' => 0], UsageTokens::from($usage));
	}

	public function test_a_plain_1x_usage_without_text_details_is_read(): void
	{
		$this->assertSame(['input_tokens' => 800, 'cache_read_tokens' => 0, 'cache_write_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0], UsageTokens::from((object) ['inputTokens' => 800, 'outputTokens' => 0]));
		$this->assertSame(0, UsageTokens::from(null)['input_tokens']);
	}

	public function test_embeddings_tokens_are_read_from_both_versions(): void
	{
		$this->assertSame(1500, UsageTokens::fromEmbeddings((object) ['tokens' => 1500])['input_tokens']);
		$this->assertSame(1500, UsageTokens::fromEmbeddings((object) ['usage' => (object) ['inputTokens' => 1500, 'outputTokens' => 0]])['input_tokens']);
	}

	public function test_billing_details_are_kept(): void
	{
		$this->assertSame(
			['audio_seconds' => 12.5, 'image_output_tokens' => 4000, 'search_units' => 1.0],
			UsageTokens::details((object) ['audioSeconds' => 12.5, 'imageInputTokens' => null, 'imageOutputTokens' => 4000, 'searchUnits' => 1.0]),
		);
		$this->assertSame([], UsageTokens::details((object) ['promptTokens' => 10]));
	}
}
