<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\VdpFlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SearchResultsViewRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
    }

    protected function tearDown(): void
    {
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_results_page_ships_direction_specific_filters_and_option_level_filtering(): void
    {
        $vdp = Mockery::mock(VdpFlightService::class);
        $vdp->shouldReceive('getActiveProviderSlots')
            ->once()
            ->andReturn([
                ['provider' => 'vdp', 'airlines' => 'GOL', 'patria' => false],
            ]);
        $this->app->instance(VdpFlightService::class, $vdp);

        $response = $this->get(route('search.results', [
            'trip_type' => 'roundtrip',
            'departure' => 'GIG',
            'arrival' => 'FOR',
            'outbound_date' => '2026-07-16',
            'inbound_date' => '2026-07-23',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
        ]));

        $response->assertOk()
            ->assertViewIs('search.results')
            ->assertSee('Companhia ida')
            ->assertSee('Companhia volta')
            ->assertSee('Paradas ida')
            ->assertSee('Paradas volta')
            ->assertSee('filter-ob-stops', false)
            ->assertSee('filter-ib-stops', false)
            ->assertSee('data-ob-has-direct', false)
            ->assertSee('data-ib-has-direct', false)
            ->assertSee('data-is-direct', false)
            ->assertSee('data-airline', false)
            ->assertSee('data-period', false)
            ->assertSee('function applyOptionFilters()', false)
            ->assertSee('option-filter-hidden', false)
            ->assertSee('syncSelectedFlight(firstVisible)', false);
    }
}
