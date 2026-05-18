<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_or_create_from_payer_returns_existing_customer_by_email(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Existente',
            'email' => 'cliente@example.com',
            'document' => '111.222.333-44',
            'status' => 'active',
        ]);

        $result = app(CustomerService::class)->findOrCreateFromPayer([
            'name' => 'Outro Nome',
            'email' => 'cliente@example.com',
            'document' => '999.888.777-66',
        ]);

        $this->assertSame($customer->id, $result->id);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Cliente Existente',
            'document' => '111.222.333-44',
        ]);
    }

    public function test_find_or_create_from_payer_creates_pending_customer(): void
    {
        Log::spy();

        $customer = app(CustomerService::class)->findOrCreateFromPayer([
            'name' => 'Novo Cliente',
            'email' => 'novo@example.com',
            'document' => '123.456.789-00',
        ]);

        $this->assertSame('Novo Cliente', $customer->name);
        $this->assertSame('novo@example.com', $customer->email);
        $this->assertSame('pending', $customer->status);
        $this->assertDatabaseHas('customers', [
            'email' => 'novo@example.com',
            'status' => 'pending',
        ]);
        Log::shouldHaveReceived('info')->once();
    }

    public function test_link_google_account_updates_identity_and_activates_customer(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Google',
            'email' => 'google@example.com',
            'status' => 'pending',
        ]);

        $googleUser = new class
        {
            public function getId(): string
            {
                return 'google-123';
            }

            public function getAvatar(): string
            {
                return 'https://example.com/avatar.png';
            }
        };

        app(CustomerService::class)->linkGoogleAccount($customer, $googleUser);

        $customer->refresh();
        $this->assertSame('google-123', $customer->google_id);
        $this->assertSame('https://example.com/avatar.png', $customer->avatar_url);
        $this->assertSame('active', $customer->status);
    }

    public function test_apply_change_request_updates_customer_and_writes_audit_log(): void
    {
        request()->server->set('REMOTE_ADDR', '203.0.113.10');

        $admin = User::factory()->create([
            'name' => 'Admin Teste',
            'role' => 'admin',
        ]);
        $customer = Customer::create([
            'name' => 'Cliente Antes',
            'email' => 'antes@example.com',
            'status' => 'active',
        ]);
        $changeRequest = CustomerChangeRequest::create([
            'customer_id' => $customer->id,
            'field' => 'email',
            'current_value' => 'antes@example.com',
            'requested_value' => 'depois@example.com',
            'status' => 'pending',
        ]);

        app(CustomerService::class)->applyChangeRequest($changeRequest, $admin);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => 'depois@example.com',
        ]);
        $this->assertDatabaseHas('customer_change_requests', [
            'id' => $changeRequest->id,
            'status' => 'approved',
            'admin_id' => $admin->id,
        ]);
        $this->assertNotNull($changeRequest->fresh()->resolved_at);
        $this->assertDatabaseHas('customer_audit_logs', [
            'customer_id' => $customer->id,
            'field' => 'email',
            'old_value' => 'antes@example.com',
            'new_value' => 'depois@example.com',
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'ip_address' => '203.0.113.10',
        ]);
    }
}
