<?php

namespace Tests\Unit;

use App\Services\CancellationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class CancellationPolicyServiceTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_paid_order_inside_24_hours_and_seven_days_is_priority(): void
    {
        Carbon::setTestNow('2026-05-24 10:00:00');

        $search = $this->createFlightSearch(['outbound_date' => '2026-06-05']);
        $order = $this->createOrder([
            'flight_search_id' => $search->id,
            'status' => 'awaiting_emission',
            'paid_at' => now()->subHours(2),
        ]);

        $policy = app(CancellationPolicyService::class)->evaluate($order, 'regret_24h');

        $this->assertTrue($policy['within_policy']);
        $this->assertTrue($policy['free_cancellation_window']);
        $this->assertSame('urgent', $policy['priority']);
    }

    public function test_paid_order_outside_automatic_window_requires_operational_analysis(): void
    {
        Carbon::setTestNow('2026-05-24 10:00:00');

        $search = $this->createFlightSearch(['outbound_date' => '2026-05-29']);
        $order = $this->createOrder([
            'flight_search_id' => $search->id,
            'status' => 'completed',
            'paid_at' => now()->subDays(2),
        ]);

        $policy = app(CancellationPolicyService::class)->evaluate($order, 'medical_or_personal');

        $this->assertFalse($policy['within_policy']);
        $this->assertFalse($policy['free_cancellation_window']);
        $this->assertSame('normal', $policy['priority']);
        $this->assertSame('Fora do prazo de cancelamento sem custo: cancelamentos voluntarios nao geram reembolso.', $policy['rule']);
    }

    public function test_unpaid_and_involuntary_requests_are_priority(): void
    {
        Carbon::setTestNow('2026-05-24 10:00:00');

        $unpaidOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $unpaidPolicy = app(CancellationPolicyService::class)->evaluate($unpaidOrder, 'wrong_data');

        $this->assertTrue($unpaidPolicy['within_policy']);
        $this->assertTrue($unpaidPolicy['without_confirmed_payment']);

        $search = $this->createFlightSearch(['outbound_date' => '2026-05-27']);
        $paidOrder = $this->createOrder([
            'flight_search_id' => $search->id,
            'status' => 'completed',
            'paid_at' => now()->subDays(3),
        ]);

        $involuntaryPolicy = app(CancellationPolicyService::class)->evaluate($paidOrder, 'schedule_change');

        $this->assertTrue($involuntaryPolicy['within_policy']);
        $this->assertTrue($involuntaryPolicy['involuntary_reason']);
        $this->assertSame('urgent', $involuntaryPolicy['priority']);
    }
}
