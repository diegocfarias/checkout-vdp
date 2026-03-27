<?php

namespace App\Http\Controllers;

use App\Http\Requests\FlightSearchRequest;
use App\Models\FlightSearch;
use App\Models\Order;
use App\Models\Setting;
use App\Services\VdpFlightService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FlightSearchController extends Controller
{
    public function __construct(
        private VdpFlightService $vdpService,
    ) {}

    public function index()
    {
        return view('search.home');
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

        $params = [
            'cia' => 'all',
            'departure' => strtoupper($validated['departure']),
            'arrival' => strtoupper($validated['arrival']),
            'outbound_date' => $validated['outbound_date'],
            'inbound_date' => $validated['trip_type'] === 'roundtrip' ? ($validated['inbound_date'] ?? null) : null,
            'adults' => (int) $validated['adults'],
            'children' => (int) $validated['children'],
            'infants' => (int) $validated['infants'],
            'cabin' => $validated['cabin'],
        ];

        try {
            $results = $this->vdpService->searchFlights($params);
        } catch (\RuntimeException $e) {
            Log::warning('FlightSearch: nenhum voo encontrado', [
                'search_id' => $flightSearch->id,
                'error' => $e->getMessage(),
            ]);

            $flightSearch->update(['results_count' => 0]);

            return view('search.results', [
                'search' => $flightSearch,
                'groups' => [],
                'airlines' => [],
                'params' => $validated,
                'isRoundtrip' => $validated['trip_type'] === 'roundtrip',
                'mixEnabled' => Setting::get('mix_enabled', true),
            ]);
        }

        $outbound = $results['outbound'] ?? [];
        $inbound = $results['inbound'] ?? [];

        if (is_array($outbound) && ! isset($outbound[0]) && ! empty($outbound)) {
            $outbound = array_values($outbound);
        }
        if (is_array($inbound) && ! isset($inbound[0]) && ! empty($inbound)) {
            $inbound = array_values($inbound);
        }

        $isRoundtrip = $validated['trip_type'] === 'roundtrip' && count($inbound) > 0;
        $groups = $this->buildGroups($outbound, $isRoundtrip ? $inbound : []);

        $airlines = collect($groups)
            ->pluck('airlines')
            ->flatten()
            ->unique()
            ->filter()
            ->sort()
            ->values()
            ->all();

        $flightSearch->update(['results_count' => count($groups)]);

        return view('search.results', [
            'search' => $flightSearch,
            'groups' => $groups,
            'airlines' => $airlines,
            'params' => $validated,
            'isRoundtrip' => $isRoundtrip,
            'mixEnabled' => Setting::get('mix_enabled', true),
        ]);
    }

    private function buildGroups(array $outbound, array $inbound): array
    {
        $obByCiaPrice = [];
        foreach ($outbound as $ob) {
            $cia = strtoupper($ob['operator'] ?? '');
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
            $cia = strtoupper($ib['operator'] ?? '');
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
            $outboundData['unique_id'] ?? '',
            $outboundData['operator'] ?? 'all',
            $inboundData ? ($inboundData['unique_id'] ?? null) : null,
            $inboundData ? ($inboundData['operator'] ?? null) : null,
        );

        if (! $fresh['outbound']) {
            return back()->with('error', 'O voo de ida selecionado não está mais disponível. Por favor, faça uma nova busca.');
        }

        if ($inboundData && ! $fresh['inbound']) {
            return back()->with('error', 'O voo de volta selecionado não está mais disponível. Por favor, faça uma nova busca.');
        }

        $outboundData = $fresh['outbound'];
        if ($fresh['inbound']) {
            $inboundData = $fresh['inbound'];
        }

        $newObPrice = $this->parseFlightPrice($outboundData);
        $newIbPrice = $inboundData ? $this->parseFlightPrice($inboundData) : 0;
        $newTotal = round($newObPrice + $newIbPrice, 2);

        if (! $confirmed && abs($newTotal - $oldTotal) >= 0.01) {
            return view('search.price-changed', [
                'searchId' => $flightSearch->id,
                'outbound' => $outboundData,
                'inbound' => $inboundData,
                'oldTotal' => $oldTotal,
                'newTotal' => $newTotal,
                'diff' => round($newTotal - $oldTotal, 2),
            ]);
        }

        return $this->createOrderFromFlights($flightSearch, $outboundData, $inboundData, $request->userAgent());
    }

    private function createOrderFromFlights(FlightSearch $flightSearch, array $outboundData, ?array $inboundData, ?string $userAgent = null)
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

        $order->flights()->create($this->buildFlightRow('outbound', $outboundData));

        if ($inboundData) {
            $order->flights()->create($this->buildFlightRow('inbound', $inboundData));
        }

        return redirect()->route('checkout.show', $order->token);
    }

    private function buildFlightRow(string $direction, array $data): array
    {
        return [
            'direction' => $direction,
            'cia' => $data['operator'] ?? '',
            'operator' => $data['operator'] ?? null,
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
        ];
    }
}
