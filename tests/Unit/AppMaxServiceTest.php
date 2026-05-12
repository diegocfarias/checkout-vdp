<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderFlight;
use App\Services\AppMaxService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppMaxServiceTest extends TestCase
{
    public function test_discounted_amount_is_sent_as_the_appmax_product_total(): void
    {
        $this->fakeAppMaxOrderEndpoint();

        $service = new AppMaxService;
        $this->invokeCreateOrder($service, $this->makeOrder(), 123, 315.00);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://api.appmax.test/v1/orders') {
                return false;
            }

            $payload = $request->data();

            return $payload['products_value'] === 31500
                && $payload['discount_value'] === 0
                && count($payload['products']) === 1
                && $payload['products'][0]['quantity'] === 1
                && $payload['products'][0]['unit_value'] === 31500;
        });
    }

    public function test_full_amount_keeps_detailed_products_when_the_sum_matches(): void
    {
        $this->fakeAppMaxOrderEndpoint();

        $service = new AppMaxService;
        $this->invokeCreateOrder($service, $this->makeOrder(), 123, 420.00);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://api.appmax.test/v1/orders') {
                return false;
            }

            $payload = $request->data();
            $productsTotal = collect($payload['products'])
                ->sum(fn (array $product) => $product['unit_value'] * $product['quantity']);

            return $payload['products_value'] === 42000
                && count($payload['products']) === 2
                && $productsTotal === $payload['products_value'];
        });
    }

    private function fakeAppMaxOrderEndpoint(): void
    {
        config([
            'services.appmax.api_url' => 'https://api.appmax.test',
            'services.appmax.auth_url' => 'https://auth.appmax.test',
        ]);

        Cache::put('appmax_jwt_token', 'fake-token', now()->addHour());

        Http::fake([
            'https://api.appmax.test/v1/orders' => Http::response([
                'data' => ['order' => ['id' => 987]],
            ]),
        ]);
    }

    private function invokeCreateOrder(AppMaxService $service, Order $order, int $customerId, float $amount): int
    {
        $method = new \ReflectionMethod($service, 'createOrder');

        return (int) $method->invoke($service, $order, $customerId, $amount);
    }

    private function makeOrder(): Order
    {
        $order = new Order([
            'total_adults' => 2,
            'total_children' => 0,
            'total_babies' => 0,
            'cabin' => 'economy',
            'expires_at' => now()->addHour(),
        ]);
        $order->id = 10;
        $order->token = 'order-token';

        $order->setRelation('flights', collect([
            $this->makeFlight(1, 100.00, 20.00, 'GRU', 'SDU'),
            $this->makeFlight(2, 80.00, 10.00, 'SDU', 'GRU'),
        ]));

        return $order;
    }

    private function makeFlight(int $id, float $moneyPrice, float $tax, string $departure, string $arrival): OrderFlight
    {
        $flight = new OrderFlight([
            'cia' => 'VDP',
            'money_price' => $moneyPrice,
            'tax' => $tax,
            'departure_location' => $departure,
            'arrival_location' => $arrival,
        ]);
        $flight->id = $id;

        return $flight;
    }
}
