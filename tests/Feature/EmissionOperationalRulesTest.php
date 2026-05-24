<?php

namespace Tests\Feature;

use App\Filament\Pages\EmissionQueue;
use App\Models\OrderEmission;
use App\Models\OrderEmissionLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class EmissionOperationalRulesTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_emission_queue_scopes_pending_and_assigned_orders_by_issuer(): void
    {
        $issuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $otherIssuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);

        $pendingOrder = $this->createOrder([
            'tracking_code' => 'VDP-PENDQ',
            'status' => 'awaiting_emission',
        ]);
        OrderEmission::create([
            'order_id' => $pendingOrder->id,
            'status' => 'pending',
        ]);

        $ownAssignedOrder = $this->createOrder([
            'tracking_code' => 'VDP-OWNQ',
            'status' => 'awaiting_emission',
        ]);
        OrderEmission::create([
            'order_id' => $ownAssignedOrder->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
        ]);

        $otherAssignedOrder = $this->createOrder([
            'tracking_code' => 'VDP-OTHERQ',
            'status' => 'awaiting_emission',
        ]);
        OrderEmission::create([
            'order_id' => $otherAssignedOrder->id,
            'issuer_id' => $otherIssuer->id,
            'status' => 'assigned',
        ]);

        $completedOrder = $this->createOrder([
            'tracking_code' => 'VDP-DONEQ',
            'status' => 'completed',
        ]);
        OrderEmission::create([
            'order_id' => $completedOrder->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Livewire::actingAs($issuer)
            ->test(EmissionQueue::class)
            ->assertSee('VDP-PENDQ')
            ->assertSee('VDP-OWNQ')
            ->assertDontSee('VDP-OTHERQ')
            ->assertDontSee('VDP-DONEQ');
    }

    public function test_emission_completion_requires_loc_and_miles_cost_for_every_flight(): void
    {
        $issuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $order = $this->createOrder(['status' => 'awaiting_emission']);
        $outbound = $this->addFlight($order, [
            'direction' => 'outbound',
            'unique_id' => 'emission-required-outbound',
        ]);
        $inbound = $this->addFlight($order, [
            'direction' => 'inbound',
            'unique_id' => 'emission-required-inbound',
        ]);
        $emission = OrderEmission::create([
            'order_id' => $order->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
            'assigned_at' => now()->subMinutes(10),
        ]);

        Livewire::actingAs($issuer)
            ->test(EmissionQueue::class)
            ->callTableAction('complete', $emission, [
                'loc_'.$outbound->id => 'ida123',
                'paid_boarding_tax_'.$outbound->id => '42.17',
                'miles_cost_'.$outbound->id => '26.50',
            ])
            ->assertHasTableActionErrors([
                'loc_'.$inbound->id,
                'miles_cost_'.$inbound->id,
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_emission',
            'loc' => null,
        ]);
        $this->assertDatabaseHas('order_emissions', [
            'id' => $emission->id,
            'status' => 'assigned',
        ]);
    }

    public function test_issuer_completes_roundtrip_emission_with_loc_and_paid_tax_per_flight(): void
    {
        Carbon::setTestNow('2026-05-17 10:30:00');
        Setting::set('emission_value_per_order', '19.90', 'string');

        $issuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $order = $this->createOrder([
            'tracking_code' => 'VDP-EMIT1',
            'status' => 'awaiting_emission',
        ]);
        $outbound = $this->addFlight($order, [
            'direction' => 'outbound',
            'unique_id' => 'emission-complete-outbound',
            'tax' => '50.00',
            'paid_boarding_tax' => null,
        ]);
        $inbound = $this->addFlight($order, [
            'direction' => 'inbound',
            'unique_id' => 'emission-complete-inbound',
            'tax' => '60.00',
            'paid_boarding_tax' => null,
        ]);
        $emission = OrderEmission::create([
            'order_id' => $order->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
            'assigned_at' => '2026-05-17 10:00:00',
        ]);

        Livewire::actingAs($issuer)
            ->test(EmissionQueue::class)
            ->callTableAction('complete', $emission, [
                'loc_'.$outbound->id => ' ida123 ',
                'paid_boarding_tax_'.$outbound->id => '51.23',
                'miles_cost_'.$outbound->id => '25.50',
                'loc_'.$inbound->id => 'vol456',
                'paid_boarding_tax_'.$inbound->id => '61.45',
                'miles_cost_'.$inbound->id => '31.40',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
            'loc' => 'IDA123 / VOL456',
        ]);
        $this->assertDatabaseHas('order_flights', [
            'id' => $outbound->id,
            'loc' => 'IDA123',
            'tax' => '50.00',
            'paid_boarding_tax' => 51.23,
        ]);
        $this->assertDatabaseHas('order_flights', [
            'id' => $inbound->id,
            'loc' => 'VOL456',
            'tax' => '60.00',
            'paid_boarding_tax' => 61.45,
        ]);
        $this->assertDatabaseHas('order_emissions', [
            'id' => $emission->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'duration_seconds' => 1800,
            'emission_value' => 19.90,
            'miles_cost_per_thousand' => 31.40,
        ]);

        $log = OrderEmissionLog::where('order_emission_id', $emission->id)
            ->where('action', 'completed')
            ->firstOrFail();

        $this->assertSame($issuer->id, $log->user_id);
        $this->assertStringContainsString('Ida: IDA123', $log->notes);
        $this->assertStringContainsString('Volta: VOL456', $log->notes);
        $this->assertStringContainsString('taxa R$ 51,23', $log->notes);
        $this->assertStringContainsString('taxa R$ 61,45', $log->notes);
    }
}
