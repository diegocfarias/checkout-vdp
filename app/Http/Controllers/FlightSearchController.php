<?php

namespace App\Http\Controllers;

use App\Http\Requests\FlightSearchRequest;
use App\Models\FlightSearch;
use App\Models\Order;
use App\Models\Setting;
use App\Models\ShowcaseRoute;
use App\Services\VdpFlightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FlightSearchController extends Controller
{
    public function __construct(
        private VdpFlightService $vdpService,
    ) {}

    public function index()
    {
        $maxCards = (int) Setting::get('showcase_max_cards', 9);
        $sortMode = Setting::get('showcase_sort_mode', 'manual');

        $showcaseQuery = ShowcaseRoute::where('is_active', true)
            ->whereNotNull('cached_price');

        if ($sortMode === 'cheapest') {
            $showcaseQuery->orderBy('cached_price', 'asc');
        } else {
            $showcaseQuery->orderBy('sort_order');
        }

        $showcaseRoutes = $showcaseQuery->limit($maxCards)->get();

        return view('search.home', [
            'showcaseRoutes' => $showcaseRoutes,
            'pixDiscount' => (float) Setting::get('pix_discount', 0),
            'pixEnabled' => ! empty(Setting::get('gateway_pix')),
        ]);
    }

    public function datePrices(Request $request)
    {
        $request->validate([
            'departure' => 'required|string|size:3',
            'arrival' => 'required|string|size:3',
            'cabin' => 'required|string',
            'adults' => 'required|integer|min:1',
            'children' => 'required|integer|min:0',
            'infants' => 'required|integer|min:0',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'trip_type' => 'required|in:oneway,roundtrip',
            'inbound_offset' => 'nullable|integer|min:0',
        ]);

        $dateFrom = \Carbon\Carbon::parse($request->input('date_from'));
        $dateTo = \Carbon\Carbon::parse($request->input('date_to'));

        $maxDays = 335;
        if ($dateFrom->diffInDays($dateTo) > $maxDays) {
            $dateTo = $dateFrom->copy()->addDays($maxDays);
        }

        $departure = strtoupper($request->input('departure'));
        $arrival = strtoupper($request->input('arrival'));
        $cabin = $request->input('cabin');
        $adults = (int) $request->input('adults');
        $children = (int) $request->input('children');
        $infants = (int) $request->input('infants');
        $tripType = $request->input('trip_type');
        $inboundOffset = (int) $request->input('inbound_offset', 0);

        $prices = [];
        $current = $dateFrom->copy();

        while ($current->lte($dateTo)) {
            $dateStr = $current->format('Y-m-d');

            $params = [
                'cia' => 'all',
                'departure' => $departure,
                'arrival' => $arrival,
                'outbound_date' => $dateStr,
                'inbound_date' => ($tripType === 'roundtrip' && $inboundOffset > 0)
                    ? $current->copy()->addDays($inboundOffset)->format('Y-m-d')
                    : null,
                'adults' => $adults,
                'children' => $children,
                'infants' => $infants,
                'cabin' => $cabin,
            ];

            $prices[$dateStr] = $this->vdpService->getMinPriceFromCache($params);
            $current->addDay();
        }

        return response()->json(['prices' => $prices]);
    }

    public function showcasePrice(ShowcaseRoute $showcaseRoute)
    {
        $pixDiscount = (float) Setting::get('pix_discount', 0);
        $pixEnabled = ! empty(Setting::get('gateway_pix'));
        $price = (float) $showcaseRoute->cached_price;
        $pixPrice = ($pixEnabled && $pixDiscount > 0)
            ? round($price * (1 - $pixDiscount / 100), 2)
            : null;

        return response()->json([
            'price' => $price,
            'formatted_price' => $showcaseRoute->formattedPrice(),
            'pix_price' => $pixPrice,
            'formatted_pix_price' => $pixPrice ? 'R$ ' . number_format($pixPrice, 2, ',', '.') : null,
            'date' => $showcaseRoute->cached_date?->format('Y-m-d'),
            'return_date' => $showcaseRoute->cached_return_date?->format('Y-m-d'),
            'airline' => $showcaseRoute->cached_airline,
        ]);
    }

    public function search(FlightSearchRequest $request)
    {
        $validated = $request->validated();

        $flightSearch = FlightSearch::create([
            'departure_iata' => strtoupper($validated['departure']),
            'arrival_iata' => strtoupper($validated['arrival']),
            'outbound_date' => $validated['outbound_date'],
            'inbound_date' => $validated['inbound_date'] ?? null,
            'trip_type' => $validated['trip_type'],
            'cabin' => $validated['cabin'],
            'adults' => $validated['adults'],
            'children' => $validated['children'],
            'infants' => $validated['infants'],
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
        ]);

        $isRoundtrip = $validated['trip_type'] === 'roundtrip';

        return view('search.results', [
            'search' => $flightSearch,
            'params' => $validated,
            'isRoundtrip' => $isRoundtrip,
            'mixEnabled' => (bool) Setting::get('mix_enabled', true),
            'pixDiscount' => (float) Setting::get('pix_discount', 0),
            'pixEnabled' => ! empty(Setting::get('gateway_pix', config('services.payment.gateway'))),
            'providerSlots' => collect($this->vdpService->getActiveProviderSlots())->values()->map(fn ($slot, $i) => [
                'key' => 's' . $i,
                'token' => encrypt($slot['provider'] . '|' . $slot['airlines']),
                'p' => $slot['patria'],
            ])->all(),
            'maxInstallments' => (int) Setting::get('max_installments', 12),
        ]);
    }

    public function searchByProvider(Request $request): JsonResponse
    {
        $request->validate([
            'departure' => 'required|string|size:3',
            'arrival' => 'required|string|size:3',
            'outbound_date' => 'required|date',
            'inbound_date' => 'nullable|date',
            'adults' => 'required|integer|min:1',
            'children' => 'required|integer|min:0',
            'infants' => 'required|integer|min:0',
            'cabin' => 'required|string|in:EC,EX',
            'slot' => 'required|string',
        ]);

        try {
            $decoded = decrypt($request->input('slot'));
        } catch (\Throwable $e) {
            return response()->json(['outbound' => [], 'inbound' => []], 400);
        }

        [$provider, $airlines] = explode('|', $decoded, 2);

        if (! in_array($provider, ['vdp', 'latam_crawler', 'bds_crawler'], true)) {
            return response()->json(['outbound' => [], 'inbound' => []], 400);
        }

        $params = [
            'cia' => 'all',
            'departure' => strtoupper($request->input('departure')),
            'arrival' => strtoupper($request->input('arrival')),
            'outbound_date' => $request->input('outbound_date'),
            'inbound_date' => $request->input('inbound_date'),
            'adults' => (int) $request->input('adults'),
            'children' => (int) $request->input('children'),
            'infants' => (int) $request->input('infants'),
            'cabin' => $request->input('cabin'),
        ];

        try {
            $results = $this->vdpService->searchSingleProvider($params, $provider, $airlines);
        } catch (\Throwable $e) {
            Log::warning('searchByProvider failed', [
                'provider' => $provider,
                'airlines' => $airlines,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'outbound' => [],
                'inbound' => [],
                'provider' => $provider,
                'airlines' => $airlines,
            ]);
        }

        $isPatria = strtoupper($airlines) === 'PATRIA';
        $providerLabel = $this->providerLabel($provider, $airlines);

        $addMeta = function (array $flight) use ($provider, $airlines, $providerLabel, $isPatria): array {
            $flight = $this->sanitizeFlight($flight);
            $flight['calculated_price'] = round($this->vdpService->calculateFlightPrice($flight), 2);
            $flight['_provider'] = $providerLabel;
            $flight['_pricing_type'] = $this->resolvePricingType($flight, $isPatria);
            $flight['_source_provider'] = $provider;
            $flight['_source_airlines'] = $airlines;

            return $flight;
        };

        return response()->json([
            'outbound' => array_values(array_map($addMeta, $results['outbound'] ?? [])),
            'inbound' => array_values(array_map($addMeta, $results['inbound'] ?? [])),
        ]);
    }

    private function buildGroups(array $outbound, array $inbound): array
    {
        $outbound = array_map([$this, 'sanitizeFlight'], $outbound);
        $inbound = array_map([$this, 'sanitizeFlight'], $inbound);

        $obByCiaPrice = [];
        foreach ($outbound as $ob) {
            $cia = $this->resolveDisplayCia($ob['operator'] ?? '', $ob['flight_number'] ?? '');
            $price = $this->parseFlightPrice($ob);
            $key = $cia . '|' . number_format($price, 2, '.', '');
            $obByCiaPrice[$key] ??= ['cia' => $cia, 'price' => $price, 'flights' => []];
            $obByCiaPrice[$key]['flights'][] = $ob;
        }

        if (empty($inbound)) {
            $groups = [];
            foreach ($obByCiaPrice as $obGroup) {
                $hasDirect = $this->groupHasDirect($obGroup['flights']);
                $periods = $this->groupPeriods($obGroup['flights']);
                $groups[] = [
                    'airlines' => [$obGroup['cia']],
                    'same_cia' => true,
                    'total_price' => $obGroup['price'],
                    'outbound_flights' => $obGroup['flights'],
                    'inbound_flights' => [],
                    'direct' => $hasDirect,
                    'outbound_periods' => $periods,
                    'inbound_periods' => [],
                ];
            }
            usort($groups, fn ($a, $b) => $a['total_price'] <=> $b['total_price']);

            return $groups;
        }

        $ibByCiaPrice = [];
        foreach ($inbound as $ib) {
            $cia = $this->resolveDisplayCia($ib['operator'] ?? '', $ib['flight_number'] ?? '');
            $price = $this->parseFlightPrice($ib);
            $key = $cia . '|' . number_format($price, 2, '.', '');
            $ibByCiaPrice[$key] ??= ['cia' => $cia, 'price' => $price, 'flights' => []];
            $ibByCiaPrice[$key]['flights'][] = $ib;
        }

        $groups = [];
        foreach ($obByCiaPrice as $obGroup) {
            foreach ($ibByCiaPrice as $ibGroup) {
                $airlines = array_values(array_unique([$obGroup['cia'], $ibGroup['cia']]));
                $sameCia = $obGroup['cia'] === $ibGroup['cia'];
                $obDirect = $this->groupHasDirect($obGroup['flights']);
                $ibDirect = $this->groupHasDirect($ibGroup['flights']);

                $groups[] = [
                    'airlines' => $airlines,
                    'same_cia' => $sameCia,
                    'total_price' => round($obGroup['price'] + $ibGroup['price'], 2),
                    'outbound_flights' => $obGroup['flights'],
                    'inbound_flights' => $ibGroup['flights'],
                    'direct' => $obDirect && $ibDirect,
                    'outbound_periods' => $this->groupPeriods($obGroup['flights']),
                    'inbound_periods' => $this->groupPeriods($ibGroup['flights']),
                ];
            }
        }

        if (! Setting::get('mix_enabled', true)) {
            $groups = array_values(array_filter($groups, fn ($g) => $g['same_cia']));
        }

        usort($groups, fn ($a, $b) => $a['total_price'] <=> $b['total_price']);

        return array_slice($groups, 0, 200);
    }

    private function groupHasDirect(array $flights): bool
    {
        foreach ($flights as $f) {
            $conns = $f['connection'] ?? [];
            if (! is_array($conns) || count($conns) <= 1) {
                return true;
            }
        }

        return false;
    }

    private function groupPeriods(array $flights): array
    {
        $periods = [];
        foreach ($flights as $f) {
            $p = $this->getTimePeriod($f['departure_time'] ?? '');
            if (! in_array($p, $periods)) {
                $periods[] = $p;
            }
        }

        return $periods;
    }

    private function parseFlightPrice(array $flight): float
    {
        return $this->vdpService->calculateFlightPrice($flight);
    }

    private function parseDurationMinutes(string $duration): int
    {
        if (preg_match('/(\d+)h\s*(\d+)?/', $duration, $m)) {
            return ((int) $m[1]) * 60 + (int) ($m[2] ?? 0);
        }

        return 0;
    }

    private function getTimePeriod(string $time): string
    {
        $hour = (int) substr($time, 0, 2);

        if ($hour < 6) {
            return 'madrugada';
        }
        if ($hour < 12) {
            return 'manha';
        }
        if ($hour < 18) {
            return 'tarde';
        }

        return 'noite';
    }

    private function sanitizeFlight(array $flight): array
    {
        $allowed = [
            'operator', 'flight_number', 'departure_time', 'arrival_time',
            'departure_location', 'arrival_location', 'departure_label', 'arrival_label',
            'boarding_tax', 'class_service', 'price_money', 'price_miles', 'price_miles_vip',
            'total_flight_duration', 'unique_id', 'connection',
        ];

        $clean = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $flight)) {
                $clean[$key] = $flight[$key];
            }
        }

        if (isset($clean['connection']) && is_array($clean['connection'])) {
            $allowedConn = [
                'DEPARTURE_TIME', 'ARRIVAL_TIME', 'DEPARTURE_LOCATION', 'ARRIVAL_LOCATION',
                'FLIGHT_NUMBER', 'FLIGHT_DURATION', 'OP', 'TIME_WAITING',
            ];
            $clean['connection'] = array_map(function ($seg) use ($allowedConn) {
                return array_intersect_key($seg, array_flip($allowedConn));
            }, $clean['connection']);
        }

        return $clean;
    }

    public function select(Request $request)
    {
        $request->validate([
            'search_id' => 'required|uuid|exists:flight_searches,id',
            'outbound' => 'required|json',
            'inbound' => 'nullable|json',
            'confirmed' => 'nullable|in:1',
        ]);

        $flightSearch = FlightSearch::findOrFail($request->input('search_id'));
        $outboundData = json_decode($request->input('outbound'), true);
        $inboundData = $request->input('inbound') ? json_decode($request->input('inbound'), true) : null;
        $confirmed = $request->input('confirmed') === '1';

        if ($flightSearch->trip_type === 'roundtrip' && ! $inboundData) {
            return back()->with('error', 'Selecione também um voo de volta para continuar com a compra de ida e volta.');
        }

        $meta = [
            'ob_provider' => $request->input('ob_provider', ''),
            'ob_pricing_type' => $request->input('ob_pricing_type', ''),
            'ob_source_provider' => $outboundData['_source_provider'] ?? $request->input('ob_source_provider', ''),
            'ob_source_airlines' => $outboundData['_source_airlines'] ?? $request->input('ob_source_airlines', ''),
            'ib_provider' => $request->input('ib_provider', ''),
            'ib_pricing_type' => $request->input('ib_pricing_type', ''),
            'ib_source_provider' => ($inboundData['_source_provider'] ?? null) ?: $request->input('ib_source_provider', ''),
            'ib_source_airlines' => ($inboundData['_source_airlines'] ?? null) ?: $request->input('ib_source_airlines', ''),
        ];

        if ($confirmed) {
            $outboundData = $this->sanitizeFlight($outboundData);
            if ($inboundData) {
                $inboundData = $this->sanitizeFlight($inboundData);
            }
        } else {
            $oldObPrice = $this->parseFlightPrice($outboundData);
            $oldIbPrice = $inboundData ? $this->parseFlightPrice($inboundData) : 0;
            $oldTotal = round($oldObPrice + $oldIbPrice, 2);

            $baseParams = [
                'departure' => $flightSearch->departure_iata,
                'arrival' => $flightSearch->arrival_iata,
                'outbound_date' => $flightSearch->outbound_date instanceof \Carbon\Carbon
                    ? $flightSearch->outbound_date->format('Y-m-d')
                    : $flightSearch->outbound_date,
                'inbound_date' => $flightSearch->inbound_date
                    ? ($flightSearch->inbound_date instanceof \Carbon\Carbon
                        ? $flightSearch->inbound_date->format('Y-m-d')
                        : $flightSearch->inbound_date)
                    : null,
                'adults' => $flightSearch->adults,
                'children' => $flightSearch->children,
                'infants' => $flightSearch->infants,
                'cabin' => $flightSearch->cabin,
            ];

            $fresh = $this->vdpService->revalidateFlightPair(
                $baseParams,
                $outboundData,
                $inboundData,
            );

            if (! $fresh['outbound']) {
                return back()->with('error', 'O voo de ida selecionado não está mais disponível. Por favor, faça uma nova busca.');
            }

            if ($inboundData && ! $fresh['inbound']) {
                return back()->with('error', 'O voo de volta selecionado não está mais disponível. Por favor, faça uma nova busca.');
            }

            $outboundData = $this->sanitizeFlight($fresh['outbound']);
            if ($fresh['inbound']) {
                $inboundData = $this->sanitizeFlight($fresh['inbound']);
            }

            $newObPrice = $this->parseFlightPrice($outboundData);
            $newIbPrice = $inboundData ? $this->parseFlightPrice($inboundData) : 0;
            $newTotal = round($newObPrice + $newIbPrice, 2);

            if (abs($newTotal - $oldTotal) >= 0.01) {
                return view('search.price-changed', [
                    'searchId' => $flightSearch->id,
                    'outbound' => $outboundData,
                    'inbound' => $inboundData,
                    'oldTotal' => $oldTotal,
                    'newTotal' => $newTotal,
                    'diff' => round($newTotal - $oldTotal, 2),
                    'meta' => $meta,
                ]);
            }
        }

        return $this->createOrderFromFlights($flightSearch, $outboundData, $inboundData, $request->userAgent(), $meta);
    }

    private function createOrderFromFlights(FlightSearch $flightSearch, array $outboundData, ?array $inboundData, ?string $userAgent = null, array $meta = [])
    {
        $deviceType = 'desktop';
        if ($userAgent && preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
            $deviceType = 'mobile';
        }

        $order = Order::create([
            'total_adults' => $flightSearch->adults,
            'total_children' => $flightSearch->children,
            'total_babies' => $flightSearch->infants,
            'cabin' => $flightSearch->cabin,
            'departure_iata' => $flightSearch->departure_iata,
            'arrival_iata' => $flightSearch->arrival_iata,
            'flight_search_id' => $flightSearch->id,
            'device_type' => $deviceType,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(Setting::get('order_expiration_minutes', 30)),
        ]);

        $order->flights()->create($this->buildFlightRow('outbound', $outboundData, $meta));

        if ($inboundData) {
            $order->flights()->create($this->buildFlightRow('inbound', $inboundData, $meta));
        }

        return redirect()->route('checkout.show', $order->token);
    }

    private function buildFlightRow(string $direction, array $data, array $meta = []): array
    {
        $prefix = $direction === 'outbound' ? 'ob_' : 'ib_';
        $operator = $this->resolveDisplayCia($data['operator'] ?? '', $data['flight_number'] ?? '');

        return [
            'direction' => $direction,
            'cia' => $operator,
            'operator' => $operator,
            'flight_number' => $data['flight_number'] ?? null,
            'departure_time' => $data['departure_time'] ?? null,
            'arrival_time' => $data['arrival_time'] ?? null,
            'departure_location' => $data['departure_location'] ?? null,
            'arrival_location' => $data['arrival_location'] ?? null,
            'departure_label' => $data['departure_label'] ?? null,
            'arrival_label' => $data['arrival_label'] ?? null,
            'boarding_tax' => $data['boarding_tax'] ?? null,
            'class_service' => $data['class_service'] ?? null,
            'price_money' => $data['price_money'] ?? null,
            'price_miles' => $data['price_miles'] ?? null,
            'price_miles_vip' => $data['price_miles_vip'] ?? null,
            'total_flight_duration' => $data['total_flight_duration'] ?? null,
            'unique_id' => $data['unique_id'] ?? null,
            'connection' => $data['connection'] ?? null,
            'miles_price' => $data['price_miles'] ?? '0',
            'money_price' => $this->vdpService->calculateBasePrice($data),
            'tax' => $this->vdpService->parseMoneyValue($data['boarding_tax'] ?? '0'),
            'provider' => $meta[$prefix.'provider'] ?? null ?: null,
            'pricing_type' => $meta[$prefix.'pricing_type'] ?? null ?: null,
            'source_provider' => $meta[$prefix.'source_provider'] ?? null ?: null,
            'source_airlines' => $meta[$prefix.'source_airlines'] ?? null ?: null,
        ];
    }

    private function resolveDisplayCia(string $operator, string $flightNumber): string
    {
        $upper = strtoupper(trim($operator));
        if ($upper !== 'PATRIA') {
            return $operator;
        }

        $fn = strtoupper(trim($flightNumber));
        if (str_starts_with($fn, 'G3')) return 'GOL';
        if (str_starts_with($fn, 'AD')) return 'AZUL';
        if (str_starts_with($fn, 'LA') || str_starts_with($fn, 'JJ')) return 'LATAM';

        return $operator;
    }

    private function providerLabel(string $provider, string $airlines): string
    {
        return match ($provider) {
            'vdp' => 'VDP',
            'latam_crawler' => 'LATAM Crawler',
            'bds_crawler' => strtoupper($airlines) === 'PATRIA' ? 'BDS Convencional' : 'BDS Crawler',
            default => $provider,
        };
    }

    private function resolvePricingType(array $flight, bool $isPatria): string
    {
        if ($isPatria) {
            return 'convencional';
        }

        $raw = $flight['price_miles'] ?? null;
        $miles = (float) str_replace(['.', ','], ['', '.'], trim((string) $raw));

        return $miles > 0 ? 'milhas' : 'convencional';
    }
}
