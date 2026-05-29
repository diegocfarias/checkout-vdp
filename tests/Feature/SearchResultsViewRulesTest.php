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
            ->assertSee('syncSelectedFlight(firstVisible)', false)
            ->assertSee('Revalidando preço e disponibilidade')
            ->assertDontSee('Carregando detalhes do voo');
    }

    public function test_search_calendar_direction_labels_are_neutral(): void
    {
        $response = $this->get(route('search.home'));

        $response->assertOk()
            ->assertSee('whitespace-nowrap text-gray-600', false)
            ->assertSee('bg-gray-100 text-gray-700 text-xs font-semibold', false)
            ->assertSee('text-[10px] font-bold uppercase text-gray-500">IDA', false)
            ->assertSee('text-[10px] font-bold uppercase text-gray-500">VOLTA', false)
            ->assertDontSee('whitespace-nowrap text-blue-600', false)
            ->assertDontSee('bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1.5 rounded-full', false);
    }
}
