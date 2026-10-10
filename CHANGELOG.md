# Changelog

All notable changes to `spaanproductions/laravel-ai-usage` are documented in this file.

## Unreleased

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
