<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderFlight;
use Illuminate\Console\Command;

class SeedFakeOrder extends Command
{
    protected $signature = 'orders:seed-fake';

    protected $description = 'Cria um pedido fake para testar o checkout localmente';

    public function handle(): int
    {
        $order = Order::create([
            'total_adults' => 1,
            'total_children' => 0,
            'total_babies' => 0,
            'cabin' => 'economy',
            'departure_iata' => 'GRU',
            'arrival_iata' => 'GIG',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $order->flights()->create([
            'direction' => 'outbound',
            'cia' => 'G3',
            'flight_number' => '1234',
            'departure_time' => '08:00',
            'arrival_time' => '09:15',
            'departure_location' => 'São Paulo (GRU)',
            'arrival_location' => 'Rio de Janeiro (GIG)',
            'departure_label' => '15 Mar 2026',
            'arrival_label' => '15 Mar 2026',
            'total_flight_duration' => '1h15',
            'miles_price' => '15000',
            'money_price' => '299.00',
            'tax' => '89.00',
        ]);

        $order->flights()->create([
            'direction' => 'inbound',
            'cia' => 'G3',
            'flight_number' => '5678',
            'departure_time' => '18:00',
            'arrival_time' => '19:15',
            'departure_location' => 'Rio de Janeiro (GIG)',
            'arrival_location' => 'São Paulo (GRU)',
            'departure_label' => '20 Mar 2026',
            'arrival_label' => '20 Mar 2026',
            'total_flight_duration' => '1h15',
            'miles_price' => '15000',
            'money_price' => '299.00',
            'tax' => '89.00',
        ]);

        $url = rtrim(config('app.url'), '/') . '/r/' . $order->token;

        $this->info("Pedido fake criado com sucesso!");
        $this->newLine();
        $this->info("Checkout URL: {$url}");
        $this->newLine();
        $this->info("Token: {$order->token}");
        $this->info("Order ID: {$order->id}");

        return self::SUCCESS;
    }
}
