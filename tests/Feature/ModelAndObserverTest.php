<?php

namespace Tests\Feature;

use App\Jobs\IssueTravellinkOrder;
use App\Jobs\NotifyIssuersNewEmission;
use App\Mail\OrderStatusMail;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\OrderEmission;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\ShowcaseRoute;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class ModelAndObserverTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.botpress.webhook_url', null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_customer_model_helpers_and_referral_code_collision_checks(): void
    {
        Coupon::create([
            'code' => 'IND-TAKEN',
            'type' => 'fixed',
            'value' => 10,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'document' => '529.982.247-25',
            'status' => 'active',
            'is_affiliate' => true,
            'referral_code' => 'IND-ABC123',
        ]);

        $this->assertTrue($customer->isActive());
        $this->assertFalse($customer->isPending());
        $this->assertTrue($customer->isAffiliate());
        $this->assertSame('52998224725', $customer->getCleanDocument());
        $this->assertTrue(Customer::isReferralCodeTaken('ind-abc123'));
        $this->assertFalse(Customer::isReferralCodeTaken('IND-ABC123', $customer->id));
        $this->assertTrue(Customer::isReferralCodeTaken('IND-TAKEN'));
        $this->assertStringStartsWith('IND-', $customer->generateReferralCode());
    }

    public function test_support_ticket_attachment_accessors(): void
    {
        $image = new SupportTicketAttachment([
            'mime_type' => 'image/png',
            'size' => 512,
        ]);
        $pdf = new SupportTicketAttachment([
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ]);
        $zip = new SupportTicketAttachment([
            'mime_type' => 'application/zip',
            'size' => 2 * 1024 * 1024,
        ]);

        $this->assertTrue($image->is_previewable);
        $this->assertTrue($pdf->is_previewable);
        $this->assertFalse($zip->is_previewable);
        $this->assertSame('512 B', $image->formatted_size);
        $this->assertSame('2,0 KB', $pdf->formatted_size);
        $this->assertSame('2,0 MB', $zip->formatted_size);
    }

    public function test_showcase_route_helpers_and_active_scope(): void
    {
        Carbon::setTestNow('2026-05-14');
        $route = ShowcaseRoute::create([
            'departure_iata' => 'gru',
            'departure_city' => 'São Paulo',
            'arrival_iata' => 'sdu',
            'arrival_city' => 'Rio de Janeiro',
            'trip_type' => 'roundtrip',
            'cabin' => 'EC',
            'search_date_from' => '2026-06-01',
            'search_date_to' => '2026-06-10',
            'sample_dates_count' => 3,
            'cached_price' => 1234.56,
            'is_active' => true,
        ]);
        ShowcaseRoute::create([
            'departure_iata' => 'cnf',
            'departure_city' => 'Belo Horizonte',
            'arrival_iata' => 'vix',
            'arrival_city' => 'Vitória',
            'trip_type' => 'oneway',
            'cabin' => 'EC',
            'cached_price' => null,
            'is_active' => true,
        ]);

        $this->assertSame(['2026-06-01', '2026-06-05', '2026-06-09', '2026-06-10'], $route->sampleDates());
        $this->assertSame('R$ 1.234,56', $route->formattedPrice());
        $this->assertSame('GRU → SDU', $route->routeLabel());
        $this->assertTrue(ShowcaseRoute::active()->pluck('id')->contains($route->id));
        $this->assertSame('-', (new ShowcaseRoute)->formattedPrice());
    }

    public function test_order_emission_duration_scopes_and_status_labels(): void
    {
        $issuer = User::factory()->create(['role' => 'issuer']);
        $completed = OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'assigned_at' => '2026-05-14 10:00:00',
            'completed_at' => '2026-05-14 10:05:30',
        ]);
        OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'status' => 'pending',
        ]);
        OrderEmission::create([
            'order_id' => $this->createOrder()->id,
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
        ]);

        $this->assertSame(330, $completed->calculateDuration());
        $this->assertSame(1, OrderEmission::completed()->count());
        $this->assertSame(1, OrderEmission::pending()->count());
        $this->assertSame(1, OrderEmission::assigned()->count());
        $this->assertSame(2, OrderEmission::forIssuer($issuer->id)->count());
        $this->assertSame(1, OrderEmission::completedBetween('2026-05-14 00:00:00', '2026-05-15 00:00:00')->count());
        $this->assertSame('Pagamento confirmado, aguardando emissão', OrderStatusHistory::statusLabel('awaiting_emission'));
        $this->assertSame('Custom status', OrderStatusHistory::statusLabel('custom_status'));
    }

    public function test_customer_observer_writes_audit_log_for_authenticated_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Cliente Antigo',
            'email' => 'cliente@example.com',
            'document' => '529.982.247-25',
            'status' => 'active',
        ]);

        $this->actingAs($admin);
        $customer->update(['email' => 'novo@example.com']);

        $this->assertDatabaseHas('customer_audit_logs', [
            'customer_id' => $customer->id,
            'field' => 'email',
            'old_value' => 'cliente@example.com',
            'new_value' => 'novo@example.com',
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_order_observer_creates_status_history_emission_and_notifications(): void
    {
        Bus::fake();
        Mail::fake();

        $order = $this->createOrder([
            'conversation_id' => 'conversation-123',
            'user_id' => 'user-123',
        ]);
        $this->addPassenger($order, ['email' => 'passageiro@example.com']);
        $this->addPayment($order, [
            'status' => 'paid',
            'gateway' => 'appmax',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'pending',
            'description' => 'Pedido criado',
        ]);

        $order->update(['status' => 'awaiting_emission']);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'awaiting_emission',
            'description' => 'Pagamento confirmado, aguardando emissão',
        ]);
        $this->assertDatabaseHas('order_emissions', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $emission = OrderEmission::where('order_id', $order->id)->firstOrFail();
        $this->assertDatabaseHas('order_emission_logs', [
            'order_emission_id' => $emission->id,
            'action' => 'created',
        ]);
        Bus::assertDispatched(NotifyIssuersNewEmission::class);
        Mail::assertSent(OrderStatusMail::class, fn (OrderStatusMail $mail): bool => $mail->hasTo('passageiro@example.com'));
    }

    public function test_order_observer_dispatches_travellink_auto_emission_for_eligible_orders(): void
    {
        Bus::fake();
        Mail::fake();
        Setting::set('travellink_emission_enabled', true, 'boolean');
        Setting::set('travellink_auto_emission_enabled', true, 'boolean');

        $order = $this->createOrder();
        $this->addPassenger($order, ['email' => 'passageiro@example.com']);
        $this->addFlight($order, [
            'source_provider' => 'travellink',
            'source_airlines' => 'ALL',
            'provider_payload' => [
                'viagem_id' => 111,
                'identificacao_da_viagem' => 'OUTBOUND-TOKEN',
                'classes_selecionadas' => [[
                    'BaseTarifaria' => 'ABC',
                    'Classe' => 'Y',
                    'Familia' => 'LIG',
                    'NumeroDoVoo' => '4111',
                ]],
            ],
        ]);

        $order->update(['status' => 'awaiting_emission']);

        $emission = OrderEmission::where('order_id', $order->id)->firstOrFail();
        Bus::assertDispatched(NotifyIssuersNewEmission::class);
        Bus::assertDispatched(IssueTravellinkOrder::class, fn (IssueTravellinkOrder $job): bool => $job->emission->is($emission));
    }

    public function test_order_status_whatsapp_notification_uses_tokenized_tracking_link(): void
    {
        Bus::fake();
        config()->set('app.url', 'https://checkout.test');
        config()->set('services.botpress.webhook_url', 'https://botpress.test/webhook');
        Http::fake([
            'https://botpress.test/webhook' => Http::response(['ok' => true]),
        ]);

        $order = $this->createOrder([
            'conversation_id' => 'conversation-123',
            'user_id' => 'user-123',
        ]);

        $order->update(['status' => 'awaiting_emission']);

        $expectedUrl = route('tracking.show', ['trackingCode' => $order->tracking_code])
            .'?'.http_build_query(['token' => $order->token]);

        Http::assertSent(function ($request) use ($order, $expectedUrl): bool {
            return $request->url() === 'https://botpress.test/webhook'
                && $request['conversationId'] === 'conversation-123'
                && $request['userId'] === 'user-123'
                && str_contains($request['message'], $order->tracking_code)
                && str_contains($request['message'], $expectedUrl);
        });
    }
}
