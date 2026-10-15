---
name: ai-usage-development
description: Track and attribute AI token usage and cost with spaanproductions/laravel-ai-usage. Use when adding or changing laravel/ai agents or AI calls (to attribute their usage to a feature, subject or user), working with AI model prices (ai_models, AiModel, ai-usage:sync-models), AI cost budgets, the AI usage dashboard/records/models pages, or the ai_usage_records table.
---

# AI Usage Development

`spaanproductions/laravel-ai-usage` records the tokens and cost of **every** laravel/ai call automatically, by listening to the laravel/ai events. You never record usage by hand: you only tell it what a call belongs to.

## When to use this skill

- Adding a new laravel/ai agent, or a new place that prompts an agent, generates images/embeddings/transcriptions or reranks.
- Querying or reporting on AI usage or cost (`AiUsageRecord`).
- Working with AI model prices (`AiModel`, `ai_models`, `ai-usage:sync-models`).
- Configuring budgets, retention, routes or the layout of the AI usage pages (`config/ai-usage.php`).

## What is recorded automatically

| laravel/ai event | `operation` |
|---|---|
| `AgentPrompted` / `AgentStreamed` | `chat` / `stream` |
| `EmbeddingsGenerated` | `embeddings` |
| `ImageGenerated` | `image` |
| `TranscriptionGenerated` | `transcription` |
| `Reranked` | `rerank` |
| `ProviderFailedOver` / `AgentFailedOver` | `failover` (status `failed`, with the error) |

Each row stores the provider, the model, the tokens, the cost, the latency, the invocation and conversation ids, the trace links (`trace_id` = the root invocation, `parent_invocation_id`, `parent_tool_invocation_id` for sub-agents and calls inside tools), the agent class, the feature, the subject (a morph) and the user. Failed rows get an `error_kind` (`ErrorKind` enum). With `ai-usage.trace.enabled` on, agent rows also store the full execution trace in `trace` (instructions, input, output, attachments and `spans`: model steps with tokens and cost, tool calls with arguments and results, each with `offset_ms` and `duration_ms`); `trace.attachments` copies prompt files to a disk, and `redaction` masks sensitive keys and patterns. Use `AiUsage::withoutRecording(fn () => ...)` to skip recording. A failure to record is reported and never breaks the AI call. Agent fakes (`MyAgent::fake()`) are recorded too, with zero tokens.

## Attributing usage

The feature, subject and user are resolved in this order:

1. A scoped `AiUsage::context()` around the call, where inner contexts win.
2. The agent, when it implements `ProvidesAiUsageContext`.
3. Fallbacks: the feature is the agent class name in snake_case, and the user is `auth()->user()`.

### Give every agent a feature

Implement `ProvidesAiUsageContext` on the agent. Use a stable snake_case feature key; in this application that is the `AiPurposes` key.

```php
use Illuminate\Database\Eloquent\Model;
use SpaanProductions\LaravelAiUsage\Contracts\ProvidesAiUsageContext;

class LinkAnalyzer implements Agent, HasStructuredOutput, ProvidesAiUsageContext
{
    use Promptable;

    public function aiUsageFeature(): string
    {
        return AiPurposes::LINK_ANALYSIS;
    }

    public function aiUsageSubject(): ?Model
    {
        return null;
    }
}
```

### Link the call to the record it was made for

Wrap the call in `AiUsage::context()`. This matters in queued jobs, where there is no authenticated user. The context returns the callback's result.

```php
use SpaanProductions\LaravelAiUsage\Facades\AiUsage;

$response = AiUsage::context(
    fn () => (new LinkAnalyzer())->prompt($prompt),
    subject: $this->link,
);

// All arguments are optional: feature, subject, user and metadata (merged into the row).
AiUsage::context(fn () => $this->importAll(), feature: 'bulk_import', user: $user, metadata: ['batch' => $batch->id]);
```

The record detail page links to the subject when the subject model has an `aiUsageUrl(): ?string` method.

### Usage of a subject model

Add the `HasAiUsage` trait to a model that is used as a subject. In this application, `Link`, `MailAttachment` and `File` already have it. The trait gives the model an `aiUsage()` morphMany relation and `aiUsageCost()`:

