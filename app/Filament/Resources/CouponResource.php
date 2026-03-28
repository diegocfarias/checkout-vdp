<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Cupons';

    protected static ?string $modelLabel = 'Cupom';

    protected static ?string $pluralModelLabel = 'Cupons';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Configuração do cupom')->columns(2)->schema([
                TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state) => strtoupper($state))
                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                Select::make('type')
                    ->label('Tipo de desconto')
                    ->options([
                        'percent' => 'Percentual (%)',
                        'fixed' => 'Valor fixo (R$)',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('value')
                    ->label('Valor do desconto')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->suffix(fn (Get $get) => $get('type') === 'percent' ? '%' : 'R$'),

                TextInput::make('max_discount')
                    ->label('Desconto máximo (R$)')
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('R$')
                    ->visible(fn (Get $get) => $get('type') === 'percent')
                    ->helperText('Limite em reais para cupons percentuais'),

                TextInput::make('usage_limit')
                    ->label('Limite de usos')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->helperText('Deixe vazio para uso ilimitado'),

                Toggle::make('active')
                    ->label('Ativo')
                    ->default(true),

                DateTimePicker::make('starts_at')
                    ->label('Válido a partir de')
                    ->displayFormat('d/m/Y H:i'),

                DateTimePicker::make('expires_at')
                    ->label('Válido até')
                    ->displayFormat('d/m/Y H:i'),
            ]),

            Section::make('Restrição por cliente')->schema([
                Select::make('customers')
                    ->label('Clientes permitidos')
                    ->relationship('customers', 'name')
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Customer::query()
                            ->where(function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('document', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn (Customer $c) => [
                                $c->id => $c->name . ($c->document ? ' — ' . $c->document : '') . ' (' . $c->email . ')',
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(fn ($value): string => Customer::find($value)?->name ?? $value)
                    ->helperText('Deixe vazio para permitir qualquer cliente'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percent' => 'Percentual',
                        'fixed' => 'Valor fixo',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'percent' => 'info',
                        'fixed' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->formatStateUsing(fn (Coupon $record): string => $record->type === 'percent'
                        ? number_format($record->value, 0) . '%'
                        : 'R$ ' . number_format($record->value, 2, ',', '.')),

                Tables\Columns\TextColumn::make('max_discount')
                    ->label('Desc. máx.')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'R$ ' . number_format((float) $state, 2, ',', '.') : '-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('usage')
                    ->label('Usos')
                    ->getStateUsing(fn (Coupon $record): string => $record->usage_count . ($record->usage_limit ? ' / ' . $record->usage_limit : ' / ∞')),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Sem expiração'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'percent' => 'Percentual',
                        'fixed' => 'Valor fixo',
                    ]),
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Ativo'),
            ])
            ->actions([
                Actions\ViewAction::make()->label('Ver'),
                Actions\EditAction::make()
                    ->label('Editar')
                    ->visible(fn (Coupon $record) => ! $record->hasBeenUsed()),
                Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->visible(fn (Coupon $record) => ! $record->hasBeenUsed()),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Detalhes do cupom')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label('Código')
                            ->copyable()
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'percent' => 'Percentual',
                                'fixed' => 'Valor fixo',
                                default => $state,
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'percent' => 'info',
                                'fixed' => 'success',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('value')
                            ->label('Valor')
                            ->formatStateUsing(fn (Coupon $record): string => $record->type === 'percent'
                                ? number_format($record->value, 0) . '%'
                                : 'R$ ' . number_format($record->value, 2, ',', '.')),
                        Infolists\Components\TextEntry::make('max_discount')
                            ->label('Desconto máximo')
                            ->formatStateUsing(fn (?string $state): string => $state ? 'R$ ' . number_format((float) $state, 2, ',', '.') : '-'),
                        Infolists\Components\TextEntry::make('usage_count')
                            ->label('Usos')
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('usage_limit')
                            ->label('Limite')
                            ->placeholder('Ilimitado'),
                        Infolists\Components\IconEntry::make('active')
                            ->label('Ativo')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('starts_at')
                            ->label('Início')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Sem data de início'),
                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expiração')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Sem expiração'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make('Clientes vinculados')
                    ->icon('heroicon-o-users')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('customers')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Nome'),
                                Infolists\Components\TextEntry::make('document')
                                    ->label('CPF')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('E-mail'),
                            ])
                            ->columns(3),
                    ]),

                Section::make('Pedidos com este cupom')
                    ->icon('heroicon-o-shopping-cart')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('orders')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('tracking_code')
                                    ->label('Código')
                                    ->badge()
                                    ->color('gray'),
                                Infolists\Components\TextEntry::make('route')
                                    ->label('Rota')
                                    ->getStateUsing(fn ($record) => strtoupper($record->departure_iata) . ' → ' . strtoupper($record->arrival_iata)),
                                Infolists\Components\TextEntry::make('discount_amount')
                                    ->label('Desconto')
                                    ->formatStateUsing(fn (?string $state) => $state ? 'R$ ' . number_format((float) $state, 2, ',', '.') : '-'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'awaiting_payment' => 'info',
                                        'awaiting_emission' => 'primary',
                                        'completed' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Data')
                                    ->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(5),
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
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
            'view' => Pages\ViewCoupon::route('/{record}'),
        ];
    }
}
