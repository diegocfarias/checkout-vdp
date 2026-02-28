<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use BackedEnum;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Orders';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('token')
                    ->searchable()
                    ->limit(12),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'awaiting_payment' => 'info',
                        'awaiting_emission' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_adults')
                    ->label('Adults'),
                Tables\Columns\TextColumn::make('total_children')
                    ->label('Children'),
                Tables\Columns\TextColumn::make('total_babies')
                    ->label('Babies'),
                Tables\Columns\TextColumn::make('cabin'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'awaiting_payment' => 'Awaiting Payment',
                        'awaiting_emission' => 'Awaiting Emission',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Infolists\Components\Section::make('Order')
                    ->schema([
                        Infolists\Components\TextEntry::make('token'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'awaiting_payment' => 'info',
                                'awaiting_emission' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('total_adults'),
                        Infolists\Components\TextEntry::make('total_children'),
                        Infolists\Components\TextEntry::make('total_babies'),
                        Infolists\Components\TextEntry::make('cabin'),
                        Infolists\Components\TextEntry::make('user_id'),
                        Infolists\Components\TextEntry::make('conversation_id'),
                        Infolists\Components\TextEntry::make('expires_at')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('paid_at')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Flights')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('flights')
                            ->schema([
                                Infolists\Components\TextEntry::make('direction')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'outbound' => 'info',
                                        'inbound' => 'success',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('cia'),
                                Infolists\Components\TextEntry::make('flight_number'),
                                Infolists\Components\TextEntry::make('departure_location'),
                                Infolists\Components\TextEntry::make('arrival_location'),
                                Infolists\Components\TextEntry::make('departure_time'),
                                Infolists\Components\TextEntry::make('arrival_time'),
                                Infolists\Components\TextEntry::make('miles_price'),
                                Infolists\Components\TextEntry::make('money_price'),
                                Infolists\Components\TextEntry::make('tax'),
                            ])
                            ->columns(5),
                    ]),

                Infolists\Components\Section::make('Passengers')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('passengers')
                            ->schema([
                                Infolists\Components\TextEntry::make('full_name'),
                                Infolists\Components\TextEntry::make('document'),
                                Infolists\Components\TextEntry::make('birth_date')
                                    ->date('d/m/Y'),
                                Infolists\Components\TextEntry::make('email'),
                                Infolists\Components\TextEntry::make('phone'),
                            ])
                            ->columns(5),
                    ]),

                Infolists\Components\Section::make('Payments')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('payments')
                            ->schema([
                                Infolists\Components\TextEntry::make('gateway'),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'paid' => 'success',
                                        'failed' => 'danger',
                                        'cancelled', 'expired' => 'gray',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('amount')
                                    ->money('BRL')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('external_checkout_id')
                                    ->label('Checkout ID')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('paid_at')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
