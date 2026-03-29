<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAuditLog;
use App\Services\ReferralService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

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
                Tables\Columns\TextColumn::make('is_affiliate')
                    ->label('Afiliado')
                    ->getStateUsing(fn (Customer $record) => $record->is_affiliate ? 'Sim' : '—')
                    ->badge()
                    ->color(fn (Customer $record) => $record->is_affiliate ? 'success' : 'gray'),
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
                Tables\Filters\TernaryFilter::make('is_affiliate')
                    ->label('Afiliado'),
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
                Actions\Action::make('manage_affiliate')
                    ->label(fn (Customer $record) => $record->is_affiliate ? 'Editar afiliado' : 'Tornar afiliado')
                    ->icon('heroicon-o-gift')
                    ->color(fn (Customer $record) => $record->is_affiliate ? 'info' : 'success')
                    ->modalHeading('Configurar afiliado')
                    ->form([
                        Toggle::make('is_affiliate')
                            ->label('Habilitar como afiliado')
                            ->default(true)
                            ->live(),
                        Select::make('code_mode')
                            ->label('Código de indicação')
                            ->options([
                                'auto' => 'Gerar automaticamente',
                                'manual' => 'Definir manualmente',
                            ])
                            ->default('auto')
                            ->live()
                            ->visible(fn (Get $get, Customer $record) => $get('is_affiliate') && ! $record->referral_code),
                        TextInput::make('custom_referral_code')
                            ->label('Código personalizado')
                            ->helperText('Deve ser único e não pode coincidir com cupons existentes.')
                            ->maxLength(20)
                            ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->visible(fn (Get $get, Customer $record) => $get('is_affiliate') && ! $record->referral_code && $get('code_mode') === 'manual')
                            ->required(fn (Get $get, Customer $record) => $get('is_affiliate') && ! $record->referral_code && $get('code_mode') === 'manual')
                            ->rules([
                                fn (Customer $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                    if (! $value) {
                                        return;
                                    }
                                    if (Customer::isReferralCodeTaken(strtoupper(trim($value)), $record->id)) {
                                        $fail('Este código já está em uso por outro afiliado ou cupom.');
                                    }
                                },
                            ]),
                        TextInput::make('affiliate_discount_pct')
                            ->label('Desconto para indicados (%)')
                            ->helperText('Deixe vazio para usar o valor padrão das configurações.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                        TextInput::make('affiliate_credit_pct')
                            ->label('Crédito para o afiliado (%)')
                            ->helperText('Deixe vazio para usar o valor padrão das configurações.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                    ])
                    ->fillForm(fn (Customer $record) => [
                        'is_affiliate' => $record->is_affiliate,
                        'code_mode' => 'auto',
                        'affiliate_discount_pct' => $record->affiliate_discount_pct,
                        'affiliate_credit_pct' => $record->affiliate_credit_pct,
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $isAffiliate = (bool) ($data['is_affiliate'] ?? false);

                        $updateData = [
                            'is_affiliate' => $isAffiliate,
                            'affiliate_discount_pct' => $data['affiliate_discount_pct'] ?: null,
                            'affiliate_credit_pct' => $data['affiliate_credit_pct'] ?: null,
                        ];

                        if ($isAffiliate && ! $record->referral_code) {
                            if (($data['code_mode'] ?? 'auto') === 'manual' && ! empty($data['custom_referral_code'])) {
                                $updateData['referral_code'] = strtoupper(trim($data['custom_referral_code']));
                            } else {
                                $updateData['referral_code'] = $record->generateReferralCode();
                            }
                        }

                        $record->update($updateData);

                        $msg = $isAffiliate
                            ? 'Afiliado habilitado! Código: ' . $record->fresh()->referral_code
                            : 'Afiliado desabilitado.';

                        Notification::make()->title($msg)->success()->send();
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

                Section::make('Afiliado — Indique e Ganhe')
                    ->icon('heroicon-o-gift')
                    ->visible(fn (Customer $record) => $record->is_affiliate)
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('referral_code')
                            ->label('Código de indicação')
                            ->badge()
                            ->color('primary')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('affiliate_discount_pct')
                            ->label('Desconto indicados (%)')
                            ->suffix('%')
                            ->placeholder('Padrão global'),
                        Infolists\Components\TextEntry::make('affiliate_credit_pct')
                            ->label('Crédito afiliado (%)')
                            ->suffix('%')
                            ->placeholder('Padrão global'),
                        Infolists\Components\TextEntry::make('wallet_balance')
                            ->label('Saldo disponível')
                            ->getStateUsing(function (Customer $record) {
                                $service = app(ReferralService::class);

                                return 'R$ ' . number_format($service->getAvailableBalance($record), 2, ',', '.');
                            })
                            ->color('success'),
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
