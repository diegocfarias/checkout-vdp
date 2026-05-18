<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Filament\Pages\PaidOrdersDashboard;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAuditLog;
use App\Models\CustomerChangeRequest;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class FilamentFinancialCustomerCoverageTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_paid_orders_dashboard_renders_metrics_filters_and_table_columns(): void
    {
        Carbon::setTestNow('2026-05-16 16:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $issuer = User::factory()->create([
            'name' => 'Emissor Financeiro',
            'role' => 'issuer',
            'is_active' => true,
        ]);
        $customer = $this->createCustomer(['name' => 'Cliente Financeiro']);
        $coupon = Coupon::create([
            'code' => 'FIN10',
            'type' => 'fixed',
            'value' => 20,
            'active' => true,
        ]);

        $mainOrder = $this->createOrder([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'tracking_code' => 'VDP-FIN1',
            'status' => 'completed',
            'total_adults' => 1,
            'total_children' => 1,
            'discount_amount' => 20,
            'wallet_amount_used' => 5,
            'departure_iata' => 'GRU',
            'arrival_iata' => 'REC',
            'device_type' => 'mobile',
            'paid_at' => '2026-05-16 10:00:00',
        ]);
        $this->addFlight($mainOrder, [
            'direction' => 'outbound',
            'cia' => 'AZUL',
            'money_price' => '100.00',
            'tax' => '20.00',
            'paid_boarding_tax' => 18,
            'price_miles' => '10000',
        ]);
        $this->addFlight($mainOrder, [
            'direction' => 'inbound',
            'cia' => 'GOL',
            'money_price' => '80.00',
            'tax' => '15.00',
            'price_miles' => '5000',
        ]);
        $this->addPayment($mainOrder, [
            'gateway' => 'c6bank',
            'payment_method' => 'credit_card',
            'status' => 'paid',
            'amount' => 405,
            'paid_at' => '2026-05-16 10:00:00',
        ]);
        $mainOrder->emission()->create([
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'emission_value' => 12,
            'miles_cost_per_thousand' => 10,
            'completed_at' => '2026-05-16 12:00:00',
        ]);

        $fallbackOrder = $this->createOrder([
            'tracking_code' => 'VDP-FIN2',
            'status' => 'awaiting_emission',
            'departure_iata' => 'CNF',
            'arrival_iata' => 'VIX',
            'device_type' => 'desktop',
            'paid_at' => '2026-05-16 11:00:00',
        ]);
        $this->addFlight($fallbackOrder, [
            'cia' => 'LATAM',
            'money_price' => '200.00',
            'tax' => '50.00',
        ]);

        Livewire::actingAs($admin)
            ->test(PaidOrdersDashboard::class)
            ->assertSee('Dashboard Financeiro')
            ->assertSee('Pedidos pagos')
            ->assertSee('Receita capturada')
            ->assertSee('Evolução financeira')
            ->assertSee('Mix de pagamentos')
            ->assertSee('Top rotas')
            ->assertSee('Cupons')
            ->assertSee('Emissores')
            ->assertSee('VDP-FIN1')
            ->assertSee('Cliente Financeiro')
            ->assertSee('FIN10')
            ->assertSee('Emissor Financeiro')
            ->assertTableColumnStateSet('route', 'GRU -> REC', $mainOrder)
            ->assertTableColumnStateSet('payment_method', 'Cartão', $mainOrder)
            ->assertTableColumnStateSet('external_revenue', 'R$ 405,00', $mainOrder)
            ->assertTableColumnStateSet('gmv', 'R$ 410,00', $mainOrder)
            ->assertTableColumnStateSet('total_cost', 'R$ 195,00', $mainOrder)
            ->assertTableColumnStateSet('margin', 'R$ 215,00', $mainOrder)
            ->assertTableColumnFormattedStateSet('status', 'Emitido', $mainOrder)
            ->assertTableColumnStateSet('paid_at', '16/05/2026 10:00', $mainOrder)
            ->set('airline', 'AZUL')
            ->assertCanSeeTableRecords([$mainOrder])
            ->assertCanNotSeeTableRecords([$fallbackOrder])
            ->set('airline', 'all')
            ->set('coupon', 'without_coupon')
            ->assertCanSeeTableRecords([$fallbackOrder])
            ->assertCanNotSeeTableRecords([$mainOrder]);

        $page = new PaidOrdersDashboard;
        $page->updatedDateFrom();
        $page->updatedDateTo();
        $page->updatedPaymentMethod();
        $page->updatedGateway();
        $page->updatedOrderStatus();
        $page->updatedAirline();
        $page->updatedCoupon();
        $page->updatedIssuerId();
        $page->updatedDeviceType();
    }

    public function test_customer_resource_table_actions_and_view_infolist_cover_affiliate_history(): void
    {
        Carbon::setTestNow('2026-05-16 17:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = $this->createCustomer([
            'name' => 'Cliente Lista',
            'email' => 'lista@example.com',
            'document' => '11122233344',
            'google_id' => 'google-123',
            'status' => 'active',
            'is_affiliate' => false,
        ]);
        $pendingCustomer = $this->createCustomer([
            'name' => 'Cliente Pendente',
            'email' => 'pendente@example.com',
            'status' => 'pending',
        ]);
        Coupon::create([
            'code' => 'TAKEN',
            'type' => 'fixed',
            'value' => 10,
            'active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ListCustomers::class)
            ->assertSee('Cliente Lista')
            ->assertSee('Cliente Pendente')
            ->assertTableColumnFormattedStateSet('status', 'Ativo', $customer)
            ->assertTableColumnStateSet('google_id', 'Sim', $customer)
            ->assertTableColumnStateSet('is_affiliate', '—', $customer)
            ->assertTableColumnFormattedStateSet('status', 'Pendente', $pendingCustomer)
            ->assertTableColumnStateSet('google_id', 'Não', $pendingCustomer)
            ->callTableAction('edit_sensitive', $customer, [
                'email' => 'lista-nova@example.com',
                'document' => '529.982.247-25',
            ])
            ->assertHasNoTableActionErrors()
            ->callTableAction('manage_affiliate', $customer, [
                'is_affiliate' => true,
                'code_mode' => 'manual',
                'custom_referral_code' => 'lista-vip',
                'affiliate_discount_pct' => '6.5',
                'affiliate_credit_pct' => '3.5',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => 'lista-nova@example.com',
            'document' => '52998224725',
            'is_affiliate' => true,
            'referral_code' => 'LISTA-VIP',
        ]);

        $blocked = $this->createCustomer([
            'name' => 'Cliente Bloqueado',
            'email' => 'bloqueado@example.com',
            'is_affiliate' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ListCustomers::class)
            ->callTableAction('manage_affiliate', $blocked, [
                'is_affiliate' => true,
                'code_mode' => 'manual',
                'custom_referral_code' => 'TAKEN',
                'affiliate_discount_pct' => null,
                'affiliate_credit_pct' => null,
            ])
            ->assertHasTableActionErrors(['custom_referral_code']);

        $affiliate = $customer->fresh();
        WalletTransaction::create([
            'customer_id' => $affiliate->id,
            'type' => 'credit',
            'amount' => 100,
            'balance_after' => 100,
            'description' => 'Crédito liberado',
        ]);
        WalletTransaction::create([
            'customer_id' => $affiliate->id,
            'type' => 'debit',
            'amount' => 15,
            'balance_after' => 85,
            'description' => 'Crédito usado',
        ]);
        $order = $this->createOrder([
            'customer_id' => $affiliate->id,
            'tracking_code' => 'VDP-CUST1',
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'status' => 'awaiting_payment',
        ]);
        CustomerChangeRequest::create([
            'customer_id' => $affiliate->id,
            'field' => 'document',
            'current_value' => '52998224725',
            'requested_value' => '11144477735',
            'reason' => 'Correção cadastral',
            'status' => 'approved',
            'admin_id' => $admin->id,
            'resolved_at' => '2026-05-16 17:30:00',
        ]);
        CustomerAuditLog::create([
            'customer_id' => $affiliate->id,
            'field' => 'email',
            'old_value' => 'lista@example.com',
            'new_value' => 'lista-nova@example.com',
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'created_at' => '2026-05-16 17:10:00',
        ]);
        CustomerAuditLog::create([
            'customer_id' => $affiliate->id,
            'field' => 'phone',
            'old_value' => null,
            'new_value' => '31999990000',
            'actor_type' => 'customer',
            'actor_id' => $affiliate->id,
            'created_at' => '2026-05-16 17:20:00',
        ]);
        CustomerAuditLog::create([
            'customer_id' => $affiliate->id,
            'field' => 'status',
            'old_value' => 'pending',
            'new_value' => 'active',
            'actor_type' => 'system',
            'created_at' => '2026-05-16 17:25:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewCustomer::class, ['record' => $affiliate->getRouteKey()])
            ->assertSee('Cliente Lista')
            ->assertSee('lista-nova@example.com')
            ->assertSee('LISTA-VIP')
            ->assertSee('6.50')
            ->assertSee('3.50')
            ->assertSee('R$ 85,00')
            ->assertSee('VDP-CUST1')
            ->assertSee('GRU')
            ->assertSee('SDU')
            ->assertSee('Aguardando Pgto')
            ->assertSee('Solicitações de alteração')
            ->assertSee('Aprovada')
            ->assertSee('Histórico de auditoria')
            ->assertSee('Admin')
            ->assertSee('Cliente')
            ->assertSee('Sistema');

        $this->assertFalse(CustomerResource::canCreate());
    }

    public function test_order_view_header_actions_cover_manual_payment_emission_refund_cancel_and_copy_modal(): void
    {
        Carbon::setTestNow('2026-05-16 18:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $awaitingPayment = $this->createOrder([
            'tracking_code' => 'VDP-ACT1',
            'status' => 'awaiting_payment',
        ]);
        $awaitingPaymentPayment = $this->addPayment($awaitingPayment, ['status' => 'pending']);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $awaitingPayment->getRouteKey()])
            ->assertActionVisible('mark_paid')
            ->callAction('mark_paid');

        $this->assertDatabaseHas('orders', [
            'id' => $awaitingPayment->id,
            'status' => 'awaiting_emission',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'id' => $awaitingPaymentPayment->id,
            'status' => 'paid',
        ]);

        $toComplete = $this->createOrder([
            'tracking_code' => 'VDP-ACT2',
            'status' => 'awaiting_emission',
        ]);
        $outbound = $this->addFlight($toComplete, ['direction' => 'outbound', 'cia' => 'AZUL']);
        $inbound = $this->addFlight($toComplete, ['direction' => 'inbound', 'cia' => 'GOL']);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $toComplete->getRouteKey()])
            ->assertActionVisible('mark_completed')
            ->callAction('mark_completed', [
                'loc_'.$outbound->id => ' azul12 ',
                'loc_'.$inbound->id => 'gol345',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $toComplete->id,
            'status' => 'completed',
            'loc' => 'AZUL12 / GOL345',
        ]);
        $this->assertDatabaseHas('order_flights', ['id' => $outbound->id, 'loc' => 'AZUL12']);
        $this->assertDatabaseHas('order_flights', ['id' => $inbound->id, 'loc' => 'GOL345']);

        $toCancel = $this->createOrder([
            'tracking_code' => 'VDP-ACT3',
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $toCancel->getRouteKey()])
            ->assertActionVisible('cancel')
            ->callAction('cancel');

        $this->assertDatabaseHas('orders', [
            'id' => $toCancel->id,
            'status' => 'cancelled',
        ]);

        $withoutPayment = $this->createOrder([
            'tracking_code' => 'VDP-ACT4',
            'status' => 'completed',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $withoutPayment->getRouteKey()])
            ->assertActionVisible('refund')
            ->callAction('refund');

        $this->assertDatabaseHas('orders', [
            'id' => $withoutPayment->id,
            'status' => 'completed',
        ]);

        $refundFails = $this->createOrder([
            'tracking_code' => 'VDP-ACT5',
            'status' => 'completed',
        ]);
        $failedPayment = $this->addPayment($refundFails, [
            'gateway' => 'appmax',
            'status' => 'paid',
            'amount' => 123,
            'paid_at' => now(),
        ]);
        $this->bindRefundingGateway(false);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $refundFails->getRouteKey()])
            ->assertActionVisible('refund')
            ->callAction('refund');

        $this->assertDatabaseHas('orders', [
            'id' => $refundFails->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'id' => $failedPayment->id,
            'status' => 'paid',
        ]);

        $refundSucceeds = $this->createOrder([
            'tracking_code' => 'VDP-ACT6',
            'status' => 'awaiting_emission',
        ]);
        $refundedPayment = $this->addPayment($refundSucceeds, [
            'gateway' => 'c6bank',
            'status' => 'paid',
            'amount' => 456,
            'paid_at' => now(),
        ]);
        $this->bindRefundingGateway(true);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $refundSucceeds->getRouteKey()])
            ->assertActionVisible('refund')
            ->callAction('refund');

        $this->assertDatabaseHas('orders', [
            'id' => $refundSucceeds->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'id' => $refundedPayment->id,
            'status' => 'refunded',
        ]);

        $copyOrder = $this->createOrder([
            'tracking_code' => 'VDP-ACT7',
            'status' => 'completed',
        ]);
        $this->addPassenger($copyOrder, [
            'full_name' => 'Passageiro Modal',
            'nationality' => 'PT',
            'document' => '52998224725',
            'passport_number' => 'PT123456',
            'passport_expiry' => '2030-05-01',
            'birth_date' => '1990-02-03',
            'email' => 'modal@example.com',
            'phone' => '31977776666',
        ]);

        $page = new class extends ViewOrder
        {
            public function exposeHeaderActions(): array
            {
                return $this->getHeaderActions();
            }
        };
        $page->record = $copyOrder->fresh(['passengers']);

        $copyPassengers = collect($page->exposeHeaderActions())
            ->firstOrFail(fn ($action) => $action->getName() === 'copy_passengers');
        $modalHtml = (string) $copyPassengers->getModalContent();

        $this->assertStringContainsString('Passageiro 1:', $modalHtml);
        $this->assertStringContainsString('Nome: PASSAGEIRO MODAL', $modalHtml);
        $this->assertStringContainsString('Nacionalidade: Portugal', $modalHtml);
        $this->assertStringContainsString('CPF: 529.982.247-25', $modalHtml);
        $this->assertStringContainsString('Passaporte: PT123456', $modalHtml);
        $this->assertStringContainsString('Validade Passaporte: 01/05/2030', $modalHtml);
    }

    private function bindRefundingGateway(bool $result): void
    {
        $gateway = new class($result) implements PaymentGatewayInterface
        {
            public function __construct(private bool $result) {}

            public function createCheckout(Order $order, ?string $paymentMethod = null, ?array $cardData = null): OrderPayment
            {
                return new OrderPayment;
            }

            public function getCheckoutStatus(OrderPayment $payment): string
            {
                return 'paid';
            }

            public function cancelCheckout(OrderPayment $payment): void {}

            public function refundPayment(OrderPayment $payment): bool
            {
                return $this->result;
            }
        };

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldReceive('resolveForPayment')->once()->andReturn($gateway);

        $this->app->instance(PaymentGatewayResolver::class, $resolver);
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
}
