<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestPendingEmissions extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Emissões Pendentes')
            ->query(
                Order::query()
                    ->where('status', 'awaiting_emission')
                    ->orderByDesc('paid_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('tracking_code')
                    ->label('Código')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('route')
                    ->label('Rota')
                    ->getStateUsing(fn (Order $record) => $record->departure_iata && $record->arrival_iata
                        ? strtoupper($record->departure_iata) . ' → ' . strtoupper($record->arrival_iata)
                        : '-'),
                Tables\Columns\TextColumn::make('passenger_name')
                    ->label('Passageiro')
                    ->getStateUsing(fn (Order $record) => $record->passengers->first()?->full_name ?? '-'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record) => route('filament.admin.resources.orders.view', $record)),
                Actions\Action::make('mark_completed')
                    ->label('Marcar Emitido')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar emissão')
                    ->modalDescription('Tem certeza que deseja marcar este pedido como emitido?')
                    ->action(function (Order $record): void {
                        $record->update(['status' => 'completed']);
                    }),
            ])
            ->paginated([5]);
    }
}
