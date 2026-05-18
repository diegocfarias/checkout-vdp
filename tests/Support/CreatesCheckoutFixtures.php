<?php

namespace Tests\Support;

use App\Models\FlightSearch;
use App\Models\Order;
use App\Models\OrderFlight;
use App\Models\OrderPassenger;
use App\Models\OrderPayment;

trait CreatesCheckoutFixtures
{
    protected function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'total_adults' => 1,
            'total_children' => 0,
            'total_babies' => 0,
            'cabin' => 'EC',
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ], $attributes));
    }

    protected function addFlight(Order $order, array $attributes = []): OrderFlight
    {
        return $order->flights()->create(array_merge([
            'direction' => 'outbound',
            'cia' => 'GOL',
            'operator' => 'GOL',
            'flight_number' => 'G31234',
            'departure_time' => '10:00',
            'arrival_time' => '11:00',
            'departure_location' => 'GRU',
            'arrival_location' => 'SDU',
            'departure_label' => 'Guarulhos (GRU)',
            'arrival_label' => 'Santos Dumont (SDU)',
            'boarding_tax' => '30,00',
            'class_service' => 'Economy',
            'price_money' => '100,00',
            'price_miles' => '10000',
            'total_flight_duration' => '01:00',
            'unique_id' => 'flight-test',
            'money_price' => '100.00',
            'tax' => '30.00',
        ], $attributes));
    }

    protected function addPassenger(Order $order, array $attributes = []): OrderPassenger
    {
        return $order->passengers()->create(array_merge([
            'full_name' => 'Maria Silva',
            'nationality' => 'BR',
            'document' => '123.456.789-00',
            'birth_date' => '1990-01-01',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
        ], $attributes));
    }

    protected function addPayment(Order $order, array $attributes = []): OrderPayment
    {
        return $order->payments()->create(array_merge([
            'gateway' => 'c6bank',
            'external_checkout_id' => 'checkout-123',
            'status' => 'pending',
            'payment_method' => 'pix',
            'amount' => 100,
            'currency' => 'BRL',
        ], $attributes));
    }

    protected function createFlightSearch(array $attributes = []): FlightSearch
    {
        return FlightSearch::create(array_merge([
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'outbound_date' => '2026-06-10',
            'trip_type' => 'oneway',
            'cabin' => 'EC',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ], $attributes));
    }
}
