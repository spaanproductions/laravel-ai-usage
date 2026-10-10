<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::create(config('ai-usage.tables.models', 'ai_models'), function (Blueprint $table) {
			$table->id();
			$table->string('provider');
			$table->string('model_id');
			$table->string('openrouter_id')->nullable();
			$table->string('name')->nullable();
			$table->text('description')->nullable();
			$table->string('owned_by')->nullable();
			$table->boolean('is_visible')->default(false);
			$table->decimal('input_price_per_million', 12, 4)->nullable();
			$table->decimal('cached_input_price_per_million', 12, 4)->nullable();
			$table->decimal('cache_write_price_per_million', 12, 4)->nullable();
			$table->decimal('output_price_per_million', 12, 4)->nullable();
			$table->unsignedInteger('context_length')->nullable();
			$table->unsignedInteger('max_completion_tokens')->nullable();
			$table->json('modalities')->nullable();
			$table->json('supported_parameters')->nullable();
			$table->timestamp('provider_created_at')->nullable();
			$table->timestamp('openrouter_synced_at')->nullable();
			$table->timestamps();
			$table->softDeletes();

			$table->unique(['provider', 'model_id']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists(config('ai-usage.tables.models', 'ai_models'));
	}
};
