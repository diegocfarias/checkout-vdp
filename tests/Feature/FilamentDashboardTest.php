<?php

namespace Tests\Feature;

use App\Filament\Pages\EmissionDashboard;
use App\Filament\Pages\MyEmissions;
use App\Filament\Pages\PaidOrdersDashboard;
use App\Filament\Pages\ReferralDashboard;
use App\Filament\Pages\SupportDashboard;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\OrderEmission;
use App\Models\Referral;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class FilamentDashboardTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_emission_dashboard_summarizes_queue_and_issuer_ranking(): void
    {
        Carbon::setTestNow('2026-05-14 12:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $issuer = User::factory()->create(['name' => 'Emissor Um', 'role' => 'issuer', 'is_active' => true]);

        OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'status' => 'pending',
        ]);
        OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
        ]);
        OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'completed_at' => '2026-05-10 10:00:00',
            'duration_seconds' => 90,
            'emission_value' => 15,
        ]);
        OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'completed_at' => '2026-04-10 10:00:00',
            'duration_seconds' => 999,
            'emission_value' => 99,
        ]);

        $this->actingAs($admin);
        $page = new EmissionDashboard;
        $page->dateFrom = '2026-05-01';
        $page->dateTo = '2026-05-31';

        $stats = collect($page->getStats())->pluck('value', 'label');

        $this->assertSame(1, $stats['Pendentes agora']);
        $this->assertSame(1, $stats['Em andamento']);
        $this->assertSame(1, $stats['Concluídas no período']);
        $this->assertSame('1min 30s', $stats['Tempo médio']);
        $this->assertSame(1, $stats['Emissores ativos']);

        $ranking = $page->getRanking();
        $this->assertSame('Emissor Um', $ranking[0]['name']);
        $this->assertSame(1, $ranking[0]['count']);
        $this->assertSame('1min 30s', $ranking[0]['avg_time']);
        $this->assertSame('R$ 15,00', $ranking[0]['total_value']);
    }

    public function test_my_emissions_dashboard_only_counts_authenticated_issuer(): void
    {
        Carbon::setTestNow('2026-05-14 12:00:00');
        $issuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $otherIssuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);

        OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'completed_at' => '2026-05-09 14:00:00',
            'duration_seconds' => 125,
            'emission_value' => 12.5,
        ]);
        OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
        ]);
        OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'issuer_id' => $otherIssuer->id,
            'status' => 'completed',
            'completed_at' => '2026-05-09 14:00:00',
            'duration_seconds' => 300,
            'emission_value' => 99,
        ]);

        $this->actingAs($issuer);
        $page = new MyEmissions;
        $page->dateFrom = '2026-05-01';
        $page->dateTo = '2026-05-31';

        $stats = collect($page->getStats())->pluck('value', 'label');

        $this->assertSame(1, $stats['Emissões no período']);
        $this->assertSame('2min 5s', $stats['Tempo médio']);
        $this->assertSame('R$ 12,50', $stats['A receber']);
        $this->assertSame(1, $stats['Em andamento']);
    }

    public function test_support_dashboard_calculates_stats_and_agent_ranking_without_database_specific_sql(): void
    {
        Carbon::setTestNow('2026-05-14 12:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $agent = User::factory()->create(['name' => 'Atendente Um', 'role' => 'support', 'is_active' => true]);
        $customer = $this->createCustomer();

        SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'status' => 'open',
            'priority' => 'normal',
            'message' => 'Aberto',
        ])->forceFill([
            'created_at' => '2026-05-10 09:00:00',
            'updated_at' => '2026-05-10 09:00:00',
        ])->save();
        $inProgress = SupportTicket::create([
            'customer_id' => $customer->id,
            'assigned_to' => $agent->id,
            'subject' => 'payment_issue',
            'status' => 'in_progress',
            'priority' => 'normal',
            'message' => 'Em atendimento',
            'first_response_at' => '2026-05-10 10:15:00',
        ]);
        $inProgress->forceFill([
            'created_at' => '2026-05-10 10:00:00',
            'updated_at' => '2026-05-10 10:00:00',
        ])->save();
        $resolved = SupportTicket::create([
            'customer_id' => $customer->id,
            'assigned_to' => $agent->id,
            'subject' => 'refund',
            'status' => 'resolved',
            'priority' => 'normal',
            'message' => 'Resolvido',
            'first_response_at' => '2026-05-10 10:20:00',
            'resolved_at' => '2026-05-10 12:30:00',
        ]);
        $resolved->forceFill([
            'created_at' => '2026-05-10 10:00:00',
            'updated_at' => '2026-05-10 10:00:00',
        ])->save();

        $this->createSupportMessage($inProgress, $agent, '2026-05-10 10:15:00');
        $this->createSupportMessage($resolved, $agent, '2026-05-10 10:20:00');

        $this->actingAs($admin);
        $page = new SupportDashboard;
        $page->dateFrom = '2026-05-01';
        $page->dateTo = '2026-05-31';

        $stats = collect($page->getStats())->pluck('value', 'label');

        $this->assertSame(2, $stats['Abertos agora']);
        $this->assertSame(2, $stats['Aguardando resposta']);
        $this->assertSame(3, $stats['Total no período']);
        $this->assertSame('17min', $stats['Tempo médio 1ª resposta']);
        $this->assertSame('2h 30min', $stats['Tempo médio resolução']);
        $this->assertSame('33%', $stats['Taxa de resolução']);

        $ranking = $page->getRanking();
        $this->assertSame('Atendente Um', $ranking[0]['name']);
        $this->assertSame(2, $ranking[0]['assigned']);
        $this->assertSame(1, $ranking[0]['resolved']);
        $this->assertSame(1, $ranking[0]['open']);
        $this->assertSame('17min', $ranking[0]['avg_response']);
    }

    public function test_referral_dashboard_summarizes_affiliates_wallet_and_ranking(): void
    {
        Carbon::setTestNow('2026-05-10 10:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $affiliate = $this->createCustomer([
            'name' => 'Afiliado Um',
            'is_affiliate' => true,
            'referral_code' => 'IND-UM',
        ]);
        $this->createCustomer([
            'name' => 'Afiliado Dois',
            'is_affiliate' => true,
            'referral_code' => 'IND-DOIS',
        ]);

        $pendingReferral = $this->createReferral($affiliate, [
            'order_base_total' => 1000,
            'discount_amount' => 100,
            'credit_amount' => 50,
            'credit_status' => 'pending',
        ]);
        $availableReferral = $this->createReferral($affiliate, [
            'order_base_total' => 500,
            'discount_amount' => 50,
            'credit_amount' => 25,
            'credit_status' => 'available',
        ]);
        Carbon::setTestNow('2026-04-10 10:00:00');
        $this->createReferral($affiliate, [
            'order_base_total' => 999,
            'discount_amount' => 99,
            'credit_amount' => 9,
        ]);
        Carbon::setTestNow('2026-05-10 10:00:00');

        WalletTransaction::create([
            'customer_id' => $affiliate->id,
            'type' => 'credit',
            'amount' => 25,
            'balance_after' => 25,
            'description' => 'Crédito liberado',
            'referral_id' => $availableReferral->id,
        ]);
        WalletTransaction::create([
            'customer_id' => $affiliate->id,
            'type' => 'debit',
            'amount' => 10,
            'balance_after' => 15,
            'description' => 'Crédito usado',
            'referral_id' => $pendingReferral->id,
        ]);

        $this->actingAs($admin);
        $page = new ReferralDashboard;
        $page->dateFrom = '2026-05-01';
        $page->dateTo = '2026-05-31';

        $stats = collect($page->getStats())->pluck('value', 'label');

        $this->assertSame(2, $stats['Afiliados ativos']);
        $this->assertSame(2, $stats['Indicações no período']);
        $this->assertSame('R$ 1.500,00', $stats['GMV por indicações']);
        $this->assertSame('R$ 150,00', $stats['Desconto concedido']);
        $this->assertSame('R$ 75,00', $stats['Crédito gerado']);
        $this->assertSame('R$ 25,00', $stats['Crédito liberado']);
        $this->assertSame('R$ 10,00', $stats['Crédito usado']);
        $this->assertSame('R$ 59,00', $stats['Saldo pendente']);

        $ranking = $page->getRanking();
        $this->assertSame('Afiliado Um', $ranking[0]['name']);
        $this->assertSame('IND-UM', $ranking[0]['referral_code']);
        $this->assertSame(2, $ranking[0]['count']);
        $this->assertSame('R$ 1.500,00', $ranking[0]['gmv']);
        $this->assertSame('R$ 75,00', $ranking[0]['credits']);
    }

    public function test_paid_orders_dashboard_filter_options_and_normalizers(): void
    {
        Carbon::setTestNow('2026-05-14 12:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $issuer = User::factory()->create(['name' => 'Emissor Financeiro', 'role' => 'issuer', 'is_active' => true]);
        $coupon = Coupon::create([
            'code' => 'voe10',
            'type' => 'fixed',
            'value' => 10,
            'active' => true,
        ]);
        $order = $this->createOrder([
            'coupon_id' => $coupon->id,
            'status' => 'awaiting_emission',
            'paid_at' => '2026-05-10 10:00:00',
            'device_type' => 'mobile',
        ]);
        $this->addFlight($order, ['cia' => 'azul']);
        $this->addPayment($order, [
            'gateway' => 'appmax',
            'payment_method' => 'pix',
            'status' => 'paid',
            'paid_at' => '2026-05-10 10:00:00',
        ]);
        OrderEmission::create([
            'order_id' => $order->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'completed_at' => '2026-05-10 12:00:00',
        ]);

        $this->actingAs($admin);
        $page = new PaidOrdersDashboard;
        $page->mount();

        $this->assertSame('Pix', $page->getPaymentMethodOptions()['pix']);
        $this->assertSame('Appmax', $page->getGatewayOptions()['appmax']);
        $this->assertSame('AZUL', $page->getAirlineOptions()['azul']);
        $this->assertSame('VOE10', $page->getCouponOptions()[(string) $coupon->id]);
        $this->assertSame('Emissor Financeiro', $page->getIssuerOptions()[(string) $issuer->id]);
        $this->assertSame('Mobile', $page->getDeviceOptions()['mobile']);

        $page->departureIata = 'gru';
        $page->arrivalIata = 'sdu';
        $page->updatedDepartureIata();
        $page->updatedArrivalIata();

        $this->assertSame('GRU', $page->departureIata);
        $this->assertSame('SDU', $page->arrivalIata);
        $this->assertSame('R$ 1.234,56', $page->money(1234.56));
        $this->assertSame('12,3%', $page->percent(12.345));

        $page->paymentMethod = 'pix';
        $page->gateway = 'appmax';
        $page->orderStatus = 'completed';
        $page->airline = 'azul';
        $page->coupon = (string) $coupon->id;
        $page->issuerId = (string) $issuer->id;
        $page->deviceType = 'mobile';
        $page->resetFilters();

        $this->assertSame('all', $page->paymentMethod);
        $this->assertSame('all', $page->gateway);
        $this->assertSame('all', $page->airline);
        $this->assertNull($page->departureIata);
        $this->assertNull($page->arrivalIata);
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Cliente Teste',
            'email' => fake()->unique()->safeEmail(),
            'document' => '52998224725',
            'status' => 'active',
            'is_affiliate' => false,
        ], $attributes));
    }

    private function createReferral(Customer $affiliate, array $attributes = []): Referral
    {
        return Referral::create(array_merge([
            'affiliate_id' => $affiliate->id,
            'referred_order_id' => $this->createOrder()->id,
            'referred_document' => '11122233344',
            'referral_code_used' => $affiliate->referral_code,
            'order_base_total' => 100,
            'discount_pct' => 10,
            'discount_amount' => 10,
            'credit_pct' => 5,
            'credit_amount' => 5,
            'credit_status' => 'pending',
            'credit_available_at' => now(),
            'status' => 'active',
        ], $attributes));
    }

    private function createSupportMessage(SupportTicket $ticket, User $agent, string $createdAt): void
    {
        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'message' => 'Resposta do atendimento',
        ]);

        $message->forceFill([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ])->save();
    }
}
