<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('ai_usage_budget_alerts', function (Blueprint $table) {
			$table->id();
			$table->string('budget');
			$table->date('period_start');
			$table->decimal('threshold', 5, 4);
			$table->decimal('limit', 16, 8);
			$table->decimal('spent', 16, 8);
			$table->timestamp('notified_at');
			$table->timestamps();

			$table->unique(['budget', 'period_start', 'threshold']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('ai_usage_budget_alerts');
	}
};
