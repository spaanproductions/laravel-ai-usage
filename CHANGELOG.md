# Changelog

All notable changes to `spaanproductions/laravel-ai-usage` are documented in this file.

## Unreleased

- `content_limit` can be set with `AI_USAGE_CONTENT_LIMIT`.
- Traces have their own limit per string, `trace.content_limit` (`AI_USAGE_TRACE_CONTENT_LIMIT`). It defaults to `content_limit`, so nothing changes unless you set it.

## v0.1.7 - 2026-10-19

- Separate retention for parts of a row, so the rows (tokens and cost) can be kept longer. `model:prune` now also clears the prompt and response with `content_retention_days`, the execution trace (and its attachments) with `trace_retention_days`, and only the attachment files with `attachment_retention_days`. All are off by default.
- The record page no longer links to attachments the retention has deleted.

## v0.1.6 - 2026-10-17

- Publishing the migrations again after an update only adds the new migrations. Before, `vendor:publish --tag=ai-usage-migrations` copied every migration again under a new date, so `migrate` failed on tables that already exist. If you followed the v0.1.4 upgrade note, delete the duplicated `create_ai_usage_records_table` and `create_ai_usage_budget_alerts_table` migrations that are not yet migrated.

## v0.1.5 - 2026-10-17

- Applications that use immutable dates (`Date::use(CarbonImmutable::class)`, as the Laravel starter kits do) no longer get a `TypeError` on the dashboard and record list, and their budget alerts fire again. Dates are typed as `CarbonInterface`, and the dashboard chart no longer relies on mutating its date cursor.

## v0.1.4 - 2026-10-15

Publish the `ai-usage-migrations` again and migrate: a new migration adds the trace columns. Update to v0.1.6 first, or delete the duplicated older migrations this publish adds (see v0.1.6).

- Execution traces. Every row links to its trace and parent call (`trace_id`, `parent_invocation_id`, `parent_tool_invocation_id`), so sub-agents and calls made inside tools form a tree on the record page.
- `trace.enabled` (`AI_USAGE_TRACE`, off by default) stores the full trace of agent calls on laravel/ai 1.x: instructions, input, output, every model step (tokens, cost, text) and every tool call (arguments, result), with timings. The record page shows it as a waterfall with expandable spans.
- `trace.attachments` (`AI_USAGE_TRACE_ATTACHMENTS`, off by default) copies prompt attachments to a disk, downloadable from the record page and pruned with the rows.
- `redaction` (on by default) masks values under sensitive keys (`*_token`, `password`, `api_key`, …) and optional regex patterns in stored prompts, responses and traces.
- Failed calls get an `error_kind` (rate limited, overloaded, connection, credits, tool error, exception), with a filter on the record list and in the CSV export.
- `AiUsage::withoutRecording()` skips recording for a block of code.
- Model ids with a version suffix (e.g. `claude-sonnet-5-5-v2`) are priced with their base model.

## v0.1.3 - 2026-10-12

- Configurable table names (`ai-usage.tables`), used by the migrations and the models, for applications that already have a table with one of the default names.

## v0.1.2 - 2026-10-10

- A "Models" page next to Dashboard and Records: every synced model with its summary, modality icons, context and prices per million tokens, filterable by provider, pricing and search, sorted newest first with a sort dropdown, and a "Sync models" button that runs the same sync as `ai-usage:sync-models`.
- The stylesheet URL carries a version hash, so browsers load the new stylesheet after an update instead of keeping the cached one for a year.

## v0.1.1 - 2026-10-10

- "Today" and "Yesterday" periods on the dashboard and the record list.
- The dashboard chart shows hours for periods of up to two days.
- The dashboard chart can show cost, tokens or requests.

## v0.1.0 - 2026-10-10

First release.

### Added

- Records the tokens and cost of every laravel/ai call from its events: agent prompts and streams, embeddings, images, audio, transcriptions, reranking, failovers and failed agent calls.
- Support for laravel/ai 0.9 – 0.11 and 1.x. Token counts are normalized, so cached tokens are never charged twice on 1.x.
- Latency per call. Start times of calls that never complete are dropped after an hour and capped at 1,000, so long-running queue workers do not leak memory.
- Attribution to a feature, subject and user via `AiUsage::context()` and the `ProvidesAiUsageContext` agent interface. The `HasAiUsage` trait for subject models.
- Pricing from the `ai_models` table, synced from OpenRouter by `ai-usage:sync-models`, with the rates used stored on each record. `ai-usage:reprice` recalculates historical rows.
- Budgets per day, week or month, optionally per feature, with the `BudgetThresholdReached` event (once per period and threshold) and an optional mail.
- Retention through `model:prune` and `retention_days`.
- Optional storage of the prompt and response text (off by default).
- Dashboard, record list (filters, sorting, CSV export) and record detail as full-page Livewire components, with a standalone layout and compiled stylesheet. Routes, middleware, gate and layout are configurable.
- Publish-only migrations (`ai-usage-migrations`, `ai-usage-models-migrations`), a demo seeder and a Laravel Boost skill.
