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
        $otherOrder = $this->createOrder(['customer_id' => $other->id]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertViewIs('customer.dashboard')
            ->assertViewHas('recentOrders', fn ($orders): bool => $orders->pluck('id')->contains($ownOrder->id)
                && ! $orders->pluck('id')->contains($otherOrder->id));

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders'))
            ->assertOk()
            ->assertViewIs('customer.orders')
            ->assertViewHas('orders', fn ($orders): bool => $orders->getCollection()->pluck('id')->contains($ownOrder->id)
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
            ->assertSee('Mala de mao inclusa', false);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.order.show', $otherOrder))
            ->assertNotFound();
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
