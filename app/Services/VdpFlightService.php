<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VdpFlightService
{
    private ?LatamCrawlerService $crawlerService = null;
    private ?BdsCrawlerService $bdsCrawlerService = null;

    private function getCrawlerService(): LatamCrawlerService
    {
        if (! $this->crawlerService) {
            $this->crawlerService = app(LatamCrawlerService::class);
        }

        return $this->crawlerService;
    }

    private function getBdsCrawlerService(): BdsCrawlerService
    {
        if (! $this->bdsCrawlerService) {
            $this->bdsCrawlerService = app(BdsCrawlerService::class);
        }

        return $this->bdsCrawlerService;
    }

    private function getProvidersForCia(string $cia): array
    {
        $normalized = $this->normalizeCia(strtolower(trim($cia)));
        $default = $normalized === 'latam' ? ['latam_crawler'] : ['vdp'];
        $value = Setting::get("provider_{$normalized}", $default);

        if (is_string($value)) {
            return $value === 'disabled' || $value === '' ? [] : [$value];
        }

        return is_array($value) ? array_values($value) : [];
    }

    private function getProviderForCia(string $cia): string
    {
        $providers = $this->getProvidersForCia($cia);

        return $providers[0] ?? 'disabled';
    }

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

        $outboundFlight = null;
        $inboundFlight = null;

        $obProvider = $this->getProviderForCia($ida['cia']);
        $obResponse = $this->callForProvider($obProvider, array_merge($basePax, ['cia' => $ida['cia']]));
        $outboundFlight = $this->findByUniqueId($obResponse['outbound'] ?? [], $ida['unique_id']);

        if ($volta) {
            $ibProvider = $this->getProviderForCia($volta['cia']);
            if ($ibProvider === $obProvider && $ida['cia'] === $volta['cia']) {
                $inboundFlight = $this->findByUniqueId($obResponse['inbound'] ?? [], $volta['unique_id']);
            } else {
                $ibResponse = $this->callForProvider($ibProvider, array_merge($basePax, ['cia' => $volta['cia']]));
                $inboundFlight = $this->findByUniqueId($ibResponse['inbound'] ?? [], $volta['unique_id']);
            }
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
                'miles_price' => $this->parseMoneyValue($ida['miles_price']),
                'money_price' => $this->parseMoneyValue($ida['money_price']),
                'tax' => $this->parseMoneyValue($ida['tax']),
                ...$this->mapFlightData($outboundFlight),
            ],
        ];

        if ($volta && $inboundFlight) {
            $flights[] = [
                'direction' => 'inbound',
                'cia' => $volta['cia'],
                'miles_price' => $this->parseMoneyValue($volta['miles_price']),
                'money_price' => $this->parseMoneyValue($volta['money_price']),
                'tax' => $this->parseMoneyValue($volta['tax']),
                ...$this->mapFlightData($inboundFlight),
            ];
        }

        return $flights;
    }

    /**
     * Busca voos com cache de 30 minutos.
     * A chave inclui a versão de pricing para invalidação automática ao alterar preços.
     * Roteia cada cia para o fornecedor configurado e mescla os resultados.
     */
    public function searchFlights(array $params): array
    {
        $pricingVersion = Setting::get('pricing_version', '0');
        $cacheKey = 'vdp_search:' . $pricingVersion . ':' . md5(json_encode($params));

        $results = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($params) {
            return $this->fetchFromProviders($params);
        });

        $this->cacheDirectionPrices($params, $results, $pricingVersion);

        return $results;
    }

    /**
     * Cacheia preço mínimo por direção (outbound e inbound separados)
     * com chave simplificada para lookup do calendário.
     */
    private function cacheDirectionPrices(array $params, array $results, string $pricingVersion): void
    {
        $ttl = now()->addMinutes(30);
        $basePax = [
            'adults' => $params['adults'] ?? 1,
            'children' => $params['children'] ?? 0,
            'infants' => $params['infants'] ?? 0,
            'cabin' => $params['cabin'] ?? 'EC',
        ];

        $outbound = $results['outbound'] ?? [];
        if (! empty($outbound) && ! empty($params['outbound_date'])) {
            $minOb = null;
            foreach ($outbound as $ob) {
                $price = $this->calculateFlightPrice($ob);
                if ($minOb === null || $price < $minOb) {
                    $minOb = $price;
                }
            }
            if ($minOb !== null) {
                $obKey = $this->directionPriceKey($pricingVersion, $params['departure'] ?? '', $params['arrival'] ?? '', $params['outbound_date'], $basePax);
                Cache::put($obKey, round($minOb, 2), $ttl);
            }
        }

        $inbound = $results['inbound'] ?? [];
        if (! empty($inbound) && ! empty($params['inbound_date'])) {
            $minIb = null;
            foreach ($inbound as $ib) {
                $price = $this->calculateFlightPrice($ib);
                if ($minIb === null || $price < $minIb) {
                    $minIb = $price;
                }
            }
            if ($minIb !== null) {
                $ibKey = $this->directionPriceKey($pricingVersion, $params['arrival'] ?? '', $params['departure'] ?? '', $params['inbound_date'], $basePax);
                Cache::put($ibKey, round($minIb, 2), $ttl);
            }
        }
    }

    private function directionPriceKey(string $version, string $departure, string $arrival, string $date, array $pax): string
    {
        return 'vdp_dir_price:' . $version . ':' . md5(json_encode([
            $departure, $arrival, $date, $pax['cabin'], $pax['adults'], $pax['children'], $pax['infants'],
        ]));
    }

    /**
     * Busca voos retornando se o resultado veio do cache.
     * @return array{data: array, from_cache: bool}
     */
    public function searchFlightsWithCacheInfo(array $params): array
    {
        $pricingVersion = Setting::get('pricing_version', '0');
        $cacheKey = 'vdp_search:' . $pricingVersion . ':' . md5(json_encode($params));

        $fromCache = Cache::has($cacheKey);
        $data = $this->searchFlights($params);

        return ['data' => $data, 'from_cache' => $fromCache];
    }

    /**
     * Retorna o menor preço total (ida + volta se roundtrip) a partir do cache.
     * Não faz chamada à API — apenas lê o cache existente.
     * Faz fallback para cache de preço por direção se a busca completa não existir.
     */
    public function getMinPriceFromCache(array $params): ?float
    {
        $pricingVersion = Setting::get('pricing_version', '0');
        $cacheKey = 'vdp_search:' . $pricingVersion . ':' . md5(json_encode($params));

        $results = Cache::get($cacheKey);

        if ($results) {
            $outbound = $results['outbound'] ?? [];
            $inbound = $results['inbound'] ?? [];

            if (! empty($outbound)) {
                $minOb = null;
                foreach ($outbound as $ob) {
                    $price = $this->calculateFlightPrice($ob);
                    if ($minOb === null || $price < $minOb) {
                        $minOb = $price;
                    }
                }

                if ($minOb !== null) {
                    if (! empty($params['inbound_date']) && ! empty($inbound)) {
                        $minIb = null;
                        foreach ($inbound as $ib) {
                            $price = $this->calculateFlightPrice($ib);
                            if ($minIb === null || $price < $minIb) {
                                $minIb = $price;
                            }
                        }
                        if ($minIb !== null) {
                            return round($minOb + $minIb, 2);
                        }
                    }

                    return round($minOb, 2);
                }
            }
        }

        if (! empty($params['outbound_date'])) {
            $dirKey = $this->directionPriceKey($pricingVersion, $params['departure'] ?? '', $params['arrival'] ?? '', $params['outbound_date'], [
                'adults' => $params['adults'] ?? 1,
                'children' => $params['children'] ?? 0,
                'infants' => $params['infants'] ?? 0,
                'cabin' => $params['cabin'] ?? 'EC',
            ]);

            return Cache::get($dirKey);
        }

        return null;
    }

    /**
     * Roteia a busca para os fornecedores configurados e mescla resultados.
     * VDP, LATAM Crawler e BDS Crawler rodam em paralelo quando multiplos estao ativos.
     */
    private function fetchFromProviders(array $params): array
    {
        $cia = strtolower($params['cia'] ?? 'all');
        $allCias = ['gol', 'azul', 'latam'];

        if ($cia !== 'all') {
            $normalized = $this->normalizeCia($cia);
            $providers = $this->getProvidersForCia($normalized);

            if (empty($providers)) {
                return ['outbound' => [], 'inbound' => []];
            }

            if (count($providers) === 1) {
                if ($providers[0] === 'bds_crawler') {
                    return $this->getBdsCrawlerService()->searchFlights($params, [strtoupper($normalized)]);
                }
                return $this->callForProvider($providers[0], $params);
            }

            $vdpCias = [];
            $crawlerCias = [];
            $bdsCias = [];
            foreach ($providers as $p) {
                if ($p === 'vdp') { $vdpCias[] = $normalized; }
                elseif ($p === 'latam_crawler') { $crawlerCias[] = $normalized; }
                elseif ($p === 'bds_crawler') { $bdsCias[] = $normalized; }
            }

            return $this->fetchParallel($params, $vdpCias, $crawlerCias, $bdsCias);
        }

        $vdpCias = [];
        $crawlerCias = [];
        $bdsCias = [];

        foreach ($allCias as $c) {
            $providers = $this->getProvidersForCia($c);

            foreach ($providers as $provider) {
                if ($provider === 'latam_crawler') {
                    $crawlerCias[] = $c;
                } elseif ($provider === 'bds_crawler') {
                    $bdsCias[] = $c;
                } elseif ($provider === 'vdp') {
                    $vdpCias[] = $c;
                }
            }
        }

        $vdpCias = array_unique($vdpCias);
        $crawlerCias = array_unique($crawlerCias);
        $bdsCias = array_unique($bdsCias);

        $patriaEnabled = (bool) Setting::get('bds_patria_enabled', false);
        if ($patriaEnabled) {
            $bdsCias[] = 'patria';
        }

        $needVdp = ! empty($vdpCias);
        $needCrawler = ! empty($crawlerCias);
        $needBds = ! empty($bdsCias);
        $providerCount = ($needVdp ? 1 : 0) + ($needCrawler ? 1 : 0) + ($needBds ? 1 : 0);

        if ($providerCount > 1 || count($bdsCias) > 1) {
            return $this->fetchParallel($params, $vdpCias, $crawlerCias, $bdsCias);
        }

        if ($needVdp) {
            try {
                return $this->callApi($params);
            } catch (\Throwable $e) {
                Log::warning('VDP API: falha na busca', ['error' => $e->getMessage()]);
                return ['outbound' => [], 'inbound' => []];
            }
        }

        if ($needCrawler) {
            try {
                return $this->getCrawlerService()->searchFlights($params);
            } catch (\Throwable $e) {
                Log::warning('LatamCrawler: falha na busca', ['error' => $e->getMessage()]);
                return ['outbound' => [], 'inbound' => []];
            }
        }

        if ($needBds) {
            try {
                return $this->getBdsCrawlerService()->searchFlights($params, array_map('strtoupper', $bdsCias));
            } catch (\Throwable $e) {
                Log::warning('BdsCrawler: falha na busca', ['error' => $e->getMessage()]);
                return ['outbound' => [], 'inbound' => []];
            }
        }

        return ['outbound' => [], 'inbound' => []];
    }

    /**
     * Executa provedores em paralelo via Http::pool() (curl_multi).
     * BDS Crawler faz uma chamada por cia (todas em paralelo).
     */
    private function fetchParallel(array $params, array $vdpCias, array $crawlerCias = [], array $bdsCias = []): array
    {
        $vdpUrl = rtrim(config('services.vdp.url'), '/') . '/api/search/flights';
        $crawlerUrl = rtrim(config('services.latam_crawler.url', ''), '/');
        $crawlerKey = config('services.latam_crawler.api_key', '');
        $bdsUrl = rtrim(config('services.bds_crawler.url', ''), '/');
        $bdsKey = config('services.bds_crawler.api_key', '');
        $crawlerService = $this->getCrawlerService();

        $cabin = $params['cabin'] ?? 'EC';
        $crawlerCabin = $crawlerService->mapCabin($cabin);
        $hasInbound = ! empty($params['inbound_date']);

        $needVdp = ! empty($vdpCias);
        $needCrawler = ! empty($crawlerCias);
        $needBds = ! empty($bdsCias);

        $crawlerBaseQuery = [
            'adt' => $params['adults'] ?? 1,
            'chd' => $params['children'] ?? 0,
            'inf' => $params['infants'] ?? 0,
            'cabin' => $crawlerCabin,
        ];

        $bdsBaseQuery = [
            'origin' => $params['departure'] ?? '',
            'destination' => $params['arrival'] ?? '',
            'outbound_date' => $params['outbound_date'] ?? '',
            'adults' => $params['adults'] ?? 1,
            'children' => $params['children'] ?? 0,
            'infants' => $params['infants'] ?? 0,
            'cabin' => $cabin,
        ];
        if ($hasInbound) {
            $bdsBaseQuery['inbound_date'] = $params['inbound_date'];
        }

        $bdsHeaders = ['Accept' => 'application/json'];
        if (! empty($bdsKey)) {
            $bdsHeaders['X-API-KEY'] = $bdsKey;
        }

        $vdpTimeout = $this->getVdpTimeout();
        $crawlerTimeout = $this->getCrawlerTimeout();
        $bdsTimeout = $this->getBdsTimeout();

        $responses = Http::pool(function ($pool) use (
            $needVdp, $vdpUrl, $params, $vdpTimeout,
            $needCrawler, $crawlerUrl, $crawlerKey, $crawlerBaseQuery, $crawlerTimeout,
            $needBds, $bdsUrl, $bdsHeaders, $bdsTimeout, $bdsCias, $bdsBaseQuery,
            $hasInbound,
        ) {
            if ($needVdp) {
                $pool->as('vdp')
                    ->acceptJson()
                    ->timeout($vdpTimeout)
                    ->post($vdpUrl, $params);
            }

            if ($needCrawler) {
                $pool->as('crawler_ob')
                    ->withHeaders(['X-API-KEY' => $crawlerKey])
                    ->acceptJson()
                    ->timeout($crawlerTimeout)
                    ->get("{$crawlerUrl}/v1/miles", array_merge($crawlerBaseQuery, [
                        'ori' => $params['departure'] ?? '',
                        'dst' => $params['arrival'] ?? '',
                        'outbound_date' => $params['outbound_date'] ?? '',
                    ]));

                if ($hasInbound) {
                    $pool->as('crawler_ib')
                        ->withHeaders(['X-API-KEY' => $crawlerKey])
                        ->acceptJson()
                        ->timeout($crawlerTimeout)
                        ->get("{$crawlerUrl}/v1/miles", array_merge($crawlerBaseQuery, [
                            'ori' => $params['arrival'] ?? '',
                            'dst' => $params['departure'] ?? '',
                            'outbound_date' => $params['inbound_date'],
                        ]));
                }
            }

            if ($needBds) {
                foreach ($bdsCias as $cia) {
                    $pool->as('bds_' . $cia)
                        ->withHeaders($bdsHeaders)
                        ->timeout($bdsTimeout)
                        ->get("{$bdsUrl}/api/search", array_merge($bdsBaseQuery, [
                            'airlines' => strtoupper($cia),
                        ]));
                }
            }
        });

        $outbound = [];
        $inbound = [];

        if ($needVdp) {
            $vdpResponse = $responses['vdp'] ?? null;
            if ($vdpResponse && ! ($vdpResponse instanceof \Throwable) && $vdpResponse->successful()) {
                $vdpData = $vdpResponse->json();
                $outbound = $this->filterByCias($vdpData['outbound'] ?? [], $vdpCias);
                $inbound = $this->filterByCias($vdpData['inbound'] ?? [], $vdpCias);
            } elseif ($vdpResponse instanceof \Throwable) {
                Log::warning('VDP API: falha na busca paralela', ['error' => $vdpResponse->getMessage()]);
            } elseif ($vdpResponse && $vdpResponse->failed()) {
                Log::warning('VDP API: erro na busca paralela', ['status' => $vdpResponse->status()]);
            }
        }

        if ($needCrawler) {
            $crawlerOb = $crawlerService->transformResponse($responses['crawler_ob'] ?? null, $cabin);
            $outbound = array_merge($outbound, $crawlerOb);

            if ($hasInbound) {
                $crawlerIb = $crawlerService->transformResponse($responses['crawler_ib'] ?? null, $cabin);
                $inbound = array_merge($inbound, $crawlerIb);
            }
        }

        $patriaOutbound = [];
        $patriaInbound = [];

        if ($needBds) {
            foreach ($bdsCias as $cia) {
                $bdsResponse = $responses['bds_' . $cia] ?? null;
                if ($bdsResponse && ! ($bdsResponse instanceof \Throwable) && $bdsResponse->successful()) {
                    $bdsData = $bdsResponse->json();
                    if ($cia === 'patria') {
                        $patriaOutbound = array_merge($patriaOutbound, $bdsData['outbound'] ?? []);
                        $patriaInbound = array_merge($patriaInbound, $bdsData['inbound'] ?? []);
                    } else {
                        $outbound = array_merge($outbound, $bdsData['outbound'] ?? []);
                        $inbound = array_merge($inbound, $bdsData['inbound'] ?? []);
                    }
                } elseif ($bdsResponse instanceof \Throwable) {
                    Log::warning("BdsCrawler [{$cia}]: falha na busca paralela", ['error' => $bdsResponse->getMessage()]);
                } elseif ($bdsResponse && $bdsResponse->failed()) {
                    Log::warning("BdsCrawler [{$cia}]: erro na busca paralela", ['status' => $bdsResponse->status()]);
                }
            }
        }

        if (! empty($patriaOutbound)) {
            $outbound = $this->mergeWithPatria($outbound, $patriaOutbound);
        }
        if (! empty($patriaInbound)) {
            $inbound = $this->mergeWithPatria($inbound, $patriaInbound);
        }

        return ['outbound' => $outbound, 'inbound' => $inbound];
    }

    /**
     * Mescla voos regulares com PATRIA (convencionais).
     * Se o mesmo voo (flight_number + departure_time) existir em ambos,
     * mantem o mais barato segundo calculateFlightPrice().
     * Voos exclusivos PATRIA sao adicionados.
     * Multiplas tarifas PATRIA do mesmo voo sao deduplicadas (mais barata).
     */
    private function mergeWithPatria(array $regularFlights, array $patriaFlights): array
    {
        $patriaIndex = [];
        foreach ($patriaFlights as $pf) {
            $key = ($pf['flight_number'] ?? '') . '|' . ($pf['departure_time'] ?? '');
            $price = $this->calculateFlightPrice($pf);
            if (! isset($patriaIndex[$key]) || $price < $patriaIndex[$key]['price']) {
                $patriaIndex[$key] = ['flight' => $pf, 'price' => $price];
            }
        }

        $usedKeys = [];
        $merged = [];
        foreach ($regularFlights as $rf) {
            $key = ($rf['flight_number'] ?? '') . '|' . ($rf['departure_time'] ?? '');
            if (isset($patriaIndex[$key])) {
                $regularPrice = $this->calculateFlightPrice($rf);
                if ($patriaIndex[$key]['price'] < $regularPrice) {
                    $merged[] = $patriaIndex[$key]['flight'];
                    Log::debug('Patria merge: substituindo voo', [
                        'flight' => $key,
                        'regular_price' => round($regularPrice, 2),
                        'patria_price' => round($patriaIndex[$key]['price'], 2),
                    ]);
                } else {
                    $merged[] = $rf;
                }
                $usedKeys[$key] = true;
            } else {
                $merged[] = $rf;
            }
        }

        foreach ($patriaIndex as $key => $entry) {
            if (! isset($usedKeys[$key])) {
                $merged[] = $entry['flight'];
            }
        }

        return $merged;
    }

    /**
     * Chama o fornecedor correto para um unico provider.
     */
    private function callForProvider(string $provider, array $params): array
    {
        if ($provider === 'latam_crawler') {
            return $this->getCrawlerService()->searchFlights($params);
        }

        if ($provider === 'bds_crawler') {
            $cia = strtoupper($this->normalizeCia($params['cia'] ?? ''));
            return $this->getBdsCrawlerService()->searchFlights($params, $cia ? [$cia] : null);
        }

        return $this->callApi($params);
    }

    /**
     * Filtra voos mantendo apenas os das cias informadas.
     */
    private function filterByCias(array $flights, array $cias): array
    {
        return array_values(array_filter($flights, function ($flight) use ($cias) {
            $op = $this->normalizeCia($flight['operator'] ?? '');

            return in_array($op, $cias);
        }));
    }

    /**
     * Retorna a lista de provedores ativos (com cia e chave) para busca progressiva.
     * Cada entrada: ['key' => 'bds_gol', 'provider' => 'bds_crawler', 'airlines' => 'GOL']
     */
    public function getActiveProviderSlots(): array
    {
        $allCias = ['gol', 'azul', 'latam'];
        $slots = [];
        $vdpCias = [];

        foreach ($allCias as $c) {
            $providers = $this->getProvidersForCia($c);

            foreach ($providers as $provider) {
                if ($provider === 'vdp') {
                    $vdpCias[] = $c;
                } elseif ($provider === 'latam_crawler') {
                    $slots[] = ['provider' => 'latam_crawler', 'airlines' => strtoupper($c), 'patria' => false];
                } elseif ($provider === 'bds_crawler') {
                    $slots[] = ['provider' => 'bds_crawler', 'airlines' => strtoupper($c), 'patria' => false];
                }
            }
        }

        $vdpCias = array_unique($vdpCias);
        if (! empty($vdpCias)) {
            $slots[] = ['provider' => 'vdp', 'airlines' => implode(',', array_map('strtoupper', $vdpCias)), 'patria' => false];
        }

        if ((bool) Setting::get('bds_patria_enabled', false)) {
            $slots[] = ['provider' => 'bds_crawler', 'airlines' => 'PATRIA', 'patria' => true];
        }

        return $slots;
    }

    /**
     * Busca voos de UM unico provedor com cache individual.
     */
    public function searchSingleProvider(array $params, string $provider, string $airlines): array
    {
        $pricingVersion = Setting::get('pricing_version', '0');
        $cacheKey = 'vdp_prov:' . $pricingVersion . ':' . $provider . ':' . strtolower($airlines) . ':' . md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($params, $provider, $airlines) {
            return $this->fetchSingleProvider($params, $provider, $airlines);
        });
    }

    private function fetchSingleProvider(array $params, string $provider, string $airlines): array
    {
        try {
            if ($provider === 'vdp') {
                $result = $this->callApi($params);
                $airlineList = array_map('trim', explode(',', strtolower($airlines)));
                return [
                    'outbound' => $this->filterByCias($result['outbound'] ?? [], $airlineList),
                    'inbound' => $this->filterByCias($result['inbound'] ?? [], $airlineList),
                ];
            }

            if ($provider === 'latam_crawler') {
                return $this->getCrawlerService()->searchFlights($params);
            }

            if ($provider === 'bds_crawler') {
                $ciaList = array_map('trim', explode(',', strtoupper($airlines)));
                $bdsUrl = rtrim(config('services.bds_crawler.url', ''), '/');
                $bdsKey = config('services.bds_crawler.api_key', '');
                $bdsTimeout = $this->getBdsTimeout();

                $headers = ['Accept' => 'application/json'];
                if (! empty($bdsKey)) {
                    $headers['X-API-KEY'] = $bdsKey;
                }

                $query = [
                    'origin' => $params['departure'] ?? '',
                    'destination' => $params['arrival'] ?? '',
                    'outbound_date' => $params['outbound_date'] ?? '',
                    'adults' => $params['adults'] ?? 1,
                    'children' => $params['children'] ?? 0,
                    'infants' => $params['infants'] ?? 0,
                    'cabin' => $params['cabin'] ?? 'EC',
                    'airlines' => implode(',', $ciaList),
                ];
                if (! empty($params['inbound_date'])) {
                    $query['inbound_date'] = $params['inbound_date'];
                }

                $response = Http::withHeaders($headers)
                    ->timeout($bdsTimeout)
                    ->get("{$bdsUrl}/api/search", $query);

                if ($response->failed()) {
                    Log::warning("BDS single provider failed", ['status' => $response->status(), 'airlines' => $airlines]);
                    return ['outbound' => [], 'inbound' => []];
                }

                $data = $response->json();
                return [
                    'outbound' => $data['outbound'] ?? [],
                    'inbound' => $data['inbound'] ?? [],
                ];
            }

            return ['outbound' => [], 'inbound' => []];
        } catch (\Throwable $e) {
            Log::warning("Single provider search failed [{$provider}/{$airlines}]", ['error' => $e->getMessage()]);
            return ['outbound' => [], 'inbound' => []];
        }
    }

    /**
     * Retorna configs de pricing para o frontend JS poder calcular precos.
     */
    public function getPricingConfig(): array
    {
        return [
            'miles_enabled' => (bool) Setting::get('pricing_miles_enabled', false),
            'pct_enabled' => (bool) Setting::get('pricing_pct_enabled', false),
            'miles_gol' => (float) Setting::get('pricing_miles_gol', 30),
            'miles_azul' => (float) Setting::get('pricing_miles_azul', 30),
            'miles_latam' => (float) Setting::get('pricing_miles_latam', 30),
            'pct_gol' => (float) Setting::get('pricing_pct_gol', 100),
            'pct_azul' => (float) Setting::get('pricing_pct_azul', 100),
            'pct_latam' => (float) Setting::get('pricing_pct_latam', 100),
        ];
    }

    /**
     * Busca voos direto na API (sem cache) para revalidação de preço.
     * Roteia para o fornecedor configurado.
     */
    public function searchFlightsFresh(array $params): array
    {
        return $this->fetchFromProviders($params);
    }

    /**
     * Re-consulta um voo específico pelo unique_id e retorna o voo atualizado com preço atual.
     * Retorna null se o voo não existir mais.
     */
    public function revalidateFlight(array $searchParams, string $uniqueId, string $direction): ?array
    {
        try {
            $results = $this->searchFlightsFresh($searchParams);
            $flights = $results[$direction] ?? [];

            return $this->findByUniqueId($flights, $uniqueId);
        } catch (\Throwable $e) {
            Log::warning('VDP: falha ao revalidar voo', [
                'unique_id' => $uniqueId,
                'direction' => $direction,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Revalida ida e volta roteando para o fornecedor configurado de cada cia.
     *
     * @return array{outbound: ?array, inbound: ?array}
     */
    public function revalidateFlightPair(
        array $baseParams,
        array $obOriginal,
        ?array $ibOriginal = null,
    ): array {
        try {
            [$obProvider, $obParams] = $this->resolveRevalidationParams($baseParams, $obOriginal);

            Log::info('Revalidating flights', [
                'ob_provider' => $obProvider,
                'ob_cia' => $obParams['cia'] ?? 'all',
                'ob_unique_id' => $obOriginal['unique_id'] ?? '',
            ]);

            $obResults = $this->callForProvider($obProvider, $obParams);
            $freshOb = $this->findFlight(
                $obResults['outbound'] ?? [],
                $obOriginal['unique_id'] ?? '',
                $obOriginal['flight_number'] ?? '',
                $obOriginal['departure_time'] ?? '',
            );

            $freshIb = null;
            if ($ibOriginal) {
                [$ibProvider, $ibParams] = $this->resolveRevalidationParams($baseParams, $ibOriginal);
                $sameSource = $obProvider === $ibProvider
                    && strtolower($obParams['cia'] ?? '') === strtolower($ibParams['cia'] ?? '');

                if ($sameSource) {
                    $freshIb = $this->findFlight(
                        $obResults['inbound'] ?? [],
                        $ibOriginal['unique_id'] ?? '',
                        $ibOriginal['flight_number'] ?? '',
                        $ibOriginal['departure_time'] ?? '',
                    );
                } else {
                    $ibResults = $this->callForProvider($ibProvider, $ibParams);
                    $freshIb = $this->findFlight(
                        $ibResults['inbound'] ?? [],
                        $ibOriginal['unique_id'] ?? '',
                        $ibOriginal['flight_number'] ?? '',
                        $ibOriginal['departure_time'] ?? '',
                    );
                }
            }

            return ['outbound' => $freshOb, 'inbound' => $freshIb];
        } catch (\Throwable $e) {
            Log::warning('VDP: falha ao revalidar par de voos', [
                'ob_unique_id' => $obOriginal['unique_id'] ?? '',
                'error' => $e->getMessage(),
            ]);

            return ['outbound' => null, 'inbound' => null];
        }
    }

    /**
     * Determina provider e params corretos para revalidação,
     * usando _source_provider/_source_airlines quando disponíveis.
     */
    private function resolveRevalidationParams(array $baseParams, array $flightData): array
    {
        $sourceProvider = $flightData['_source_provider'] ?? null;
        $sourceAirlines = $flightData['_source_airlines'] ?? null;

        if ($sourceProvider && $sourceAirlines) {
            $cia = strtolower($sourceAirlines);
            return [$sourceProvider, array_merge($baseParams, ['cia' => $cia])];
        }

        $operator = $flightData['operator'] ?? 'all';
        $provider = $this->getProviderForCia($operator);
        return [$provider, array_merge($baseParams, ['cia' => strtolower($operator)])];
    }

    private function getVdpTimeout(): int
    {
        return (int) Setting::get('vdp_timeout', 35);
    }

    private function getCrawlerTimeout(): int
    {
        return (int) Setting::get('crawler_timeout', 35);
    }

    private function getBdsTimeout(): int
    {
        return (int) Setting::get('bds_crawler_timeout', 60);
    }

    private function callApi(array $params): array
    {
        $url = rtrim(config('services.vdp.url'), '/') . '/api/search/flights';

        Log::info('VDP API request', ['url' => $url, 'params' => $params]);

        $response = Http::acceptJson()
            ->timeout($this->getVdpTimeout())
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

    private function findFlight(array $flights, string $uniqueId, string $flightNumber = '', string $departureTime = ''): ?array
    {
        foreach ($flights as $flight) {
            if (($flight['unique_id'] ?? null) === $uniqueId) {
                return $flight;
            }
        }

        if ($flightNumber && $departureTime) {
            Log::debug('findFlight: unique_id nao encontrado, tentando por flight_number+departure_time', [
                'unique_id' => $uniqueId,
                'flight_number' => $flightNumber,
                'departure_time' => $departureTime,
                'total_flights' => count($flights),
            ]);

            foreach ($flights as $flight) {
                if (($flight['flight_number'] ?? '') === $flightNumber
                    && ($flight['departure_time'] ?? '') === $departureTime) {
                    return $flight;
                }
            }
        }

        return null;
    }

    /**
     * Converte valor monetário em formato BR ("1.151" ou "1.151,50") para decimal padrão ("1151" ou "1151.50").
     */
    public function parseMoneyValue(string $value): string
    {
        $value = trim($value);

        if (str_contains($value, ',')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        if (preg_match('/\.\d{3}$/', $value)) {
            return str_replace('.', '', $value);
        }

        return $value;
    }

    /**
     * Calcula o preço total de um voo (base + taxa) usando a precificação configurada.
     * Prioridade: milhas > percentual > preço original da API.
     */
    public function calculateFlightPrice(array $flight): float
    {
        $cia = $this->normalizeCia($flight['operator'] ?? '');
        $tax = $this->parseMoneyFloat($flight['boarding_tax'] ?? '0');

        $milesEnabled = Setting::get('pricing_miles_enabled', false);
        $pctEnabled = Setting::get('pricing_pct_enabled', false);

        if ($milesEnabled) {
            $miles = $this->parseMilesValue($flight['price_miles'] ?? null);
            if ($miles > 0) {
                $valorMilheiro = (float) Setting::get("pricing_miles_{$cia}", 30);
                $price = ($miles / 1000) * $valorMilheiro + $tax;

                Log::debug('Pricing: milhas', [
                    'cia' => $cia, 'miles' => $miles,
                    'valor_milheiro' => $valorMilheiro, 'tax' => $tax, 'price' => $price,
                ]);

                return $price;
            }
        }

        if ($pctEnabled) {
            $money = $this->parseMoneyFloat($flight['price_money'] ?? '0');
            $pct = (float) Setting::get("pricing_pct_{$cia}", 100);
            $price = $money * (1 + $pct / 100) + $tax;

            Log::debug('Pricing: percentual', [
                'cia' => $cia, 'money' => $money, 'pct' => $pct,
                'tax' => $tax, 'price' => $price,
                'miles_raw' => $flight['price_miles'] ?? null,
            ]);

            return $price;
        }

        $money = $this->parseMoneyFloat($flight['price_money'] ?? '0');

        return $money + $tax;
    }

    /**
     * Calcula o preço base de um voo (sem taxa) para gravar no pedido.
     */
    public function calculateBasePrice(array $flight): string
    {
        $cia = $this->normalizeCia($flight['operator'] ?? '');

        $milesEnabled = Setting::get('pricing_miles_enabled', false);
        $pctEnabled = Setting::get('pricing_pct_enabled', false);

        if ($milesEnabled) {
            $miles = $this->parseMilesValue($flight['price_miles'] ?? null);
            if ($miles > 0) {
                $valorMilheiro = (float) Setting::get("pricing_miles_{$cia}", 30);

                return number_format(($miles / 1000) * $valorMilheiro, 2, '.', '');
            }
        }

        if ($pctEnabled) {
            $money = $this->parseMoneyFloat($flight['price_money'] ?? '0');
            $pct = (float) Setting::get("pricing_pct_{$cia}", 100);

            return number_format($money * (1 + $pct / 100), 2, '.', '');
        }

        return $this->parseMoneyValue($flight['price_money'] ?? '0');
    }

    /**
     * Converte price_miles da API para float. Aceita formatos como "10.500", "10500", "10,500", etc.
     */
    private function parseMilesValue(mixed $value): float
    {
        if ($value === null || $value === '' || $value === '0') {
            return 0;
        }

        $str = (string) $value;
        $clean = preg_replace('/[^\d.,]/', '', $str);

        if ($clean === '' || $clean === '0') {
            return 0;
        }

        return $this->parseMoneyFloat($clean);
    }

    private function parseMoneyFloat(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        return (float) str_replace(['.', ','], ['', '.'], $value);
    }

    private function normalizeCia(string $operator): string
    {
        $map = [
            'gol' => 'gol', 'g3' => 'gol',
            'azul' => 'azul', 'ad' => 'azul',
            'latam' => 'latam', 'la' => 'latam', 'jj' => 'latam',
        ];

        return $map[strtolower(trim($operator))] ?? strtolower(trim($operator));
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
