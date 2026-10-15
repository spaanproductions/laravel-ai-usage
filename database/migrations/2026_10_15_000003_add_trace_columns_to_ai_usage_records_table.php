<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::table(config('ai-usage.tables.records', 'ai_usage_records'), function (Blueprint $table) {
			$table->string('trace_id')->nullable()->index()->after('conversation_id');
			$table->string('parent_invocation_id')->nullable()->index()->after('trace_id');
			$table->string('parent_tool_invocation_id')->nullable()->after('parent_invocation_id');
			$table->string('error_kind')->nullable()->index()->after('error');
			$table->json('trace')->nullable()->after('response');
		});
	}

	public function down(): void
	{
		Schema::table(config('ai-usage.tables.records', 'ai_usage_records'), function (Blueprint $table) {
			$table->dropIndex(['trace_id']);
			$table->dropIndex(['parent_invocation_id']);
			$table->dropIndex(['error_kind']);
			$table->dropColumn(['trace_id', 'parent_invocation_id', 'parent_tool_invocation_id', 'error_kind', 'trace']);
		});
	}
};
