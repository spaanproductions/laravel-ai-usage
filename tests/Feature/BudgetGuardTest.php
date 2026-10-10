<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\AnonymousNotifiable;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Budgets\BudgetGuard;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Events\BudgetThresholdReached;
use SpaanProductions\LaravelAiUsage\Notifications\BudgetThresholdReachedNotification;

class BudgetGuardTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		Event::fake([BudgetThresholdReached::class]);
		Notification::fake();

		config(['ai-usage.budgets' => [
			'monthly' => ['period' => 'month', 'limit' => 10, 'thresholds' => [0.8, 1.0], 'mail' => ['admin@example.com']],
		]]);
	}

	public function test_a_threshold_alerts_once_per_period(): void
	{
		$this->spend(5);
		Event::assertNotDispatched(BudgetThresholdReached::class);

		$this->spend(3.5);
		$this->spend(0.5);

		Event::assertDispatchedTimes(BudgetThresholdReached::class, 1);
		Event::assertDispatched(BudgetThresholdReached::class, fn (BudgetThresholdReached $event): bool => $event->budget === 'monthly' && $event->threshold === 0.8 && $event->spent === 8.5);
		Notification::assertSentTimes(BudgetThresholdReachedNotification::class, 1);
		Notification::assertSentTo(new AnonymousNotifiable, BudgetThresholdReachedNotification::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['admin@example.com']);

		$this->spend(2);

		Event::assertDispatchedTimes(BudgetThresholdReached::class, 2);
		Event::assertDispatched(BudgetThresholdReached::class, fn (BudgetThresholdReached $event): bool => $event->threshold === 1.0);
	}

	public function test_spend_of_a_previous_period_does_not_count(): void
	{
		AiUsageRecord::factory()->create(['total_cost' => 50, 'created_at' => now()->subMonthNoOverflow()->startOfMonth()]);

		$this->spend(1);

		Event::assertNotDispatched(BudgetThresholdReached::class);
	}

	public function test_a_feature_budget_only_counts_that_feature(): void
	{
		config(['ai-usage.budgets' => ['links' => ['period' => 'day', 'limit' => 1, 'thresholds' => [1.0], 'feature' => 'link_analysis']]]);

		$this->spend(5, 'document_extraction');
		Event::assertNotDispatched(BudgetThresholdReached::class);

		$this->spend(1, 'link_analysis');
		Event::assertDispatched(BudgetThresholdReached::class, fn (BudgetThresholdReached $event): bool => $event->spent === 1.0 && $event->feature === 'link_analysis');
		Notification::assertNothingSent();
	}

	private function spend(float $cost, string $feature = 'document_extraction'): void
	{
		$record = AiUsageRecord::factory()->create(['total_cost' => $cost, 'feature' => $feature]);

		resolve(BudgetGuard::class)->evaluate($record);
	}
}
