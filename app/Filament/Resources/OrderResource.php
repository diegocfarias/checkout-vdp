<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\PaymentGatewayResolver;
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

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $modelLabel = 'Pedido';

    protected static ?string $pluralModelLabel = 'Pedidos';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tracking_code')
                    ->label('Código')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('route')
                    ->label('Rota')
                    ->getStateUsing(fn (Order $record) => $record->departure_iata && $record->arrival_iata
                        ? strtoupper($record->departure_iata) . ' → ' . strtoupper($record->arrival_iata)
                        : '-'),
                Tables\Columns\TextColumn::make('passengers.full_name')
                    ->label('Passageiro')
                    ->getStateUsing(fn (Order $record) => $record->passengers->first()?->full_name ?? '-')
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('passengers', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
                    }),
                Tables\Columns\TextColumn::make('status')
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
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state)),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Valor')
                    ->getStateUsing(function (Order $record) {
                        $record->loadMissing('flights');
                        $total = $record->flights->sum(fn ($f) => (float) ($f->money_price ?? 0) + (float) ($f->tax ?? 0));

                        return $total > 0 ? 'R$ ' . number_format($total, 2, ',', '.') : '-';
                    }),
                Tables\Columns\TextColumn::make('device_type')
                    ->label('Dispositivo')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'mobile' ? 'info' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'mobile' => 'Mobile',
                        'desktop' => 'Desktop',
                        default => '-',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cabin')
                    ->label('Cabine')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'awaiting_payment' => 'Aguardando Pagamento',
                        'awaiting_emission' => 'Aguardando Emissão',
                        'completed' => 'Emitido',
                        'cancelled' => 'Cancelado',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->label('Ver'),
                Actions\Action::make('mark_completed')
                    ->label('Emitido')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Confirmar emissão')
                    ->modalDescription('Informe o LOC de cada trecho.')
                    ->form(function (Order $record): array {
                        $record->loadMissing('flights');
                        $fields = [];
                        foreach ($record->flights as $flight) {
                            $dir = $flight->direction === 'outbound' ? 'Ida' : 'Volta';
                            $label = $dir . ' — ' . strtoupper($flight->cia ?? '');
                            $fields[] = TextInput::make('loc_' . $flight->id)
                                ->label($label)
                                ->placeholder('Ex: ABC123')
                                ->required()
                                ->maxLength(20)
                                ->extraInputAttributes(['style' => 'text-transform: uppercase']);
                        }
                        return $fields;
                    })
                    ->visible(fn (Order $record): bool => $record->status === 'awaiting_emission')
                    ->action(function (Order $record, array $data): void {
                        $record->loadMissing('flights');
                        $locs = [];
                        foreach ($record->flights as $flight) {
                            $loc = strtoupper(trim($data['loc_' . $flight->id] ?? ''));
                            if ($loc) {
                                $flight->update(['loc' => $loc]);
                                $locs[] = $loc;
                            }
                        }
                        $record->update([
                            'status' => 'completed',
                            'loc' => implode(' / ', array_unique($locs)),
                        ]);
                        Notification::make()->title('Pedido emitido com sucesso')->success()->send();
                    }),
                Actions\Action::make('mark_paid')
                    ->label('Confirmar Pgto')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar pagamento manualmente')
                    ->modalDescription('Marcar este pedido como pago? Use quando o pagamento foi confirmado fora do sistema.')
                    ->visible(fn (Order $record): bool => in_array($record->status, ['pending', 'awaiting_payment']))
                    ->action(function (Order $record): void {
                        $now = now();
                        $payment = $record->latestPayment;
                        if ($payment) {
                            $payment->update(['status' => 'paid', 'paid_at' => $now]);
                        }
                        $record->update(['status' => 'awaiting_emission', 'paid_at' => $now]);
                        Notification::make()->title('Pagamento confirmado')->success()->send();
                    }),
                Actions\Action::make('refund')
                    ->label('Estornar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Estornar pagamento')
                    ->modalDescription('O valor será devolvido ao cliente pelo gateway de pagamento original. Tem certeza?')
                    ->visible(fn (Order $record): bool => in_array($record->status, ['awaiting_emission', 'completed']))
                    ->action(function (Order $record): void {
                        $payment = $record->latestPayment;
                        if (! $payment) {
                            Notification::make()->title('Nenhum pagamento encontrado')->danger()->send();
                            return;
                        }

                        $resolver = app(PaymentGatewayResolver::class);
                        $gateway = $resolver->resolveForPayment($payment);
                        $refunded = $gateway->refundPayment($payment);

                        if ($refunded) {
                            $payment->update(['status' => 'refunded']);
                            $record->update(['status' => 'cancelled']);
                            Notification::make()->title('Estorno processado com sucesso')->success()->send();
                        } else {
                            Notification::make()
                                ->title('Falha ao processar estorno no gateway')
                                ->body('O estorno não foi concluído. Verifique os logs ou faça o estorno manualmente no painel do gateway.')
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar pedido')
                    ->modalDescription('Tem certeza que deseja cancelar este pedido?')
                    ->visible(fn (Order $record): bool => in_array($record->status, ['pending', 'awaiting_payment', 'awaiting_emission']))
                    ->action(function (Order $record): void {
                        $record->update(['status' => 'cancelled']);
                        Notification::make()->title('Pedido cancelado')->danger()->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Informações do Pedido')
                    ->icon('heroicon-o-document-text')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('tracking_code')
                            ->label('Código de rastreio')
                            ->badge()
                            ->color('gray')
                            ->copyable(),
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
                            ->formatStateUsing(fn (string $state): string => self::statusLabel($state)),
                        Infolists\Components\TextEntry::make('loc')
                            ->label('LOC')
                            ->badge()
                            ->color('success')
                            ->copyable()
                            ->placeholder('Não informado'),
                        Infolists\Components\TextEntry::make('route')
                            ->label('Rota')
                            ->getStateUsing(fn (Order $record) => $record->departure_iata && $record->arrival_iata
                                ? strtoupper($record->departure_iata) . ' → ' . strtoupper($record->arrival_iata)
                                : '-'),
                        Infolists\Components\TextEntry::make('cabin')
                            ->label('Cabine')
                            ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '-'),
                        Infolists\Components\TextEntry::make('passengers_summary')
                            ->label('Passageiros')
                            ->getStateUsing(function (Order $record) {
                                $parts = [];
                                if ($record->total_adults > 0) {
                                    $parts[] = $record->total_adults . ' adulto' . ($record->total_adults > 1 ? 's' : '');
                                }
                                if ($record->total_children > 0) {
                                    $parts[] = $record->total_children . ' criança' . ($record->total_children > 1 ? 's' : '');
                                }
                                if ($record->total_babies > 0) {
                                    $parts[] = $record->total_babies . ' bebê' . ($record->total_babies > 1 ? 's' : '');
                                }

                                return implode(', ', $parts) ?: '-';
                            }),
                        Infolists\Components\TextEntry::make('total_value')
                            ->label('Valor total')
                            ->getStateUsing(function (Order $record) {
                                $record->loadMissing('flights');
                                $total = $record->flights->sum(fn ($f) => (float) ($f->money_price ?? 0) + (float) ($f->tax ?? 0));

                                return $total > 0 ? 'R$ ' . number_format($total, 2, ',', '.') : '-';
                            }),
                        Infolists\Components\TextEntry::make('total_miles')
                            ->label('Total em milhas')
                            ->getStateUsing(function (Order $record) {
                                $record->loadMissing('flights');
                                $parts = [];
                                foreach ($record->flights as $f) {
                                    $miles = $f->price_miles ?? $f->miles_price ?? null;
                                    if ($miles) {
                                        $dir = $f->direction === 'outbound' ? 'Ida' : 'Volta';
                                        $parts[] = $dir . ': ' . number_format((float) $miles, 0, '', '.') . ' milhas';
                                    }
                                }
                                return count($parts) > 0 ? implode(' | ', $parts) : '-';
                            }),
                        Infolists\Components\TextEntry::make('device_type')
                            ->label('Dispositivo')
                            ->badge()
                            ->color(fn (?string $state): string => $state === 'mobile' ? 'info' : 'gray')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'mobile' => 'Mobile',
                                'desktop' => 'Desktop',
                                default => '-',
                            })
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Pago em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Não pago'),
                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expira em')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make('Voos')
                    ->icon('heroicon-o-paper-airplane')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('flights')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('direction')
                                    ->label('Trecho')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => $state === 'outbound' ? 'Ida' : 'Volta')
                                    ->color(fn (string $state): string => $state === 'outbound' ? 'info' : 'success'),
                                Infolists\Components\TextEntry::make('flight_info')
                                    ->label('Voo')
                                    ->getStateUsing(fn ($record) => trim(($record->cia ?? '') . ' ' . ($record->flight_number ?? '')) ?: '-'),
                                Infolists\Components\TextEntry::make('departure_info')
                                    ->label('Origem')
                                    ->getStateUsing(fn ($record) => ($record->departure_location ?? '-') . ($record->departure_time ? ' · ' . $record->departure_time : '')),
                                Infolists\Components\TextEntry::make('arrival_info')
                                    ->label('Destino')
                                    ->getStateUsing(fn ($record) => ($record->arrival_location ?? '-') . ($record->arrival_time ? ' · ' . $record->arrival_time : '')),
                                Infolists\Components\TextEntry::make('money_price')
                                    ->label('Preço')
                                    ->money('BRL'),
                                Infolists\Components\TextEntry::make('tax')
                                    ->label('Taxa')
                                    ->money('BRL'),
                                Infolists\Components\TextEntry::make('miles_display')
                                    ->label('Milhas')
                                    ->getStateUsing(fn ($record) => ($record->price_miles ?? $record->miles_price)
                                        ? number_format((float) ($record->price_miles ?? $record->miles_price), 0, '', '.') . ' mi'
                                        : '-')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('loc')
                                    ->label('LOC')
                                    ->badge()
                                    ->color('success')
                                    ->copyable()
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('connection_details')
                                    ->label('Conexões')
                                    ->columnSpanFull()
                                    ->html()
                                    ->getStateUsing(function ($record) {
                                        $conns = is_array($record->connection) ? $record->connection : [];
                                        if (count($conns) <= 1) {
                                            return '<span style="color:#059669;font-weight:600;">Direto</span>';
                                        }
                                        $html = '<div style="font-size:12px;line-height:1.6;">';
                                        foreach ($conns as $i => $seg) {
                                            $dep = $seg['DEPARTURE_TIME'] ?? '';
                                            $arr = $seg['ARRIVAL_TIME'] ?? '';
                                            $depIata = $seg['DEPARTURE_LOCATION'] ?? '';
                                            $arrIata = $seg['ARRIVAL_LOCATION'] ?? '';
                                            $num = $seg['FLIGHT_NUMBER'] ?? '';
                                            $dur = $seg['FLIGHT_DURATION'] ?? '';
                                            $wait = $seg['TIME_WAITING'] ?? '';
                                            $html .= "<div style='padding:2px 0;'>";
                                            $html .= "<strong>{$dep}</strong> {$depIata} → <strong>{$arr}</strong> {$arrIata}";
                                            if ($num) {
                                                $html .= " <span style='color:#9ca3af;'>({$num})</span>";
                                            }
                                            if ($dur) {
                                                $html .= " <span style='color:#9ca3af;'>· {$dur}</span>";
                                            }
                                            $html .= "</div>";
                                            if ($i < count($conns) - 1 && $wait) {
                                                $html .= "<div style='padding:1px 0 1px 12px;color:#d97706;font-size:11px;'>⏳ Espera {$wait} em {$arrIata}</div>";
                                            }
                                        }
                                        $html .= '</div>';

                                        return $html;
                                    }),
                            ])
                            ->columns(4),
                    ]),

                Section::make('Passageiros')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('passengers')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('full_name')
                                    ->label('Nome completo'),
                                Infolists\Components\TextEntry::make('document')
                                    ->label('CPF'),
                                Infolists\Components\TextEntry::make('birth_date')
                                    ->label('Nascimento')
                                    ->date('d/m/Y'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('E-mail')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('phone')
                                    ->label('Telefone')
                                    ->copyable(),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Pagamentos')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('payments')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('gateway')
                                    ->label('Gateway')
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'paid' => 'success',
                                        'failed' => 'danger',
                                        'refunded' => 'info',
                                        'cancelled', 'expired' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'pending' => 'Pendente',
                                        'paid' => 'Pago',
                                        'failed' => 'Falhou',
                                        'refunded' => 'Estornado',
                                        'cancelled' => 'Cancelado',
                                        'expired' => 'Expirado',
                                        default => ucfirst($state),
                                    }),
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Método')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'pix' => 'PIX',
                                        'credit_card' => 'Cartão de crédito',
                                        'boleto' => 'Boleto',
                                        default => $state ?? '-',
                                    })
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Valor')
                                    ->money('BRL')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('external_checkout_id')
                                    ->label('ID no gateway')
                                    ->copyable()
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('paid_at')
                                    ->label('Pago em')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                            ])
                            ->columns(3),
                    ]),

                Section::make('Histórico de Status')
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('statusHistories')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('description')
                                    ->label('Evento'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Data/Hora')
                                    ->dateTime('d/m/Y H:i:s'),
                            ])
                            ->columns(2),
                    ]),

                Section::make('Dados técnicos')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('token')
                            ->label('Token')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('user_id')
                            ->label('User ID (Botpress)')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('conversation_id')
                            ->label('Conversation ID')
                            ->placeholder('-'),
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

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'awaiting_payment' => 'Aguardando Pagamento',
            'awaiting_emission' => 'Aguardando Emissão',
            'completed' => 'Emitido',
            'cancelled' => 'Cancelado',
            default => ucfirst($status),
        };
    }
}
