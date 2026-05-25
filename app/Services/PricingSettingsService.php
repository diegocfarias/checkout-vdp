<?php

namespace App\Services;

use App\Models\PricingChangeLog;
use App\Models\Setting;

class PricingSettingsService
{
    public const MILES_METHOD_MILHEIRO = 'miles_per_thousand';

    public const MILES_METHOD_TOTAL_PERCENTAGE = 'miles_total_percentage';

    public const MILES_METHOD_API_ORIGINAL = 'api_original';

    public const DEFAULT_MILES_PRIORITY = [
        self::MILES_METHOD_MILHEIRO,
        self::MILES_METHOD_TOTAL_PERCENTAGE,
        self::MILES_METHOD_API_ORIGINAL,
    ];

    public const SETTINGS_KEYS = [
        'pricing_miles_enabled',
        'pricing_miles_azul',
        'pricing_miles_gol',
        'pricing_miles_latam',
        'pricing_miles_pct_enabled',
        'pricing_miles_pct_azul',
        'pricing_miles_pct_gol',
        'pricing_miles_pct_latam',
        'pricing_miles_priority_order',
        'pricing_pct_enabled',
        'pricing_pct_azul',
        'pricing_pct_gol',
        'pricing_pct_latam',
        'boarding_tax_fallback_pct',
    ];

    public function settings(): array
    {
        return $this->normalize([
            'pricing_miles_enabled' => Setting::get('pricing_miles_enabled', true),
            'pricing_miles_azul' => Setting::get('pricing_miles_azul', '30.00'),
            'pricing_miles_gol' => Setting::get('pricing_miles_gol', '30.00'),
            'pricing_miles_latam' => Setting::get('pricing_miles_latam', '30.00'),
            'pricing_miles_pct_enabled' => Setting::get('pricing_miles_pct_enabled', false),
            'pricing_miles_pct_azul' => Setting::get('pricing_miles_pct_azul', '0'),
            'pricing_miles_pct_gol' => Setting::get('pricing_miles_pct_gol', '0'),
            'pricing_miles_pct_latam' => Setting::get('pricing_miles_pct_latam', '0'),
            'pricing_miles_priority_order' => Setting::get('pricing_miles_priority_order', self::DEFAULT_MILES_PRIORITY),
            'pricing_pct_enabled' => Setting::get('pricing_pct_enabled', false),
            'pricing_pct_azul' => Setting::get('pricing_pct_azul', '80'),
            'pricing_pct_gol' => Setting::get('pricing_pct_gol', '80'),
            'pricing_pct_latam' => Setting::get('pricing_pct_latam', '80'),
            'boarding_tax_fallback_pct' => Setting::get('boarding_tax_fallback_pct', '10'),
        ]);
    }

    public function save(array $data, string $action = 'updated', ?int $restoredFromId = null): ?PricingChangeLog
    {
        $previous = $this->settings();
        $next = $this->normalize(array_merge($previous, $data));

        foreach ($next as $key => $value) {
            Setting::set($key, $value, $this->typeFor($key));
        }

        if ($previous === $next && $action !== 'restored') {
            return null;
        }

        Setting::set('pricing_version', (string) now()->timestamp, 'string');

        return PricingChangeLog::create([
            'user_id' => auth()->id(),
            'restored_from_id' => $restoredFromId,
            'action' => $action,
            'previous_settings' => $previous,
            'settings' => $next,
        ]);
    }

    public function restore(PricingChangeLog $log): ?PricingChangeLog
    {
        return $this->save($log->settings ?? [], 'restored', $log->id);
    }

    public function milesPriority(): array
    {
        return $this->normalizePriority(Setting::get('pricing_miles_priority_order', self::DEFAULT_MILES_PRIORITY));
    }

    public static function milesMethodOptions(): array
    {
        return [
            self::MILES_METHOD_MILHEIRO => 'Milheiro',
            self::MILES_METHOD_TOTAL_PERCENTAGE => '% sobre total da API',
            self::MILES_METHOD_API_ORIGINAL => 'Preço original da API',
        ];
    }

    private function normalize(array $data): array
    {
        return [
            'pricing_miles_enabled' => (bool) ($data['pricing_miles_enabled'] ?? true),
            'pricing_miles_azul' => $this->moneyString($data['pricing_miles_azul'] ?? '30.00'),
            'pricing_miles_gol' => $this->moneyString($data['pricing_miles_gol'] ?? '30.00'),
            'pricing_miles_latam' => $this->moneyString($data['pricing_miles_latam'] ?? '30.00'),
            'pricing_miles_pct_enabled' => (bool) ($data['pricing_miles_pct_enabled'] ?? false),
            'pricing_miles_pct_azul' => $this->percentString($data['pricing_miles_pct_azul'] ?? '0'),
            'pricing_miles_pct_gol' => $this->percentString($data['pricing_miles_pct_gol'] ?? '0'),
            'pricing_miles_pct_latam' => $this->percentString($data['pricing_miles_pct_latam'] ?? '0'),
            'pricing_miles_priority_order' => $this->normalizePriority($data['pricing_miles_priority_order'] ?? self::DEFAULT_MILES_PRIORITY),
            'pricing_pct_enabled' => (bool) ($data['pricing_pct_enabled'] ?? false),
            'pricing_pct_azul' => $this->percentString($data['pricing_pct_azul'] ?? '80'),
            'pricing_pct_gol' => $this->percentString($data['pricing_pct_gol'] ?? '80'),
            'pricing_pct_latam' => $this->percentString($data['pricing_pct_latam'] ?? '80'),
            'boarding_tax_fallback_pct' => $this->percentString($data['boarding_tax_fallback_pct'] ?? '10'),
        ];
    }

    private function normalizePriority(mixed $priority): array
    {
        $priority = is_array($priority) ? array_values($priority) : self::DEFAULT_MILES_PRIORITY;
        $allowed = array_keys(self::milesMethodOptions());
        $normalized = [];

        foreach ($priority as $method) {
            if (in_array($method, $allowed, true) && ! in_array($method, $normalized, true)) {
                $normalized[] = $method;
            }
        }

        foreach (self::DEFAULT_MILES_PRIORITY as $method) {
            if (! in_array($method, $normalized, true)) {
                $normalized[] = $method;
            }
        }

        return $normalized;
    }

    private function moneyString(mixed $value): string
    {
        return number_format(max(0, (float) str_replace(',', '.', (string) $value)), 2, '.', '');
    }

    private function percentString(mixed $value): string
    {
        $value = (float) str_replace(',', '.', (string) $value);

        return rtrim(rtrim(number_format(max(0, $value), 2, '.', ''), '0'), '.');
    }

    private function typeFor(string $key): string
    {
        return match ($key) {
            'pricing_miles_enabled', 'pricing_miles_pct_enabled', 'pricing_pct_enabled' => 'boolean',
            'pricing_miles_priority_order' => 'json',
            default => 'string',
        };
    }
}
