<?php

namespace SpaanProductions\LaravelAiUsage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use SpaanProductions\LaravelAiUsage\Enums\Operation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SpaanProductions\LaravelAiUsage\Enums\UsageStatus;
use SpaanProductions\LaravelAiUsage\Database\Factories\AiUsageRecordFactory;

/**
 * One recorded AI call with its tokens and cost. The cost is calculated with the prices of
 * the matching AI model when the call is recorded; the rates used are kept in `prices`.
 *
 * @property int $id
 * @property string|null $invocation_id
 * @property string|null $conversation_id
 * @property Operation $operation
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $ai_model_id
 * @property string|null $agent
 * @property string|null $feature
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property int|null $user_id
 * @property UsageStatus $status
 * @property string|null $error
 * @property int|null $latency_ms
 * @property int $input_tokens
 * @property int $cache_read_tokens
 * @property int $cache_write_tokens
 * @property int $output_tokens
 * @property int $reasoning_tokens
 * @property int $total_tokens
 * @property float $input_cost
 * @property float $cache_read_cost
 * @property float $cache_write_cost
 * @property float $output_cost
 * @property float $total_cost
 * @property string $currency
 * @property bool $pricing_missing
 * @property array<string, float|null>|null $prices
 * @property string|null $prompt
 * @property string|null $response
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AiModel|null $aiModel
 * @property-read Model|null $subject
 * @property-read Model|null $user
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
class AiUsageRecord extends Model
{
	/** @use HasFactory<AiUsageRecordFactory> */
	use HasFactory;
	use MassPrunable;

	protected $table = 'ai_usage_records';

	public $guarded = [];

	protected static function newFactory(): AiUsageRecordFactory
	{
		return AiUsageRecordFactory::new();
	}

	protected function casts(): array
	{
		return [
			'operation' => Operation::class,
			'status' => UsageStatus::class,
			'latency_ms' => 'integer',
			'input_tokens' => 'integer',
			'cache_read_tokens' => 'integer',
			'cache_write_tokens' => 'integer',
			'output_tokens' => 'integer',
			'reasoning_tokens' => 'integer',
			'total_tokens' => 'integer',
			'input_cost' => 'float',
			'cache_read_cost' => 'float',
			'cache_write_cost' => 'float',
			'output_cost' => 'float',
			'total_cost' => 'float',
			'pricing_missing' => 'boolean',
			'prices' => 'array',
			'metadata' => 'array',
		];
	}

	/** @return BelongsTo<AiModel, $this> */
	public function aiModel(): BelongsTo
	{
		return $this->belongsTo(AiModel::class)->withTrashed();
	}

	/** @return MorphTo<Model, $this> */
	public function subject(): MorphTo
	{
		return $this->morphTo();
	}

	/** @return BelongsTo<Model, $this> */
	public function user(): BelongsTo
	{
		return $this->belongsTo((string) config('ai-usage.user_model'));
	}

	/** @param Builder<AiUsageRecord> $query */
	public function scopeBetweenDates(Builder $query, mixed $from, mixed $until): void
	{
		$query->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
			->when($until, fn (Builder $query) => $query->where('created_at', '<=', $until));
	}

	/** @return Builder<AiUsageRecord> */
	public function prunable(): Builder
	{
		$days = config('ai-usage.retention_days');

		return $days === null || $days === ''
			? self::query()->whereRaw('1 = 0')
			: self::query()->where('created_at', '<', now()->subDays((int) $days));
	}

	public function isFailed(): bool
	{
		return $this->status === UsageStatus::Failed;
	}
}
