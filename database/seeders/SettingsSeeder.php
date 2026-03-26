<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'mix_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'pix_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'credit_card_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'max_installments', 'value' => '12', 'type' => 'integer'],
            ['key' => 'interest_rates', 'value' => json_encode([
                1 => 0, 2 => 1.99, 3 => 2.99, 4 => 3.99,
                5 => 4.99, 6 => 5.99, 7 => 6.99, 8 => 7.99,
                9 => 8.99, 10 => 9.99, 11 => 10.99, 12 => 11.99,
            ]), 'type' => 'json'],
            ['key' => 'order_expiration_minutes', 'value' => '30', 'type' => 'integer'],
            ['key' => 'whatsapp_number', 'value' => '', 'type' => 'string'],
            ['key' => 'pricing_miles_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'pricing_pct_enabled', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'pricing_miles_azul', 'value' => '30.00', 'type' => 'string'],
            ['key' => 'pricing_miles_gol', 'value' => '30.00', 'type' => 'string'],
            ['key' => 'pricing_miles_latam', 'value' => '30.00', 'type' => 'string'],
            ['key' => 'pricing_pct_azul', 'value' => '80', 'type' => 'string'],
            ['key' => 'pricing_pct_gol', 'value' => '80', 'type' => 'string'],
            ['key' => 'pricing_pct_latam', 'value' => '80', 'type' => 'string'],
        ];

        foreach ($defaults as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'type' => $setting['type']],
            );
        }

        Setting::clearCache();
    }
}
