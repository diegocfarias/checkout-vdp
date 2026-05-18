<?php

namespace Tests\Feature;

use App\Filament\Pages\EmissionOrderDetail;
use App\Filament\Pages\MyEmissions;
use App\Filament\Widgets\LatestPendingEmissions;
use App\Filament\Widgets\StatsOverview;
use App\Models\OrderEmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class FilamentOperationalCoverageTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_emission_order_detail_renders_infolist_and_copy_passengers_modal(): void
    {
        Carbon::setTestNow('2026-05-16 09:00:00');
        $issuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $search = $this->createFlightSearch([
            'departure_iata' => 'GRU',
            'arrival_iata' => 'REC',
            'outbound_date' => '2026-06-10',
            'inbound_date' => '2026-06-15',
            'trip_type' => 'roundtrip',
        ]);
        $order = $this->createOrder([
            'flight_search_id' => $search->id,
            'tracking_code' => 'VDP-DET1',
            'departure_iata' => 'GRU',
            'arrival_iata' => 'REC',
            'status' => 'awaiting_emission',
        ]);

        $this->addFlight($order, [
            'direction' => 'outbound',
            'cia' => 'LATAM',
            'operator' => 'LATAM',
            'flight_number' => 'LA1000',
            'departure_location' => 'GRU',
            'arrival_location' => 'REC',
            'departure_label' => 'Guarulhos (GRU)',
            'arrival_label' => 'Recife (REC)',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'price_miles' => '12000',
            'total_flight_duration' => '04:00',
            'connection' => [
                [
                    'DEPARTURE_TIME' => '08:00',
                    'ARRIVAL_TIME' => '09:30',
                    'DEPARTURE_LOCATION' => 'GRU',
                    'ARRIVAL_LOCATION' => 'BSB',
                    'FLIGHT_NUMBER' => 'LA1000',
                    'FLIGHT_DURATION' => '01:30',
                    'TIME_WAITING' => '01:00',
                ],
                [
                    'DEPARTURE_TIME' => '10:30',
                    'ARRIVAL_TIME' => '12:00',
                    'DEPARTURE_LOCATION' => 'BSB',
                    'ARRIVAL_LOCATION' => 'REC',
                    'FLIGHT_NUMBER' => 'LA2000',
                    'FLIGHT_DURATION' => '01:30',
                ],
            ],
        ]);
        $this->addFlight($order, [
            'direction' => 'inbound',
            'cia' => 'GOL',
            'operator' => 'GOL',
            'flight_number' => 'G31234',
            'departure_location' => 'REC',
            'arrival_location' => 'GRU',
            'departure_label' => 'Recife (REC)',
            'arrival_label' => 'Guarulhos (GRU)',
            'departure_time' => '14:00',
            'arrival_time' => '17:00',
            'price_miles' => '8000',
            'total_flight_duration' => '03:00',
            'connection' => null,
        ]);
        $this->addPassenger($order, [
            'full_name' => 'Passageiro Um',
            'nationality' => 'US',
            'document' => '529.982.247-25',
            'passport_number' => 'AB987654',
            'passport_expiry' => '2031-01-01',
            'birth_date' => '1988-03-04',
            'email' => 'um@example.com',
            'phone' => '31999990000',
        ]);
        $this->addPassenger($order, [
            'full_name' => 'Passageiro Dois',
            'nationality' => 'BR',
            'document' => 'RG123',
            'birth_date' => '1991-05-06',
            'email' => 'dois@example.com',
            'phone' => '31988887777',
        ]);
        OrderEmission::create([
            'order_id' => $order->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
        ]);

        Livewire::actingAs($issuer)
            ->test(EmissionOrderDetail::class, ['order' => $order->id])
            ->assertSee('VDP-DET1')
            ->assertSee('GRU')
            ->assertSee('REC')
            ->assertSee('Econômica')
            ->assertSee('20.000 milhas')
            ->assertSee('Card para Emissão')
            ->assertSee('1 conexão')
            ->assertSee('Total geral')
            ->assertSee('Passageiro Um')
            ->assertSee('AB987654')
            ->assertActionExists('copy_passengers')
            ->assertActionExists('back');

        $this->actingAs($issuer);
        $page = new class extends EmissionOrderDetail
        {
            public function exposeHeaderActions(): array
            {
                return $this->getHeaderActions();
            }
        };

        $this->assertSame('Detalhes da Emissão', $page->getTitle());
        $page->mount($order->id);
        $this->assertSame('Emissão — VDP-DET1', $page->getTitle());

        $copyPassengers = collect($page->exposeHeaderActions())
            ->firstOrFail(fn ($action) => $action->getName() === 'copy_passengers');
        $modalHtml = (string) $copyPassengers->getModalContent();

        $this->assertStringContainsString('Passageiro 1:', $modalHtml);
        $this->assertStringContainsString('Nome: PASSAGEIRO UM', $modalHtml);
        $this->assertStringContainsString('Nacionalidade: Estados Unidos', $modalHtml);
        $this->assertStringContainsString('CPF: 529.982.247-25', $modalHtml);
        $this->assertStringContainsString('Passaporte: AB987654', $modalHtml);
        $this->assertStringContainsString('Validade Passaporte: 01/01/2031', $modalHtml);
        $this->assertStringContainsString('Passageiro 2:', $modalHtml);
        $this->assertStringContainsString('Nome: PASSAGEIRO DOIS', $modalHtml);
    }

    public function test_emission_order_detail_allows_admin_and_blocks_other_issuers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $otherIssuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $order = $this->createOrder([
            'tracking_code' => 'VDP-SEC1',
            'status' => 'awaiting_emission',
        ]);
        $this->addFlight($order);
        $this->addPassenger($order);
        OrderEmission::create([
            'order_id' => $order->id,
            'issuer_id' => $owner->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($otherIssuer)
            ->get(route('filament.admin.pages.emission-order', ['order' => $order->id]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('filament.admin.pages.emission-order', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('VDP-SEC1');
    }

    public function test_my_emissions_filters_table_and_formats_duration_edges(): void
    {
        Carbon::setTestNow('2026-05-16 12:00:00');
        $issuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $otherIssuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);

        $fastOrder = $this->createOrder([
            'tracking_code' => 'VDP-MY1',
            'departure_iata' => 'CNF',
            'arrival_iata' => 'VIX',
        ]);
        OrderEmission::create([
            'order_id' => $fastOrder->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'completed_at' => '2026-05-10 10:00:00',
            'duration_seconds' => 45,
            'emission_value' => 10.10,
            'miles_cost_per_thousand' => 26.75,
        ]);

        $longOrder = $this->createOrder([
            'tracking_code' => 'VDP-MY2',
            'departure_iata' => 'VIX',
            'arrival_iata' => 'CNF',
        ]);
        OrderEmission::create([
            'order_id' => $longOrder->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'completed_at' => '2026-05-12 10:00:00',
            'duration_seconds' => 3665,
            'emission_value' => 20.20,
            'miles_cost_per_thousand' => 28.50,
        ]);

        $outsidePeriod = $this->createOrder(['tracking_code' => 'VDP-MY3']);
        OrderEmission::create([
            'order_id' => $outsidePeriod->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'completed_at' => '2026-04-30 10:00:00',
            'duration_seconds' => 120,
            'emission_value' => 99,
        ]);
        OrderEmission::create([
            'order_id' => $this->createOrder(['tracking_code' => 'VDP-OTHER'])->id,
            'issuer_id' => $otherIssuer->id,
            'status' => 'completed',
            'completed_at' => '2026-05-12 10:00:00',
            'duration_seconds' => 60,
            'emission_value' => 50,
        ]);
        OrderEmission::create([
            'order_id' => $this->createOrder(['tracking_code' => 'VDP-ASSIGNED'])->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($issuer);
        $page = new MyEmissions;
        $page->dateFrom = '2026-05-01';
        $page->dateTo = '2026-05-31';
        $stats = collect($page->getStats())->pluck('value', 'label');

        $this->assertSame(2, $stats['Emissões no período']);
        $this->assertSame('30min 55s', $stats['Tempo médio']);
        $this->assertSame('R$ 30,30', $stats['A receber']);
        $this->assertSame(1, $stats['Em andamento']);

        Livewire::actingAs($issuer)
            ->test(MyEmissions::class)
            ->assertSee('VDP-MY1')
            ->assertSee('VDP-MY2')
            ->assertSee('45s')
            ->assertSee('1h 1min')
            ->assertDontSee('VDP-MY3')
            ->assertDontSee('VDP-OTHER')
            ->assertDontSee('VDP-ASSIGNED')
            ->set('dateFrom', '2026-05-12')
            ->set('dateTo', '2026-05-12')
            ->assertDontSee('VDP-MY1')
            ->assertSee('VDP-MY2');
    }

    public function test_latest_pending_emissions_widget_lists_orders_and_marks_them_completed(): void
    {
        Carbon::setTestNow('2026-05-16 13:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $pending = $this->createOrder([
            'tracking_code' => 'VDP-PEND1',
            'departure_iata' => 'SDU',
            'arrival_iata' => 'CNF',
            'status' => 'awaiting_emission',
            'paid_at' => '2026-05-16 12:30:00',
        ]);
        $this->addPassenger($pending, ['full_name' => 'Cliente Pendente']);
        $completed = $this->createOrder([
            'tracking_code' => 'VDP-DONE1',
            'status' => 'completed',
            'paid_at' => '2026-05-16 11:00:00',
        ]);
        $this->addPassenger($completed, ['full_name' => 'Cliente Emitido']);

        Livewire::actingAs($admin)
            ->test(LatestPendingEmissions::class)
            ->assertSee('Emissões Pendentes')
            ->assertSee('VDP-PEND1')
            ->assertSee('SDU')
            ->assertSee('CNF')
            ->assertSee('Cliente Pendente')
            ->assertDontSee('VDP-DONE1')
            ->assertTableActionVisible('view', $pending)
            ->assertTableActionVisible('mark_completed', $pending)
            ->callTableAction('mark_completed', $pending);

        $this->assertDatabaseHas('orders', [
            'id' => $pending->id,
            'status' => 'completed',
        ]);
    }

    public function test_stats_overview_counts_admin_metrics(): void
    {
        Carbon::setTestNow('2026-05-16 14:00:00');
        User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->createOrder([
            'status' => 'awaiting_emission',
            'paid_at' => '2026-05-16 08:00:00',
        ]);
        $this->createOrder([
            'status' => 'awaiting_emission',
            'paid_at' => '2026-05-15 08:00:00',
        ]);
        $this->createOrder(['status' => 'cancelled']);
        $this->createOrder(['status' => 'completed']);

        $widget = new class extends StatsOverview
        {
            public function exposeStats(): array
            {
                return $this->getStats();
            }
        };

        $stats = $widget->exposeStats();

        $this->assertSame('Emissões Pendentes', $stats[0]->getLabel());
        $this->assertSame(2, $stats[0]->getValue());
        $this->assertSame('Aguardando emissão', $stats[0]->getDescription());
        $this->assertSame('Pagos Hoje', $stats[1]->getLabel());
        $this->assertSame(1, $stats[1]->getValue());
        $this->assertSame('Total de Pedidos', $stats[2]->getLabel());
        $this->assertSame(4, $stats[2]->getValue());
        $this->assertSame('Cancelados', $stats[3]->getLabel());
        $this->assertSame(1, $stats[3]->getValue());
    }
}
