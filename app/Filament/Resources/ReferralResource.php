<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferralResource\Pages;
use App\Models\Referral;
use BackedEnum;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ReferralResource extends Resource
{
    protected static ?string $model = Referral::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Indicações';

    protected static ?string $modelLabel = 'Indicação';

    protected static ?string $pluralModelLabel = 'Indicações';

    protected static string|\UnitEnum|null $navigationGroup = 'Gestão';

    protected static ?int $navigationSort = 12;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Afiliado')
                    ->searchable(),

                Tables\Columns\TextColumn::make('referred_document')
                    ->label('CPF Indicado')
                    ->formatStateUsing(function (?string $state) {
                        if (! $state || strlen($state) !== 11) {
                            return $state ?? '-';
                        }

                        return substr($state, 0, 3) . '.' . substr($state, 3, 3) . '.' . substr($state, 6, 3) . '-' . substr($state, 9, 2);
                    }),

                Tables\Columns\TextColumn::make('referredOrder.tracking_code')
                    ->label('Pedido')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Desconto')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('credit_amount')
                    ->label('Crédito')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('credit_status')
                    ->label('Status crédito')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'available' => 'success',
                        'used' => 'info',
                        'reversed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'available' => 'Disponível',
                        'used' => 'Usado',
                        'reversed' => 'Revertido',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'reversed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Ativa',
                        'reversed' => 'Revertida',
                        'cancelled' => 'Cancelada',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('credit_status')
                    ->label('Status crédito')
                    ->options([
                        'pending' => 'Pendente',
                        'available' => 'Disponível',
                        'used' => 'Usado',
                        'reversed' => 'Revertido',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativa',
                        'reversed' => 'Revertida',
                        'cancelled' => 'Cancelada',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ver'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Dados da indicação')
                    ->icon('heroicon-o-gift')
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('affiliate.name')
                            ->label('Afiliado'),
                        Infolists\Components\TextEntry::make('referral_code_used')
                            ->label('Código usado')
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('referred_document')
                            ->label('CPF do indicado'),
                        Infolists\Components\TextEntry::make('referredOrder.tracking_code')
                            ->label('Pedido')
                            ->badge()
                            ->color('gray'),
                    ]),

                Section::make('Valores')
                    ->icon('heroicon-o-currency-dollar')
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('order_base_total')
                            ->label('Total base')
                            ->money('BRL'),
                        Infolists\Components\TextEntry::make('discount_pct')
                            ->label('Desconto (%)')
                            ->suffix('%'),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->label('Desconto (R$)')
                            ->money('BRL'),
                        Infolists\Components\TextEntry::make('credit_pct')
                            ->label('Crédito (%)')
                            ->suffix('%'),
                        Infolists\Components\TextEntry::make('credit_amount')
                            ->label('Crédito (R$)')
                            ->money('BRL'),
                        Infolists\Components\TextEntry::make('credit_status')
                            ->label('Status crédito')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'available' => 'success',
                                'used' => 'info',
                                'reversed' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('credit_available_at')
                            ->label('Liberação prevista')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('credit_released_at')
                            ->label('Liberado em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferrals::route('/'),
            'view' => Pages\ViewReferral::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