```php
use SpaanProductions\LaravelAiUsage\Concerns\HasAiUsage;

class Link extends Model
{
    use HasAiUsage;
}

$link->aiUsage;                 // AiUsageRecord collection
$link->aiUsageCost();           // total cost (float)

// For lists, avoid N+1: aiUsageCost() then uses the loaded sum.
Link::query()->withSum('aiUsage', 'total_cost')->get();
```

## Pricing

- Costs are calculated once, when the row is recorded, from the `ai_models` prices (per 1M tokens: `input_price_per_million`, `cached_input_price_per_million`, `cache_write_price_per_million`, `output_price_per_million`). The rates used are stored in `prices`.
- `input_tokens` on a record excludes the cached tokens, and `output_tokens` includes the reasoning tokens. `Support\UsageTokens` normalizes both laravel/ai shapes: 0.x `promptTokens`/`completionTokens`, and 1.x `inputTokens`/`outputTokens`, where `inputTokens` includes the cached tokens. Read usage objects through it instead of reading their properties directly.
- The model is matched on `provider` + `model_id`, then on the id without date/`-latest`, then on `openrouter_id`, then on the longest `model_id` it extends with version suffixes only (`-v2`, `-preview`). Models without a price get `pricing_missing = true` and a cost of 0.
- `php artisan ai-usage:sync-models` syncs the models and prices (OpenRouter catalogue). After a price change, run `php artisan ai-usage:reprice --missing-only` (or `--from=`/`--to=`, and `--dry-run` first).
- Use `SpaanProductions\LaravelAiUsage\Models\AiModel`; there is no `App\Models\AiModel`.

## Querying usage

```php
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Support\UsageFilters;

[$from, $until] = UsageFilters::range('this_month');

$cost = UsageFilters::query($from, $until, ['feature' => 'document_extraction'])->sum('total_cost');

AiUsageRecord::query()->whereMorphedTo('subject', $mail)->sum('total_cost');
```

`operation`, `status` and `error_kind` are cast to the `Operation`, `UsageStatus` and `ErrorKind` enums. `AiUsageRecord::query()->inTrace($traceId)` returns all rows of one run. `total_tokens` is input + cache read + cache write + output.

## Budgets, events and retention

- `config('ai-usage.budgets')` has entries of the form `name => ['period' => 'day'|'week'|'month', 'limit' => 50, 'thresholds' => [0.8, 1.0], 'feature' => null, 'mail' => ['…']]`. Each threshold fires `BudgetThresholdReached` once per period and mails the addresses.
- `AiUsageRecorded` fires after every saved row. Listen to it instead of to the laravel/ai events.
- `retention_days` together with `php artisan model:prune --model="SpaanProductions\LaravelAiUsage\Models\AiUsageRecord"` deletes old rows.

## Pages

The routes `ai-usage.dashboard`, `ai-usage.records.index`, `ai-usage.records.show` and `ai-usage.models.index` (the synced models, with a button that runs the same sync as `ai-usage:sync-models`) are full-page Livewire components behind `routes.middleware` plus the `gate`. They are configured in `config/ai-usage.php`, which also sets `routes.enabled`, `routes.prefix`, `layout` and `back_link`. To restyle them, publish the views with `php artisan vendor:publish --tag=ai-usage-views`. After changing the package's own views, run `npm run build` in the package directory to rebuild `dist/ai-usage.css`.

## Testing

```php
use SpaanProductions\LaravelAiUsage\Models\AiModel;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;

AiModel::factory()->withPricing(input: 2, output: 10, cachedInput: 0.5)->create(['model_id' => 'gpt-6-luna']);
AiUsageRecord::factory()->create(['feature' => 'link_analysis', 'total_cost' => 1.25]);
AiUsageRecord::factory()->failed()->create();
AiUsageRecord::factory()->pricingMissing()->create();
```

Fakes report zero tokens. To test token or cost handling, dispatch `new AgentPrompted($invocationId, $agentPrompt, new AgentResponse($invocationId, $text, new Usage(1000, 500), new Meta('openai', 'gpt-6-luna')))` yourself.

For demo data, run `php artisan db:seed --class="SpaanProductions\LaravelAiUsage\Database\Seeders\AiUsageDemoSeeder"`. The demo rows are tagged `metadata.demo` and are replaced on every run.
