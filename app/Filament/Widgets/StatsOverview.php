<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        return [
            Stat::make('Emissões Pendentes', Order::where('status', 'awaiting_emission')->count())
                ->description('Aguardando emissão')
                ->descriptionIcon('heroicon-o-clock')
                ->color('primary'),

            Stat::make('Pagos Hoje', Order::where('status', 'awaiting_emission')
                ->where('paid_at', '>=', $today)
                ->count())
                ->description('Pedidos pagos hoje')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('success'),

            Stat::make('Total de Pedidos', Order::count())
                ->description('Todos os pedidos')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('gray'),

            Stat::make('Cancelados', Order::where('status', 'cancelled')->count())
                ->description('Pedidos cancelados')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }
}
