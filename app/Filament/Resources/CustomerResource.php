<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Models\CustomerAuditLog;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('document')
                    ->label('CPF')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Ativo',
                        'pending' => 'Pendente',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('google_id')
                    ->label('Google')
                    ->getStateUsing(fn (Customer $record) => $record->google_id ? 'Sim' : 'Não')
                    ->badge()
                    ->color(fn (Customer $record) => $record->google_id ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Pedidos')
                    ->counts('orders')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativo',
                        'pending' => 'Pendente',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make()->label('Ver'),
                Actions\Action::make('edit_sensitive')
                    ->label('Editar dados')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading('Editar dados sensíveis')
                    ->modalDescription('Alterações serão registradas no histórico de auditoria.')
                    ->form([
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required(),
                        TextInput::make('document')
                            ->label('CPF')
                            ->maxLength(14),
                    ])
                    ->fillForm(fn (Customer $record) => [
                        'email' => $record->email,
                        'document' => $record->document,
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $record->update([
                            'email' => $data['email'],
                            'document' => preg_replace('/\D/', '', $data['document'] ?? ''),
                        ]);
                        Notification::make()->title('Dados atualizados e auditados')->success()->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Dados do Cliente')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Nome'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('E-mail')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('document')
                            ->label('CPF')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Telefone')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'active' => 'Ativo',
                                'pending' => 'Pendente',
                                default => ucfirst($state),
                            }),
                        Infolists\Components\TextEntry::make('google_id')
                            ->label('Google ID')
                            ->placeholder('Não vinculado'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Cadastro')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make('Pedidos')
                    ->icon('heroicon-o-shopping-cart')
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
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Criado em')
                                    ->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('Solicitações de alteração')
                    ->icon('heroicon-o-pencil-square')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('changeRequests')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('field')
                                    ->label('Campo')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'email' => 'E-mail',
                                        'document' => 'CPF',
                                        default => $state,
                                    }),
                                Infolists\Components\TextEntry::make('current_value')
                                    ->label('Atual'),
                                Infolists\Components\TextEntry::make('requested_value')
                                    ->label('Solicitado'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'pending' => 'Pendente',
                                        'approved' => 'Aprovada',
                                        'rejected' => 'Rejeitada',
                                        default => ucfirst($state),
                                    }),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Data')
                                    ->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Histórico de auditoria')
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('auditLogs')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('field')
                                    ->label('Campo'),
                                Infolists\Components\TextEntry::make('old_value')
                                    ->label('De')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('new_value')
                                    ->label('Para')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('actor_type')
                                    ->label('Tipo')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'admin' => 'danger',
                                        'customer' => 'info',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'admin' => 'Admin',
                                        'customer' => 'Cliente',
                                        'system' => 'Sistema',
                                        default => $state,
                                    }),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Data')
                                    ->dateTime('d/m/Y H:i:s'),
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
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
