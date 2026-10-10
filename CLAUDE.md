# CLAUDE.md

Guidance for Claude Code when working in this package.

## What this is

`spaanproductions/laravel-ai-usage` records the tokens and cost of every laravel/ai call, prices
them with a synced model price list (`ai_models`) and shows them on standalone Livewire pages. Built for
our own applications (first: spaanproductions.nl) and kept compact on purpose.

- PHP 8.4+, Laravel 12/13, Livewire 4, laravel/ai 0.9 – 1.x.
- Built with `spatie/laravel-package-tools`. Tests run on Orchestra Testbench + PHPUnit.

## Commands

```bash
composer test        # PHPUnit via Testbench
composer analyse     # PHPStan (larastan), level 5
composer format      # php-cs-fixer (PSR-12, tabs, length-sorted imports)
npm run build        # compile dist/ai-usage.css (commit it)
```

## Architecture

```
src/
  LaravelAiUsageServiceProvider.php   config, views, commands, listener, gate, Livewire, routes, publishes
  AiUsage.php (+ Facades/AiUsage)     context stack, record(), latency start/stop
  Listeners/RecordLaravelAiUsage      one subscriber for all laravel/ai events
  Support/UsageTokens                 normalizes 0.x and 1.x usage objects
  Pricing/CostCalculator              ai_models lookup + CostBreakdown
  Budgets/BudgetGuard                 once-per-period alerts (ai_usage_budget_alerts)
  Concerns/HasAiUsage                 trait for subject models
  Contracts/ProvidesAiUsageContext    optional agent interface (feature, subject)
  Livewire/{Dashboard,Records,RecordDetail} + ConfiguredLayout
  Models/{AiModel,AiUsageRecord,AiUsageBudgetAlert}
  Support/{AiModelSynchronizer,AiProviders}  ai-usage:sync-models (OpenRouter prices)
database/{migrations,factories,seeders}/
resources/views/   layout + partials + livewire pages;  resources/boost/skills/  Boost skill
tests/             Testbench TestCase + Fixtures (User, Document, SummaryAgent, PlainAgent)
```

## Conventions

- Tabs, PSR-12, constructor promotion, explicit return types, imports sorted by length (`composer format`).
- Test methods are `snake_case`; keep `composer test` and `composer analyse` green.
- Create records with `Model::create()`; use `Model::query()->` only to build queries.
- Commit messages have no `Co-Authored-By` or other AI attribution trailer.
- Names stay under the `ai-usage` prefix (config `ai-usage`, views `ai-usage::`, routes and Livewire
  `ai-usage.*`, tables `ai_usage_*`, commands `ai-usage:*`, publish tags `ai-usage-*`) so they never
  collide with `spaanproductions/laravel-switchbot-frame` (`switchbot*`, `frame_*`) or the host app.
- Update `CHANGELOG.md` for every user-facing change and the Boost skill when the API changes.

## Deliberate decisions

Keep these unless the reason no longer holds. Each was chosen on purpose.

### Scope

- **Our own package instead of `sanjayacloud/ai-model-usage-tracker`:** no third-party code in the path
  of every AI call, full control over behaviour and data. Kept smaller than that package.
- **Only laravel/ai events are captured.** No global HTTP client sniffing (buffers every response and
  breaks streaming), no Prism, no fluent manual builder. `AiUsage::record()` exists for internal use.
- **Not built (for now):** queued recording, a JSON API / API resource, a `report` CLI, Inertia or
  starter-kit pages with a nav installer, translations (the UI is English), LiteLLM pricing, per-image
  or per-second prices, config price overrides. Add them only when actually needed.
- **Also declined** (reviewed against backtik-ch and harrisonclewis `laravel-ai-usage`, 2026-10): a
  Filament plugin, live polling, free-text search on Records, separate system/user prompt storage, a
  `show_costs` flag, a public manual record builder and per-call price overrides.

### Recording

- **Recording never breaks the AI call:** every handler runs in `safely()`, which reports and swallows
  exceptions. Tests guard this.
- **Recording is synchronous.** One insert per call; no queue mode.
- **Prompt and response text are not stored** unless `store_content` is on (then truncated to
  `content_limit`). Usage rows must not leak document or mail contents by default.
- **Attribution order:** a scoped `AiUsage::context()` (nested, inner values win) > the agent's
  `ProvidesAiUsageContext` > fallbacks (feature = agent class in snake_case, user = `auth()->user()`).
  The subject is a morph; `HasAiUsage` gives subject models the inverse relation.
