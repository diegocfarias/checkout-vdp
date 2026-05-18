<?php

namespace Tests\Feature;

use App\Jobs\NotifyIssuersNewEmission;
use App\Jobs\RefreshShowcaseRoute;
use App\Jobs\ReleaseReferralCredits;
use App\Models\Customer;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\ShowcaseRoute;
use App\Models\WalletTransaction;
use App\Services\PushoverService;
use App\Services\ReferralService;
use App\Services\VdpFlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class JobTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_release_referral_credits_credits_completed_orders_and_reverses_cancelled_orders(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');
        $affiliate = $this->createCustomer([
            'is_affiliate' => true,
            'referral_code' => 'IND-JOB123',
        ]);
        WalletTransaction::create([
            'customer_id' => $affiliate->id,
            'type' => 'credit',
            'amount' => 10,
            'balance_after' => 10,
            'description' => 'Saldo anterior',
        ]);

        $completedReferral = $this->createReferral($affiliate, [
            'referred_order_id' => $this->createOrder(['status' => 'completed'])->id,
            'credit_amount' => 25,
            'credit_available_at' => now()->subMinute(),
        ]);
        $cancelledReferral = $this->createReferral($affiliate, [
            'referred_order_id' => $this->createOrder(['status' => 'cancelled'])->id,
            'credit_amount' => 30,
            'credit_available_at' => now()->subMinute(),
        ]);
        $futureReferral = $this->createReferral($affiliate, [
            'referred_order_id' => $this->createOrder(['status' => 'completed'])->id,
            'credit_amount' => 40,
            'credit_available_at' => now()->addDay(),
        ]);

        app(ReleaseReferralCredits::class)->handle(app(ReferralService::class));

        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $affiliate->id,
            'type' => 'credit',
            'amount' => 25,
            'balance_after' => 35,
            'referral_id' => $completedReferral->id,
        ]);
        $this->assertDatabaseHas('referrals', [
            'id' => $completedReferral->id,
            'credit_status' => 'available',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('referrals', [
            'id' => $cancelledReferral->id,
            'credit_status' => 'reversed',
            'status' => 'reversed',
        ]);
        $this->assertDatabaseHas('referrals', [
            'id' => $futureReferral->id,
            'credit_status' => 'pending',
            'status' => 'active',
        ]);
    }

    public function test_notify_issuers_job_delegates_to_pushover_service(): void
    {
        $order = $this->createOrder();
        $pushover = Mockery::mock(PushoverService::class);
        $pushover->shouldReceive('notifyIssuers')
            ->once()
            ->with(Mockery::on(fn ($received): bool => $received->is($order)));

        (new NotifyIssuersNewEmission($order))->handle($pushover);
    }

    public function test_refresh_showcase_route_updates_route_and_refresh_log_with_best_price(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');
        Setting::set('showcase_wait_seconds', 1, 'integer');
        $route = ShowcaseRoute::create([
            'departure_iata' => 'GRU',
            'departure_city' => 'São Paulo',
            'arrival_iata' => 'SDU',
            'arrival_city' => 'Rio de Janeiro',
            'trip_type' => 'oneway',
            'cabin' => 'EC',
            'search_date_from' => '2026-06-01',
            'search_date_to' => '2026-06-02',
            'sample_dates_count' => 2,
            'cached_price' => 999,
            'is_active' => true,
        ]);

        $vdpService = Mockery::mock(VdpFlightService::class);
        $vdpService->shouldReceive('searchFlightsWithCacheInfo')
            ->twice()
            ->andReturn(
                [
                    'from_cache' => true,
                    'data' => [
                        'outbound' => [
                            [
                                'operator' => 'GOL',
                                'price' => 300,
                                'departure_time' => '08:00',
                                'arrival_time' => '09:00',
                                'total_flight_duration' => '01:00',
                            ],
                        ],
                        'inbound' => [],
                    ],
                ],
                [
                    'from_cache' => true,
                    'data' => [
                        'outbound' => [
                            [
                                'operator' => 'AZUL',
                                'price' => 250,
                                'departure_time' => '10:00',
                                'arrival_time' => '11:00',
                                'total_flight_duration' => '01:00',
                            ],
                        ],
                        'inbound' => [],
                    ],
                ],
            );
        $vdpService->shouldReceive('calculateFlightPrice')
            ->twice()
            ->andReturnUsing(fn (array $flight): float => (float) $flight['price']);

        (new RefreshShowcaseRoute($route))->handle($vdpService);

        $route->refresh();
        $this->assertEqualsWithDelta(250, (float) $route->cached_price, 0.01);
        $this->assertSame('2026-06-02', $route->cached_date->format('Y-m-d'));
        $this->assertSame('AZUL', $route->cached_airline);
        $this->assertSame('10:00', $route->cached_flight_data['departure_time']);

        $this->assertDatabaseHas('showcase_refresh_logs', [
            'showcase_route_id' => $route->id,
            'status' => 'completed',
            'dates_searched' => 2,
            'cache_hits' => 2,
            'api_calls' => 0,
            'errors_count' => 0,
            'best_price' => 250,
            'best_date' => '2026-06-02 00:00:00',
            'previous_price' => 999,
        ]);
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Afiliado Teste',
            'email' => fake()->unique()->safeEmail(),
            'document' => '529.982.247-25',
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
            'referral_code_used' => $affiliate->referral_code ?: 'IND-TESTE1',
            'order_base_total' => 1000,
            'discount_pct' => 5,
            'discount_amount' => 50,
            'credit_pct' => 5,
            'credit_amount' => 50,
            'credit_status' => 'pending',
            'credit_available_at' => now(),
            'status' => 'active',
        ], $attributes));
    }
}
