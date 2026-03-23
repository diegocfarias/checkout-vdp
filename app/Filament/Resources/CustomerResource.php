<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\OrderPassenger;
use BackedEnum;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CustomerResource extends Resource
{
    protected static ?string $model = OrderPassenger::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereIn('id', function ($sub) {
                    $sub->selectRaw('MIN(id)')
                        ->from('order_passengers')
                        ->whereNotNull('document')
                        ->where('document', '!=', '')
                        ->groupBy('document');
                })
                ->addSelect([
                    'orders_count' => DB::table('order_passengers as op2')
                        ->selectRaw('COUNT(DISTINCT op2.order_id)')
                        ->whereColumn('op2.document', 'order_passengers.document'),
                ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('document')
                    ->label('CPF')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Pedidos')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\ViewAction::make()
                    ->label('Ver'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Dados do Cliente')
                    ->schema([
                        Infolists\Components\TextEntry::make('full_name')
                            ->label('Nome'),
                        Infolists\Components\TextEntry::make('document')
                            ->label('CPF'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('E-mail'),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Telefone')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('birth_date')
                            ->label('Nascimento')
                            ->date('d/m/Y')
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                Section::make('Pedidos deste cliente')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('customerOrders')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('tracking_code')
                                    ->label('Código'),
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
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'pending' => 'Pendente',
                                        'awaiting_payment' => 'Aguardando Pgto',
                                        'awaiting_emission' => 'Aguardando Emissão',
                                        'completed' => 'Emitido',
                                        'cancelled' => 'Cancelado',
                                        default => ucfirst($state),
                                    }),
                                Infolists\Components\TextEntry::make('paid_at')
                                    ->label('Pago em')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Criado em')
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
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
