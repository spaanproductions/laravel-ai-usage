<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Recording
	|--------------------------------------------------------------------------
	|
	| Every laravel/ai call (agents, streams, embeddings, images, transcriptions,
	| reranking and failovers) is recorded with its tokens and cost. A failure to
	| record is reported but never breaks the AI call itself.
	|
	| The prompt and response text are only stored when store_content is on, and
	| then truncated to content_limit characters.
	|
	*/

	'enabled' => env('AI_USAGE_ENABLED', true),

	'store_content' => env('AI_USAGE_STORE_CONTENT', false),

	'content_limit' => 5000,

	/*
	|--------------------------------------------------------------------------
	| Execution traces
	|--------------------------------------------------------------------------
	|
	| Every row is linked to its trace (the root call) and parent call, so the
	| record page shows sub-agents as a tree. With tracing on, each agent row
	| also stores its full execution trace: the instructions, input and output,
	| every model step (tokens, cost, text) and every tool call (arguments,
	| result), with timings. Strings are truncated to content_limit and run
	| through the redaction below.
	|
	| With attachments on, the files attached to a prompt are copied to the
	| disk and path below (per day, deleted with the rows by the retention).
	| Remote and provider files are only described, never downloaded.
	|
	*/

	'trace' => [
		'enabled' => env('AI_USAGE_TRACE', false),
		'attachments' => env('AI_USAGE_TRACE_ATTACHMENTS', false),
		'disk' => env('AI_USAGE_TRACE_DISK', 'local'),
		'path' => 'ai-usage/attachments',
	],

	/*
	|--------------------------------------------------------------------------
	| Redaction
	|--------------------------------------------------------------------------
	|
	| On by default: stored content (prompt, response and trace) is masked first:
	| values under the keys below (`*` is a wildcard) and matches of the regex
	| patterns, given as `regex => replacement` or as a plain list of regexes.
	|
	*/

	'redaction' => [
		'enabled' => env('AI_USAGE_REDACTION', true),
		'keys' => ['password', 'secret', 'api_key', 'authorization', '*_token', '*_secret'],
		'patterns' => [
			// '/[\w.+-]+@[\w-]+\.[\w.-]+/' => '[email]',
		],
		'replacement' => '[redacted]',
	],

	/*
	|--------------------------------------------------------------------------
	| Users
	|--------------------------------------------------------------------------
	|
	| The model a usage row's user_id points to. The authenticated user is used
	| unless a user is passed with AiUsage::context().
	|
	*/

	'user_model' => env('AI_USAGE_USER_MODEL', 'App\\Models\\User'),

	/*
	|--------------------------------------------------------------------------
	| Tables
	|--------------------------------------------------------------------------
	|
	| The database tables of the package. Change a name when your application
	| already has a table with that name; the migrations and the models both
	| read these, so set them before running the migrations.
	|
	*/

	'tables' => [
		'models' => 'ai_models',
		'records' => 'ai_usage_records',
		'budget_alerts' => 'ai_usage_budget_alerts',
	],

	/*
	|--------------------------------------------------------------------------
	| Pricing
	|--------------------------------------------------------------------------
	|
	| Costs are calculated with the prices of the ai_models table (synced from
	| OpenRouter by ai-usage:sync-models, per 1M tokens) and stored on each row.
	|
	*/

	'currency' => 'USD',

	/*
	|--------------------------------------------------------------------------
	| Retention
	|--------------------------------------------------------------------------
	|
	| Rows older than this many days are deleted by `php artisan model:prune`.
	| Null keeps every row.
	|
	*/

	'retention_days' => env('AI_USAGE_RETENTION_DAYS'),

	/*
	|--------------------------------------------------------------------------
	| Budgets
	|--------------------------------------------------------------------------
	|
	| Spend limits per day, week or month, optionally for one feature. When the
	| spend of a period crosses a threshold (a fraction of the limit) the
	| BudgetThresholdReached event fires once, and a mail is sent to the given
	| addresses.
	|
	| 'monthly' => [
	|     'period' => 'month',
	|     'limit' => 50,
	|     'thresholds' => [0.8, 1.0],
	|     'feature' => null,
	|     'mail' => ['admin@example.com'],
	| ],
	|
	*/

	'budgets' => [],

	/*
	|--------------------------------------------------------------------------
	| Routes
	|--------------------------------------------------------------------------
	|
	| The dashboard, record list and record detail pages. Access needs the
	| middleware below plus the gate; define the gate in your application, the
	| package default denies everyone.
	|
	*/

	'routes' => [
		'enabled' => env('AI_USAGE_ROUTES', true),
		'prefix' => env('AI_USAGE_ROUTE_PREFIX', 'ai-usage'),
		'middleware' => ['web', 'auth'],
	],

	'gate' => 'viewAiUsage',

	/*
	|--------------------------------------------------------------------------
	| Views
	|--------------------------------------------------------------------------
	|
	| The pages use the package's standalone layout. Set a layout of your own
	| (e.g. "layouts::app") to render them inside your application; its Tailwind
	| build then has to scan the package views.
	|
	*/

	'layout' => 'ai-usage::layout',

	'back_link' => [
		'label' => env('AI_USAGE_BACK_LINK_LABEL', 'Back'),
		'route' => env('AI_USAGE_BACK_LINK_ROUTE'),
		'url' => env('AI_USAGE_BACK_LINK_URL'),
	],

];
