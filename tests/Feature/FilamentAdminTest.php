<?php

namespace Tests\Feature;

use App\Filament\Pages\EmissionDashboard;
use App\Filament\Pages\ManagePricing;
use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\PaidOrdersDashboard;
use App\Filament\Pages\ReferralDashboard;
use App\Filament\Pages\SupportDashboard;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Widgets\LatestPendingEmissions;
use App\Filament\Widgets\StatsOverview;
use App\Models\Customer;
use App\Models\PricingChangeLog;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use App\Services\PricingSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentAdminTest extends TestCase
{
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

    public function test_admin_only_resources_and_widgets_respect_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $issuer = User::factory()->create(['role' => 'issuer', 'is_active' => true]);
        $support = User::factory()->create(['role' => 'support', 'is_active' => true]);
        $customerOnly = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $this->assertTrue($admin->canAccessPanel(app(\Filament\Panel::class)));
        $this->assertTrue($issuer->canAccessPanel(app(\Filament\Panel::class)));
        $this->assertTrue($support->canAccessPanel(app(\Filament\Panel::class)));
        $this->assertFalse($customerOnly->canAccessPanel(app(\Filament\Panel::class)));

        $this->actingAs($admin);
        $this->assertTrue(UserResource::canAccess());
        $this->assertTrue(OrderResource::canAccess());
        $this->assertTrue(ManageSettings::canAccess());
        $this->assertTrue(ManagePricing::canAccess());
        $this->assertTrue(PaidOrdersDashboard::canAccess());
        $this->assertTrue(EmissionDashboard::canAccess());
        $this->assertTrue(ReferralDashboard::canAccess());
        $this->assertTrue(SupportDashboard::canAccess());
        $this->assertTrue(StatsOverview::canView());
        $this->assertTrue(LatestPendingEmissions::canView());
        $this->assertTrue(SupportTicketResource::canAccess());

        $this->actingAs($issuer);
        $this->assertFalse(UserResource::canAccess());
        $this->assertFalse(OrderResource::canAccess());
        $this->assertFalse(ManageSettings::canAccess());
        $this->assertFalse(ManagePricing::canAccess());
        $this->assertFalse(StatsOverview::canView());
        $this->assertFalse(SupportTicketResource::canAccess());

        $this->actingAs($support);
        $this->assertFalse(UserResource::canAccess());
        $this->assertFalse(OrderResource::canAccess());
        $this->assertFalse(ManageSettings::canAccess());
        $this->assertFalse(ManagePricing::canAccess());
        $this->assertTrue(SupportTicketResource::canAccess());
    }

    public function test_user_resource_creates_panel_users_with_hashed_passwords(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Nova Emissora',
                'email' => 'emissora@example.com',
                'role' => 'issuer',
                'is_active' => true,
                'password' => 'senha-segura',
                'pushover_user_key' => 'user-key-123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'emissora@example.com')->firstOrFail();

        $this->assertSame('Nova Emissora', $user->name);
        $this->assertSame('issuer', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('user-key-123', $user->pushover_user_key);
        $this->assertTrue(Hash::check('senha-segura', $user->password));
        $this->assertSame('Emissor', UserResource::roleLabel('issuer'));
        $this->assertSame('-', UserResource::roleLabel(null));
        $this->assertArrayHasKey('support', UserResource::roleOptions());
    }

    public function test_manage_settings_saves_gateways_providers_and_interest_rates(): void
    {
        Carbon::setTestNow('2026-05-14 12:34:56');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(ManageSettings::class)
            ->fillForm([
                'mix_enabled' => false,
                'gateway_pix' => 'c6bank',
                'pix_discount' => '4.5',
                'gateway_credit_card' => 'c6bank',
                'max_installments_appmax' => 6,
                'max_installments_c6bank' => 10,
                'interest_rates_appmax' => ['2' => '1.25'],
                'interest_rates_c6bank' => ['3' => '2.5', '1' => '0'],
                'pix_expiration_minutes' => 45,
                'order_expiration_minutes' => 60,
                'whatsapp_number' => '5531999999999',
                'emission_value_per_order' => '18.50',
                'pushover_app_token' => 'app-token',
                'referral_enabled' => true,
                'referral_discount_pct' => '6',
                'referral_credit_pct' => '7',
                'referral_credit_release_mode' => 'after_arrival',
                'referral_credit_release_hours' => 36,
                'referral_cookie_days' => 45,
                'referral_cumulative_with_pix' => false,
                'showcase_refresh_minutes' => 90,
                'showcase_max_searches_per_minute' => 5,
                'showcase_wait_seconds' => 12,
                'showcase_max_cards' => 12,
                'showcase_sort_mode' => 'cheapest',
                'calendar_prices_enabled' => false,
                'provider_gol' => ['vdp', 'bds_crawler'],
                'provider_azul' => ['bds_crawler'],
                'provider_latam' => ['latam_crawler'],
                'vdp_timeout' => 40,
                'crawler_timeout' => 41,
                'bds_crawler_timeout' => 42,
                'bds_patria_enabled' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(Setting::get('mix_enabled'));
        $this->assertSame('c6bank', Setting::get('gateway_pix'));
        $this->assertSame('c6bank', Setting::get('gateway_credit_card'));
        $this->assertTrue(Setting::get('pix_enabled'));
        $this->assertTrue(Setting::get('credit_card_enabled'));
        $this->assertSame(10, Setting::get('max_installments'));
        $this->assertEquals([1 => 0.0, 3 => 2.5], Setting::get('interest_rates'));
        $this->assertSame('5531999999999', Setting::get('whatsapp_number'));
        $this->assertSame('after_arrival', Setting::get('referral_credit_release_mode'));
        $this->assertFalse(Setting::get('referral_cumulative_with_pix'));
        $this->assertSame(['vdp', 'bds_crawler'], Setting::get('provider_gol'));
        $this->assertTrue(Setting::get('bds_patria_enabled'));
        $this->assertSame((string) now()->timestamp, Setting::get('pricing_version'));
    }

    public function test_manage_pricing_saves_history_and_restores_snapshot(): void
    {
        Carbon::setTestNow('2026-05-24 09:10:11');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(ManagePricing::class)
            ->fillForm([
                'pricing_miles_enabled' => true,
                'pricing_miles_azul' => '31.50',
                'pricing_miles_gol' => '29.90',
                'pricing_miles_latam' => '32.10',
                'pricing_miles_pct_enabled' => true,
                'pricing_miles_pct_azul' => '12',
                'pricing_miles_pct_gol' => '13',
                'pricing_miles_pct_latam' => '14',
                'pricing_miles_priority_order' => [
                    PricingSettingsService::MILES_METHOD_TOTAL_PERCENTAGE,
                    PricingSettingsService::MILES_METHOD_MILHEIRO,
                    PricingSettingsService::MILES_METHOD_API_ORIGINAL,
                ],
                'pricing_pct_enabled' => true,
                'pricing_pct_azul' => '75',
                'pricing_pct_gol' => '76',
                'pricing_pct_latam' => '77',
                'boarding_tax_fallback_pct' => '8.5',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $firstLog = PricingChangeLog::firstOrFail();

        $this->assertEqualsWithDelta(31.5, (float) Setting::get('pricing_miles_azul'), 0.01);
        $this->assertTrue(Setting::get('pricing_miles_pct_enabled'));
        $this->assertSame([
            PricingSettingsService::MILES_METHOD_TOTAL_PERCENTAGE,
            PricingSettingsService::MILES_METHOD_MILHEIRO,
            PricingSettingsService::MILES_METHOD_API_ORIGINAL,
        ], Setting::get('pricing_miles_priority_order'));
        $this->assertEqualsWithDelta(8.5, (float) Setting::get('boarding_tax_fallback_pct'), 0.01);
        $this->assertSame((string) now()->timestamp, Setting::get('pricing_version'));
        $this->assertSame($admin->id, $firstLog->user_id);

        Carbon::setTestNow('2026-05-24 09:20:00');
        app(PricingSettingsService::class)->save([
            'pricing_miles_enabled' => false,
            'pricing_miles_pct_enabled' => false,
            'pricing_pct_enabled' => false,
        ]);

        $this->assertFalse(Setting::get('pricing_miles_enabled'));

        app(PricingSettingsService::class)->restore($firstLog);

        $this->assertTrue(Setting::get('pricing_miles_enabled'));
        $this->assertTrue(Setting::get('pricing_miles_pct_enabled'));
        $this->assertSame($firstLog->id, PricingChangeLog::latest('id')->first()->restored_from_id);
    }

    public function test_manage_settings_invalidates_search_cache_when_provider_configuration_changes(): void
    {
        Carbon::setTestNow('2026-05-20 10:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Setting::set('pricing_version', 'old-version', 'string');
        Setting::set('mix_enabled', true, 'boolean');
        Setting::set('provider_gol', ['vdp'], 'json');
        Setting::set('provider_azul', ['vdp'], 'json');
        Setting::set('provider_latam', ['latam_crawler'], 'json');
        Setting::set('bds_patria_enabled', false, 'boolean');
        Setting::set('pricing_miles_enabled', true, 'boolean');
        Setting::set('pricing_pct_enabled', false, 'boolean');
        Setting::set('pricing_miles_azul', '30.00', 'string');
        Setting::set('pricing_miles_gol', '30.00', 'string');
        Setting::set('pricing_miles_latam', '30.00', 'string');
        Setting::set('pricing_pct_azul', '80', 'string');
        Setting::set('pricing_pct_gol', '80', 'string');
        Setting::set('pricing_pct_latam', '80', 'string');
        Setting::set('boarding_tax_fallback_pct', '10', 'string');
        Setting::set('pix_discount', '0', 'string');

        Livewire::actingAs($admin)
            ->test(ManageSettings::class)
            ->fillForm($this->settingsPayload([
                'provider_gol' => ['bds_crawler'],
            ]))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame((string) now()->timestamp, Setting::get('pricing_version'));
    }

    public function test_support_ticket_resource_scopes_support_agent_visibility(): void
    {
        $support = User::factory()->create(['role' => 'support', 'is_active' => true]);
        $otherSupport = User::factory()->create(['role' => 'support', 'is_active' => true]);
        $customer = $this->createCustomer();

        $assigned = $this->createTicket($customer, ['assigned_to' => $support->id, 'status' => 'in_progress']);
        $unassigned = $this->createTicket($customer, ['assigned_to' => null, 'status' => 'awaiting_customer']);
        $openOther = $this->createTicket($customer, ['assigned_to' => $otherSupport->id, 'status' => 'open']);
        $hidden = $this->createTicket($customer, ['assigned_to' => $otherSupport->id, 'status' => 'in_progress']);

        $this->actingAs($support);
        $visibleIds = SupportTicketResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($assigned->id, $visibleIds);
        $this->assertContains($unassigned->id, $visibleIds);
        $this->assertContains($openOther->id, $visibleIds);
        $this->assertNotContains($hidden->id, $visibleIds);
    }

    public function test_admin_attachment_routes_allow_admin_and_scope_support_agents(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $support = User::factory()->create(['role' => 'support', 'is_active' => true]);
        $otherSupport = User::factory()->create(['role' => 'support', 'is_active' => true]);
        $customer = $this->createCustomer();
        $ticket = $this->createTicket($customer, ['assigned_to' => $otherSupport->id, 'status' => 'in_progress']);
        Storage::disk('local')->put("support-ticket-attachments/{$ticket->uuid}/interno.pdf", '%PDF-1.4');
        $attachment = SupportTicketAttachment::create([
            'support_ticket_id' => $ticket->id,
            'uploaded_by_user_id' => $admin->id,
            'disk' => 'local',
            'path' => "support-ticket-attachments/{$ticket->uuid}/interno.pdf",
            'original_name' => 'interno.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
            'is_internal' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.support.attachments.view', $attachment))
            ->assertOk();

        $this->actingAs($support)
            ->get(route('admin.support.attachments.view', $attachment))
            ->assertForbidden();

        $ticket->update(['assigned_to' => $support->id]);

        $this->actingAs($support)
            ->get(route('admin.support.attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('interno.pdf');
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

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'mix_enabled' => true,
            'gateway_pix' => '',
            'pix_discount' => '0',
            'gateway_credit_card' => 'appmax',
            'max_installments_appmax' => 12,
            'max_installments_c6bank' => 12,
            'interest_rates_appmax' => [],
            'interest_rates_c6bank' => [],
            'pix_expiration_minutes' => 30,
            'order_expiration_minutes' => 30,
            'whatsapp_number' => '',
            'emission_value_per_order' => '0',
            'pushover_app_token' => '',
            'referral_enabled' => false,
            'referral_discount_pct' => '5',
            'referral_credit_pct' => '5',
            'referral_credit_release_mode' => 'after_purchase',
            'referral_credit_release_hours' => 24,
            'referral_cookie_days' => 30,
            'referral_cumulative_with_pix' => true,
            'showcase_refresh_minutes' => 60,
            'showcase_max_searches_per_minute' => 6,
            'showcase_wait_seconds' => 10,
            'showcase_max_cards' => 9,
            'showcase_sort_mode' => 'manual',
            'calendar_prices_enabled' => true,
            'provider_gol' => ['vdp'],
            'provider_azul' => ['vdp'],
            'provider_latam' => ['latam_crawler'],
            'vdp_timeout' => 35,
            'crawler_timeout' => 35,
            'bds_crawler_timeout' => 60,
            'bds_patria_enabled' => false,
        ], $overrides);
    }
}
