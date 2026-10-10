<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
	public function up(): void
	{
		Schema::create('ai_usage_records', function (Blueprint $table) {
			$table->id();
			$table->string('invocation_id')->nullable()->index();
			$table->string('conversation_id')->nullable()->index();
			$table->string('operation')->index();
			$table->string('provider')->nullable()->index();
			$table->string('model')->nullable()->index();
			$table->foreignId('ai_model_id')->nullable();
			$table->string('agent')->nullable();
			$table->string('feature')->nullable()->index();
			$table->nullableMorphs('subject');
			$table->foreignId('user_id')->nullable()->index();
			$table->string('status')->index();
			$table->text('error')->nullable();
			$table->unsignedInteger('latency_ms')->nullable();
			$table->unsignedBigInteger('input_tokens')->default(0);
			$table->unsignedBigInteger('cache_read_tokens')->default(0);
			$table->unsignedBigInteger('cache_write_tokens')->default(0);
			$table->unsignedBigInteger('output_tokens')->default(0);
			$table->unsignedBigInteger('reasoning_tokens')->default(0);
			$table->unsignedBigInteger('total_tokens')->default(0);
			$table->decimal('input_cost', 16, 8)->default(0);
			$table->decimal('cache_read_cost', 16, 8)->default(0);
			$table->decimal('cache_write_cost', 16, 8)->default(0);
			$table->decimal('output_cost', 16, 8)->default(0);
			$table->decimal('total_cost', 16, 8)->default(0);
			$table->char('currency', 3)->default('USD');
			$table->boolean('pricing_missing')->default(false);
			$table->json('prices')->nullable();
			$table->longText('prompt')->nullable();
			$table->longText('response')->nullable();
			$table->json('metadata')->nullable();
			$table->timestamp('created_at')->nullable()->index();
			$table->timestamp('updated_at')->nullable();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('ai_usage_records');
	}
};
