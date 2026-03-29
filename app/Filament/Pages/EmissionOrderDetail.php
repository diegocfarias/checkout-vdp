<?php

namespace App\Filament\Pages;

use App\Models\Order;
use Filament\Pages\Page;
use Filament\Panel;

class EmissionOrderDetail extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.emission-order-detail';

    public ?int $orderId = null;

    public function mount(int|string $order): void
    {
        $orderModel = Order::with(['flights', 'flightSearch', 'passengers', 'emission.issuer'])
            ->findOrFail($order);

        $user = auth()->user();
        if (! $user->isAdmin()) {
            $emission = $orderModel->emission;
            if (! $emission || $emission->issuer_id !== $user->id) {
                abort(403);
            }
        }

        $this->orderId = $orderModel->id;
    }

    public function getOrder(): Order
    {
        return Order::with(['flights', 'flightSearch', 'passengers', 'emission'])->findOrFail($this->orderId);
    }

    public function getTitle(): string
    {
        return 'Emissão — ' . ($this->getOrder()->tracking_code ?? '');
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/emission-order/{order}';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'emission-order';
    }
}
