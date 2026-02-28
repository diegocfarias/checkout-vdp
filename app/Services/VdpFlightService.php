<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VdpFlightService
{
    public function searchAndFilter(array $payload): array
    {
        $ida = $payload['ida'];
        $volta = ! empty($payload['volta']) ? $payload['volta'] : null;

        $basePax = [
            'adults' => (int) $payload['total_adults'],
            'children' => (int) $payload['total_children'],
            'infants' => (int) $payload['total_babies'],
            'cabin' => $payload['cabin'],
            'departure' => $payload['departure_iata'],
            'arrival' => $payload['arrival_iata'],
            'outbound_date' => $ida['outbound_date'],
            'inbound_date' => $volta['inbound_date'] ?? null,
        ];

        $sameCia = $volta && $ida['cia'] === $volta['cia'];
        $outboundFlight = null;
        $inboundFlight = null;

        if (! $volta || $sameCia) {
            $response = $this->callApi(array_merge($basePax, ['cia' => $ida['cia']]));

            $outboundFlight = $this->findByUniqueId($response['outbound'] ?? [], $ida['unique_id']);

            if ($volta) {
                $inboundFlight = $this->findByUniqueId($response['inbound'] ?? [], $volta['unique_id']);
            }
        } else {
            $outboundResponse = $this->callApi(array_merge($basePax, ['cia' => $ida['cia']]));
            $outboundFlight = $this->findByUniqueId($outboundResponse['outbound'] ?? [], $ida['unique_id']);

            $inboundResponse = $this->callApi(array_merge($basePax, ['cia' => $volta['cia']]));
            $inboundFlight = $this->findByUniqueId($inboundResponse['inbound'] ?? [], $volta['unique_id']);
        }

        if (! $outboundFlight) {
            throw new \RuntimeException('Voo de ida não encontrado para o unique_id informado.');
        }

        if ($volta && ! $inboundFlight) {
            throw new \RuntimeException('Voo de volta não encontrado para o unique_id informado.');
        }

        $flights = [
            [
                'direction' => 'outbound',
                'cia' => $ida['cia'],
                'miles_price' => $ida['miles_price'],
                'money_price' => $ida['money_price'],
                'tax' => $ida['tax'],
                ...$this->mapFlightData($outboundFlight),
            ],
        ];

        if ($volta && $inboundFlight) {
            $flights[] = [
                'direction' => 'inbound',
                'cia' => $volta['cia'],
                'miles_price' => $volta['miles_price'],
                'money_price' => $volta['money_price'],
                'tax' => $volta['tax'],
                ...$this->mapFlightData($inboundFlight),
            ];
        }

        return $flights;
    }

    private function callApi(array $params): array
    {
        $url = rtrim(config('services.vdp.url'), '/') . '/api/search/flights';

        Log::info('VDP API request', ['url' => $url, 'params' => $params]);

        $response = Http::acceptJson()
            ->timeout(30)
            ->post($url, $params);

        if ($response->failed()) {
            Log::error('VDP API retornou erro', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'request_params' => $params,
                'url' => $url,
            ]);

            throw new \RuntimeException(
                "VDP API retornou status {$response->status()}"
            );
        }

        return $response->json();
    }

    private function findByUniqueId(array $flights, string $uniqueId): ?array
    {
        foreach ($flights as $flight) {
            if (($flight['unique_id'] ?? null) === $uniqueId) {
                return $flight;
            }
        }

        return null;
    }

    private function mapFlightData(array $flight): array
    {
        return [
            'operator' => $flight['operator'] ?? null,
            'flight_number' => $flight['flight_number'] ?? null,
            'departure_time' => $flight['departure_time'] ?? null,
            'arrival_time' => $flight['arrival_time'] ?? null,
            'departure_location' => $flight['departure_location'] ?? null,
            'arrival_location' => $flight['arrival_location'] ?? null,
            'departure_label' => $flight['departure_label'] ?? null,
            'arrival_label' => $flight['arrival_label'] ?? null,
            'boarding_tax' => $flight['boarding_tax'] ?? null,
            'class_service' => $flight['class_service'] ?? null,
            'price_money' => $flight['price_money'] ?? null,
            'price_miles' => $flight['price_miles'] ?? null,
            'price_miles_vip' => $flight['price_miles_vip'] ?? null,
            'total_flight_duration' => $flight['total_flight_duration'] ?? null,
            'unique_id' => $flight['unique_id'] ?? null,
            'connection' => $flight['connection'] ?? null,
        ];
    }
}
