<?php

namespace App\Filament\Pages;

use App\Models\Order;
use Filament\Pages\Page;
use Filament\Panel;

class EmissionOrderDetail extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.emission-order-detail';

    public ?Order $order = null;

    public function mount(Order $order): void
    {
        $order->loadMissing(['flights', 'flightSearch', 'passengers', 'emission.issuer']);

        $user = auth()->user();
        if (! $user->isAdmin()) {
            $emission = $order->emission;
            if (! $emission || $emission->issuer_id !== $user->id) {
                abort(403);
            }
        }

        $this->order = $order;
    }

    public function getTitle(): string
    {
        return 'Emissão — ' . ($this->order->tracking_code ?? '');
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
