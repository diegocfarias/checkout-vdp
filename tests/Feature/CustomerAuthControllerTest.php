<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class CustomerAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_is_logged_in(): void
    {
        $this->post(route('customer.register.submit'), [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'document' => '529.982.247-25',
            'phone' => '11999999999',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticated('customer');
        $this->assertDatabaseHas('customers', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'document' => '52998224725',
            'status' => 'active',
        ]);
    }

    public function test_login_activates_pending_customer_with_valid_password(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Pendente',
            'email' => 'pendente@example.com',
            'password' => Hash::make('password123'),
            'document' => '52998224725',
            'status' => 'pending',
        ]);

        $this->post(route('customer.login.submit'), [
            'email' => 'pendente@example.com',
            'password' => 'password123',
        ])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
        $this->assertSame('active', $customer->fresh()->status);
    }

    public function test_login_rejects_missing_password_or_invalid_credentials(): void
    {
        Customer::create([
            'name' => 'Sem Senha',
            'email' => 'sem-senha@example.com',
            'document' => '52998224725',
            'status' => 'pending',
        ]);
        Customer::create([
            'name' => 'Com Senha',
            'email' => 'com-senha@example.com',
            'password' => Hash::make('password123'),
            'document' => '52998224725',
            'status' => 'active',
        ]);

        $this->from(route('customer.login'))
            ->post(route('customer.login.submit'), [
                'email' => 'sem-senha@example.com',
                'password' => 'qualquer',
            ])
            ->assertRedirect(route('customer.login'))
            ->assertSessionHas('needs_password', true)
            ->assertSessionHasErrors('email');

        $this->from(route('customer.login'))
            ->post(route('customer.login.submit'), [
                'email' => 'com-senha@example.com',
                'password' => 'errada',
            ])
            ->assertRedirect(route('customer.login'))
            ->assertSessionHasErrors('email');
    }

    public function test_logout_clears_customer_session(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'password' => Hash::make('password123'),
            'document' => '52998224725',
            'status' => 'active',
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.logout'))
            ->assertRedirect(route('search.home'));

        $this->assertGuest('customer');
    }

    public function test_forgot_password_sends_reset_notification_and_reset_logs_customer_in(): void
    {
        Notification::fake();
        $customer = Customer::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'password' => Hash::make('old-password'),
            'document' => '52998224725',
            'status' => 'pending',
        ]);

        $this->post(route('customer.password.email'), [
            'email' => 'cliente@example.com',
        ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Enviamos um link para redefinir sua senha.');

        Notification::assertSentTo($customer, \App\Notifications\CustomerResetPassword::class);

        $token = Password::broker('customers')->createToken($customer);

        $this->post(route('customer.password.update'), [
            'token' => $token,
            'email' => 'cliente@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertRedirect(route('customer.dashboard'))
            ->assertSessionHas('status', 'Senha definida com sucesso!');

        $customer->refresh();
        $this->assertSame('active', $customer->status);
        $this->assertTrue(Hash::check('new-password', $customer->password));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_complete_registration_requires_google_session_and_creates_customer(): void
    {
        $this->get(route('customer.complete-registration'))
            ->assertRedirect(route('customer.login'));

        $googleUser = [
            'id' => 'google-123',
            'name' => 'Google User',
            'email' => 'google@example.com',
            'avatar' => 'https://example.com/avatar.png',
        ];

        $this->withSession(['google_user' => $googleUser])
            ->post(route('customer.complete-registration.submit'), [
                'document' => '529.982.247-25',
                'phone' => '11999999999',
            ])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticated('customer');
        $this->assertDatabaseHas('customers', [
            'name' => 'Google User',
            'email' => 'google@example.com',
            'google_id' => 'google-123',
            'document' => '52998224725',
            'status' => 'active',
        ]);
    }

    public function test_google_callback_links_existing_customer_by_email(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Existente',
            'email' => 'google@example.com',
            'document' => '52998224725',
            'status' => 'pending',
        ]);
        $googleUser = new class
        {
            public function getId(): string
            {
                return 'google-456';
            }

            public function getName(): string
            {
                return 'Google User';
            }

            public function getEmail(): string
            {
                return 'google@example.com';
            }

            public function getAvatar(): string
            {
                return 'https://example.com/avatar.png';
            }
        };
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('customer.google.callback'))
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'google_id' => 'google-456',
            'avatar_url' => 'https://example.com/avatar.png',
            'status' => 'active',
        ]);
    }

    public function test_google_callback_stores_new_google_user_in_session(): void
    {
        $googleUser = new class
        {
            public function getId(): string
            {
                return 'google-new';
            }

            public function getName(): string
            {
                return 'Novo Google';
            }

            public function getEmail(): string
            {
                return 'novo-google@example.com';
            }

            public function getAvatar(): string
            {
                return 'https://example.com/novo.png';
            }
        };
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('customer.google.callback'))
            ->assertRedirect(route('customer.complete-registration'))
            ->assertSessionHas('google_user.email', 'novo-google@example.com');
    }
}
