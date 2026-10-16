<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Feature;

use Livewire\Livewire;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use SpaanProductions\LaravelAiUsage\Tests\TestCase;
use SpaanProductions\LaravelAiUsage\Livewire\Records;
use SpaanProductions\LaravelAiUsage\Livewire\Dashboard;
use SpaanProductions\LaravelAiUsage\Budgets\BudgetGuard;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Events\BudgetThresholdReached;

/**
 * Applications may use immutable dates (`Date::use(CarbonImmutable::class)`, as the Laravel
 * starter kits do), so `now()` is not an `Illuminate\Support\Carbon`.
 */
class ImmutableDatesTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		Date::use(CarbonImmutable::class);
	}

	protected function tearDown(): void
	{
		Date::useDefault();

		parent::tearDown();
	}

	public function test_the_pages_work_with_immutable_dates(): void
	{
		AiUsageRecord::factory()->create(['total_cost' => 1.5, 'created_at' => now()->subDays(2)]);

		$dashboard = Livewire::actingAs($this->createAdmin())->test(Dashboard::class)->assertOk()->assertSee('$1.50');

		foreach (['today', '30d', 'this_year'] as $range) {
			$dashboard->set('range', $range)->assertOk();
		}

		$this->assertCount(30, $dashboard->set('range', '30d')->viewData('chart')['bars']);

		Livewire::test(Records::class)->set('range', 'custom')->set('from', now()->subWeek()->toDateString())->assertOk()->assertSee('$1.50');
	}

	public function test_budgets_work_with_immutable_dates(): void
	{
		Event::fake([BudgetThresholdReached::class]);
		config(['ai-usage.budgets' => ['daily' => ['period' => 'day', 'limit' => 1]]]);

		AiUsageRecord::factory()->create(['total_cost' => 2]);
		resolve(BudgetGuard::class)->evaluate(AiUsageRecord::query()->sole());

		Event::assertDispatched(BudgetThresholdReached::class, fn (BudgetThresholdReached $event): bool => $event->periodStart instanceof CarbonImmutable);
	}
}
