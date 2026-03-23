<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos'),
            'awaiting_emission' => Tab::make('Emissões Pendentes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'awaiting_emission'))
                ->icon('heroicon-o-clock'),
            'awaiting_payment' => Tab::make('Aguardando Pgto')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'awaiting_payment'))
                ->icon('heroicon-o-credit-card'),
            'completed' => Tab::make('Emitidos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->icon('heroicon-o-check-circle'),
            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
