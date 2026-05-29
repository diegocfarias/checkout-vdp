<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use App\Models\SavedPassenger;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class CustomerAreaControllerTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    public function test_dashboard_and_orders_only_show_authenticated_customer_data(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $other = $this->createCustomer(['email' => 'outro@example.com']);
        $ownOrder = $this->createOrder(['customer_id' => $customer->id]);
        $cardOrder = $this->createOrder([
            'customer_id' => $customer->id,
            'status' => 'awaiting_payment',
            'tracking_code' => 'VDP-CARD',
        ]);
        $this->addPayment($cardOrder, [
            'payment_method' => 'credit_card',
            'status' => 'pending',
        ]);
        $otherOrder = $this->createOrder(['customer_id' => $other->id]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertViewIs('customer.dashboard')
            ->assertSee('Pagamento em análise')
            ->assertViewHas('recentOrders', fn ($orders): bool => $orders->pluck('id')->contains($ownOrder->id)
                && $orders->pluck('id')->contains($cardOrder->id)
                && ! $orders->pluck('id')->contains($otherOrder->id));

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders'))
            ->assertOk()
            ->assertViewIs('customer.orders')
            ->assertSee('Pagamento em análise')
            ->assertViewHas('orders', fn ($orders): bool => $orders->getCollection()->pluck('id')->contains($ownOrder->id)
                && $orders->getCollection()->pluck('id')->contains($cardOrder->id)
                && ! $orders->getCollection()->pluck('id')->contains($otherOrder->id));
    }

    public function test_order_detail_rejects_orders_from_another_customer(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $other = $this->createCustomer(['email' => 'outro@example.com']);
        $ownOrder = $this->createOrder(['customer_id' => $customer->id]);
        $otherOrder = $this->createOrder(['customer_id' => $other->id]);
        $this->addFlight($ownOrder, [
            'baggage' => [
                'personal_item' => ['included' => true, 'quantity' => 1, 'weight' => '10kg'],
                'carry_on' => ['included' => true, 'quantity' => 1, 'weight' => '10kg'],
                'checked' => ['included' => false, 'quantity' => 0, 'weight' => null],
            ],
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.order.show', $ownOrder))
            ->assertOk()
            ->assertViewIs('customer.order-detail')
            ->assertSee('Mala de mao inclusa', false)
            ->assertSee('Cancelamento da viagem')
            ->assertSee('Solicitar cancelamento')
            ->assertSee('Desisti da compra');

        $this->actingAs($customer, 'customer')
            ->get(route('customer.order.show', $otherOrder))
            ->assertNotFound();
    }

    public function test_order_detail_shows_post_sale_passenger_and_emission_details(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $order = $this->createOrder([
            'customer_id' => $customer->id,
            'status' => 'completed',
        ]);
        $this->addFlight($order, [
            'direction' => 'outbound',
            'cia' => 'AZUL',
            'flight_number' => 'AD1234',
            'departure_location' => 'CNF',
            'arrival_location' => 'VIX',
            'loc' => 'ABC123',
            'price_miles' => '987654',
            'paid_boarding_tax' => 456.78,
        ]);
        $this->addPassenger($order, [
            'full_name' => 'John Passport',
            'nationality' => 'US',
            'document' => null,
            'passport_number' => 'XP123456',
            'passport_expiry' => '2031-12-31',
            'email' => 'john@example.com',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.order.show', $order))
            ->assertOk()
            ->assertSee('Pedido '.$order->tracking_code)
            ->assertSee('LOC: ABC123')
            ->assertSee('John Passport')
            ->assertSee('Passaporte: XP123456')
            ->assertSee('john@example.com')
            ->assertDontSee('987654')
            ->assertDontSee('456,78');
    }

    public function test_update_profile_changes_allowed_fields(): void
    {
        $customer = $this->createCustomer([
            'name' => 'Nome Antigo',
            'email' => 'cliente@example.com',
            'phone' => '11999999999',
        ]);

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), [
                'name' => 'Nome Novo',
                'phone' => '21988887777',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Perfil atualizado com sucesso.');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Nome Novo',
            'phone' => '21988887777',
            'email' => 'cliente@example.com',
        ]);
    }

    public function test_saved_passenger_can_only_be_removed_by_owner(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $other = $this->createCustomer(['email' => 'outro@example.com']);
        $ownPassenger = $this->createSavedPassenger($customer, ['document' => '52998224725']);
        $otherPassenger = $this->createSavedPassenger($other, ['document' => '11144477735']);

        $this->actingAs($customer, 'customer')
            ->delete(route('customer.passenger.destroy', $ownPassenger))
            ->assertRedirect()
            ->assertSessionHas('status', 'Passageiro removido com sucesso.');

        $this->assertDatabaseMissing('saved_passengers', ['id' => $ownPassenger->id]);

        $this->actingAs($customer, 'customer')
            ->delete(route('customer.passenger.destroy', $otherPassenger))
            ->assertNotFound();
    }

    public function test_referrals_page_requires_affiliate_customer_and_shows_balances(): void
    {
        $notAffiliate = $this->createCustomer(['email' => 'nao-afiliado@example.com']);
        $affiliate = $this->createCustomer([
            'email' => 'afiliado@example.com',
            'is_affiliate' => true,
            'referral_code' => 'IND-ABC123',
        ]);
        WalletTransaction::create([
            'customer_id' => $affiliate->id,
            'type' => 'credit',
            'amount' => 100,
            'balance_after' => 100,
            'description' => 'Credito teste',
        ]);

        $this->actingAs($notAffiliate, 'customer')
            ->get(route('customer.referrals'))
            ->assertNotFound();

        $this->actingAs($affiliate, 'customer')
            ->get(route('customer.referrals'))
            ->assertOk()
            ->assertViewIs('customer.referrals')
            ->assertViewHas('availableBalance', 100.0)
            ->assertViewHas('referralLink', url('/?ref=IND-ABC123'));
    }

    public function test_change_request_store_creates_request_and_blocks_duplicate_pending_field(): void
    {
        $customer = $this->createCustomer([
            'email' => 'cliente@example.com',
            'document' => '529.982.247-25',
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.change-request'), [
                'field' => 'email',
                'requested_value' => 'novo@example.com',
                'reason' => 'Atualizar contato',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Solicitação enviada. Você será notificado quando for analisada.');

        $this->assertDatabaseHas('customer_change_requests', [
            'customer_id' => $customer->id,
            'field' => 'email',
            'current_value' => 'cliente@example.com',
            'requested_value' => 'novo@example.com',
            'reason' => 'Atualizar contato',
            'status' => 'pending',
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.change-request'), [
                'field' => 'email',
                'requested_value' => 'outro@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('field');

        $this->assertSame(1, CustomerChangeRequest::where('customer_id', $customer->id)->count());
    }

    public function test_change_request_validates_requested_email_and_document_before_admin_review(): void
    {
        $customer = $this->createCustomer([
            'email' => 'cliente@example.com',
            'document' => '52998224725',
        ]);
        $this->createCustomer([
            'email' => 'usado@example.com',
            'document' => '11144477735',
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.change-request'), [
                'field' => 'email',
                'requested_value' => 'email-invalido',
            ])
            ->assertSessionHasErrors('requested_value');

        $this->actingAs($customer, 'customer')
            ->post(route('customer.change-request'), [
                'field' => 'email',
                'requested_value' => 'usado@example.com',
            ])
            ->assertSessionHasErrors('requested_value');

        $this->actingAs($customer, 'customer')
            ->post(route('customer.change-request'), [
                'field' => 'document',
                'requested_value' => '123',
            ])
            ->assertSessionHasErrors('requested_value');

        $this->actingAs($customer, 'customer')
            ->post(route('customer.change-request'), [
                'field' => 'document',
                'requested_value' => '390.533.447-05',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Solicitação enviada. Você será notificado quando for analisada.');

        $this->assertDatabaseHas('customer_change_requests', [
            'customer_id' => $customer->id,
            'field' => 'document',
            'requested_value' => '39053344705',
            'status' => 'pending',
        ]);
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Cliente Teste',
            'email' => fake()->unique()->safeEmail(),
            'document' => '529.982.247-25',
            'phone' => '11999999999',
            'status' => 'active',
            'is_affiliate' => false,
        ], $attributes));
    }

    private function createSavedPassenger(Customer $customer, array $attributes = []): SavedPassenger
    {
        return SavedPassenger::create(array_merge([
            'customer_id' => $customer->id,
            'full_name' => 'Maria Silva',
            'nationality' => 'BR',
            'document' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
        ], $attributes));
    }
}
