<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Filament\Pages\EmissionQueue;
use App\Filament\Resources\ChangeRequestResource\Pages\ViewChangeRequest;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use App\Jobs\NotifyIssuersNewEmission;
use App\Mail\SupportTicketReplyMail;
use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use App\Models\Order;
use App\Models\OrderEmission;
use App\Models\OrderPayment;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class FilamentActionTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.botpress.webhook_url', null);
        Setting::clearCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_order_table_actions_confirm_payment_complete_cancel_and_refund(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $awaitingPayment = $this->createOrder(['status' => 'awaiting_payment']);
        $this->addPayment($awaitingPayment, ['status' => 'pending']);

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->callTableAction('mark_paid', $awaitingPayment);

        $this->assertDatabaseHas('orders', [
            'id' => $awaitingPayment->id,
            'status' => 'awaiting_emission',
        ]);
        $this->assertTrue($awaitingPayment->fresh()->paid_at->equalTo(Carbon::parse('2026-05-15 10:00:00')));
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $awaitingPayment->id,
            'status' => 'paid',
        ]);

        $toComplete = $this->createOrder(['status' => 'awaiting_emission']);
        $outbound = $this->addFlight($toComplete, ['direction' => 'outbound', 'unique_id' => 'outbound-action']);
        $inbound = $this->addFlight($toComplete, ['direction' => 'inbound', 'unique_id' => 'inbound-action']);

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->callTableAction('mark_completed', $toComplete, [
                'loc_'.$outbound->id => ' abc123 ',
                'loc_'.$inbound->id => 'def456',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $toComplete->id,
            'status' => 'completed',
            'loc' => 'ABC123 / DEF456',
        ]);
        $this->assertDatabaseHas('order_flights', ['id' => $outbound->id, 'loc' => 'ABC123']);
        $this->assertDatabaseHas('order_flights', ['id' => $inbound->id, 'loc' => 'DEF456']);

        $toCancel = $this->createOrder(['status' => 'pending']);

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->callTableAction('cancel', $toCancel);

        $this->assertDatabaseHas('orders', [
            'id' => $toCancel->id,
            'status' => 'cancelled',
        ]);

        $toRefund = $this->createOrder(['status' => 'completed']);
        $payment = $this->addPayment($toRefund, [
            'gateway' => 'appmax',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $this->bindRefundingGateway(true);

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->callTableAction('refund', $toRefund);

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'status' => 'refunded',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $toRefund->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_emission_queue_actions_move_emissions_through_the_operational_flow(): void
    {
        Carbon::setTestNow('2026-05-15 11:00:00');
        Bus::fake();
        Setting::set('emission_value_per_order', '22.50', 'string');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $issuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $newIssuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);

        $pending = OrderEmission::create([
            'order_id' => $this->createOrder(['status' => 'awaiting_emission'])->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($issuer)
            ->test(EmissionQueue::class)
            ->callTableAction('claim', $pending);

        $this->assertDatabaseHas('order_emissions', [
            'id' => $pending->id,
            'status' => 'assigned',
            'issuer_id' => $issuer->id,
        ]);
        $this->assertDatabaseHas('order_emission_logs', [
            'order_emission_id' => $pending->id,
            'action' => 'assigned',
            'to_issuer_id' => $issuer->id,
        ]);

        Livewire::actingAs($issuer)
            ->test(EmissionQueue::class)
            ->callTableAction('release', $pending->fresh());

        $this->assertDatabaseHas('order_emissions', [
            'id' => $pending->id,
            'status' => 'pending',
            'issuer_id' => null,
        ]);
        $this->assertDatabaseHas('order_emission_logs', [
            'order_emission_id' => $pending->id,
            'action' => 'released',
            'from_issuer_id' => $issuer->id,
        ]);
        Bus::assertDispatched(NotifyIssuersNewEmission::class);

        $assigned = OrderEmission::create([
            'order_id' => $this->createOrder(['status' => 'awaiting_emission'])->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
            'assigned_at' => '2026-05-15 10:45:00',
        ]);

        Livewire::actingAs($admin)
            ->test(EmissionQueue::class)
            ->callTableAction('reassign', $assigned, [
                'new_issuer_id' => $newIssuer->id,
            ]);

        $this->assertDatabaseHas('order_emissions', [
            'id' => $assigned->id,
            'issuer_id' => $newIssuer->id,
        ]);
        $this->assertDatabaseHas('order_emission_logs', [
            'order_emission_id' => $assigned->id,
            'action' => 'reassigned',
            'from_issuer_id' => $issuer->id,
            'to_issuer_id' => $newIssuer->id,
        ]);

        $order = $this->createOrder(['status' => 'awaiting_emission']);
        $flight = $this->addFlight($order, [
            'tax' => '41.25',
            'paid_boarding_tax' => null,
            'unique_id' => 'complete-action-flight',
        ]);
        $toComplete = OrderEmission::create([
            'order_id' => $order->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
            'assigned_at' => '2026-05-15 10:50:00',
        ]);

        Livewire::actingAs($issuer)
            ->test(EmissionQueue::class)
            ->callTableAction('complete', $toComplete, [
                'loc_'.$flight->id => ' loc789 ',
                'paid_boarding_tax_'.$flight->id => '39.83',
                'miles_cost_'.$flight->id => '27.5',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
            'loc' => 'LOC789',
        ]);
        $this->assertDatabaseHas('order_flights', [
            'id' => $flight->id,
            'loc' => 'LOC789',
            'paid_boarding_tax' => 39.83,
        ]);
        $this->assertDatabaseHas('order_emissions', [
            'id' => $toComplete->id,
            'status' => 'completed',
            'emission_value' => 22.50,
            'miles_cost_per_thousand' => 27.50,
            'duration_seconds' => 600,
        ]);
        $this->assertDatabaseHas('order_emission_logs', [
            'order_emission_id' => $toComplete->id,
            'action' => 'completed',
            'user_id' => $issuer->id,
        ]);
    }

    public function test_support_ticket_view_actions_reply_assign_prioritize_resolve_and_close(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $support = User::factory()->create(['role' => 'support', 'is_active' => true]);
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $ticket = $this->createTicket($customer, ['status' => 'open']);
        $path = "support-ticket-attachments/{$ticket->uuid}/resposta.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4');

        Livewire::actingAs($support)
            ->test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
            ->callAction('reply', [
                'message' => 'Segue comprovante.',
                'attachments' => [$path],
                'attachment_names' => [$path => 'resposta.pdf'],
                'is_internal_note' => false,
                'new_status' => 'awaiting_customer',
            ]);

        $message = $ticket->messages()->firstOrFail();
        $this->assertSame('Segue comprovante.', $message->message);
        $this->assertSame($support->id, $message->user_id);
        $this->assertDatabaseHas('support_ticket_attachments', [
            'support_ticket_id' => $ticket->id,
            'support_ticket_message_id' => $message->id,
            'uploaded_by_user_id' => $support->id,
            'original_name' => 'resposta.pdf',
            'is_internal' => false,
        ]);
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'assigned_to' => $support->id,
            'status' => 'awaiting_customer',
        ]);
        $this->assertTrue($ticket->fresh()->first_response_at->equalTo(Carbon::parse('2026-05-15 12:00:00')));
        Mail::assertSent(SupportTicketReplyMail::class, fn (SupportTicketReplyMail $mail): bool => $mail->hasTo('cliente@example.com'));

        Livewire::actingAs($admin)
            ->test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
            ->callAction('assign', ['assigned_to' => $admin->id])
            ->callAction('change_priority', ['priority' => 'urgent'])
            ->callAction('resolve')
            ->callAction('close');

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'assigned_to' => $admin->id,
            'priority' => 'urgent',
            'status' => 'closed',
        ]);
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    public function test_customer_and_change_request_view_actions_apply_admin_changes(): void
    {
        Carbon::setTestNow('2026-05-15 13:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = $this->createCustomer([
            'email' => 'antigo@example.com',
            'document' => '11122233344',
            'is_affiliate' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ViewCustomer::class, ['record' => $customer->id])
            ->callAction('edit_sensitive', [
                'email' => 'novo@example.com',
                'document' => '529.982.247-25',
            ])
            ->callAction('manage_affiliate', [
                'is_affiliate' => true,
                'code_mode' => 'manual',
                'custom_referral_code' => 'vip123',
                'affiliate_discount_pct' => '8.5',
                'affiliate_credit_pct' => '4.5',
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => 'novo@example.com',
            'document' => '52998224725',
            'is_affiliate' => true,
            'referral_code' => 'VIP123',
        ]);
        $this->assertDatabaseHas('customer_audit_logs', [
            'customer_id' => $customer->id,
            'field' => 'email',
            'old_value' => 'antigo@example.com',
            'new_value' => 'novo@example.com',
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
        ]);

        $approveRequest = CustomerChangeRequest::create([
            'customer_id' => $customer->id,
            'field' => 'email',
            'current_value' => 'novo@example.com',
            'requested_value' => 'aprovado@example.com',
            'reason' => 'Correção',
            'status' => 'pending',
        ]);
        $rejectRequest = CustomerChangeRequest::create([
            'customer_id' => $customer->id,
            'field' => 'document',
            'current_value' => '52998224725',
            'requested_value' => '00000000000',
            'reason' => 'Inválido',
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewChangeRequest::class, ['record' => $approveRequest->id])
            ->callAction('approve');

        $this->assertDatabaseHas('customer_change_requests', [
            'id' => $approveRequest->id,
            'status' => 'approved',
            'admin_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => 'aprovado@example.com',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewChangeRequest::class, ['record' => $rejectRequest->id])
            ->callAction('reject', [
                'admin_notes' => 'Documento não confere.',
            ]);

        $this->assertDatabaseHas('customer_change_requests', [
            'id' => $rejectRequest->id,
            'status' => 'rejected',
            'admin_id' => $admin->id,
            'admin_notes' => 'Documento não confere.',
        ]);
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
        ], $attributes));
    }

    private function createTicket(Customer $customer, array $attributes = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'status' => 'open',
            'priority' => 'normal',
            'message' => 'Mensagem inicial',
        ], $attributes));
    }
}