- **A failover row does not stop the latency timer:** the invocation continues on the next provider,
  so its final row keeps the full latency. `AgentFailed` (0.11+) records a failed row.
- **Start times are bounded:** dropped after `STALE_AFTER_SECONDS` (1 h) and capped at `MAX_RUNNING`
  (1,000), so long-running Horizon workers do not leak memory for calls that never complete.

### Tokens and cost

- **Supports laravel/ai 0.x and 1.x via `Support\UsageTokens`.** 0.x reports `promptTokens` without
  the cached tokens; 1.x reports `inputTokens` including cache read/write (nullable subsets). Records
  always store `input_tokens` excluding the cache, so each token is charged once at its own rate.
  Output includes reasoning; reasoning is informational and never charged again. Read usage objects
  through `UsageTokens`, never their properties directly.
- **Prices come from `ai_models` only** (synced from OpenRouter by `ai-usage:sync-models`). Cost is
  calculated once at record time and the rates are snapshotted in `prices`; `ai-usage:reprice`
  recalculates history. Missing or variable (negative) prices give cost 0 and `pricing_missing`.
- **`ai_models` moved into this package** (model, synchronizer, providers, command) from the app.

### Database

- **Migrations are publish-only (Laravel's advice for packages), never auto-loaded.** Two tags:
  `ai-usage-migrations` (usage tables) and `ai-usage-models-migrations` (`ai_models`, separate so an app
  that already has the table can skip it). The files carry a timestamp prefix so `vendor:publish` can
  re-stamp them.
- **Budget alerts fire once per budget, period and threshold**, enforced by a unique index on
  `ai_usage_budget_alerts` and `insertOrIgnore`; event `BudgetThresholdReached` plus optional mail.
- **Retention via `MassPrunable`** and `retention_days` (null keeps everything); the host schedules
  `model:prune`.

### Pages

- **Routes are on by default** at a configurable prefix and middleware, plus a gate. The package's own
  gate default **denies everyone**; the host defines it (spaanproductions points `gate` at `super-admin`).
- **Standalone layout with its own compiled stylesheet**, served by a route or published to `public/`,
  so the pages work without the host's Tailwind build. `layout` can point at a host layout instead.
  The layout is applied with the `ConfiguredLayout` attribute (a `BaseLayout` subclass reading config),
  not `->layout()`, which larastan cannot see.
- **Tailwind scans only `resources/views`** (`@import "tailwindcss" source(none)`), so the build does not
  pick up class-like words from the README or changelog. `dist/ai-usage.css` is committed.
- **The chart is server-rendered CSS bars**, no JS chart library. Its tooltip uses Tailwind
  `group-hover`, not the host's `x-tooltip` Alpine plugin (the standalone layout does not load host JS).
  The chart follows the page's period selector; it has no own period switch or totals (by request).
  It buckets per hour (periods up to 48 h, with a per-driver hour SQL expression), day or month, and a
  toggle switches the bars between cost, tokens and requests.
- **Dropdowns use a custom chevron** (`appearance: none` + padding) because the native arrow sat
  against the edge.
- **Views render with `ViewFactory::make('ai-usage::…')`**; larastan resolves the namespaced views
  through `testbench.yaml`, which registers the providers. Keep that file.

### Repository

- **License Apache-2.0** with `NOTICE`, like the switchbot package.
- **`.gitattributes` export-ignores** `art/`, `tests/`, `.github/`, tooling and this file, so the
  Composer dist only ships what applications need.
- **CI matrix:** PHP 8.4/8.5 × Laravel 12/13 × laravel/ai `^0.9`/`^1.0` × lowest/highest, plus PHPStan.
  Tests that need a newer laravel/ai (e.g. `AgentFailed`) skip themselves on older versions, and test
  helpers build version-appropriate usage objects (`TestCase::usage()`).
- **Dependabot:** weekly PRs for Composer and GitHub Actions with a 3-day cooldown; **no npm PRs**,
  npm is covered by Dependabot alerts only (security update PRs are off in the repo settings).
- **npm `overrides` pins `@parcel/watcher` to `^2.6.0`**: `@tailwindcss/cli` 4.3.3 pins 2.5.1, whose
  `micromatch`/`braces` chain fails `npm audit`. Remove the override once the CLI no longer pins it.
- **Screenshots in `art/` use demo data with names replaced by "Demo user"**; the repo is public, so
  never commit real customer or user data.
