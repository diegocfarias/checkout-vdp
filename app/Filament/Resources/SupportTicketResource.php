<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Tickets';

    protected static ?string $modelLabel = 'Ticket';

    protected static ?string $pluralModelLabel = 'Tickets';

    protected static string|\UnitEnum|null $navigationGroup = 'Atendimento';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isSupport();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->isSupport() && ! $user->isAdmin()) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhereNull('assigned_to')
                    ->orWhere('status', 'open');
            });
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SupportTicket::open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Assunto')
                    ->formatStateUsing(fn (string $state) => SupportTicket::SUBJECTS[$state] ?? $state)
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.tracking_code')
                    ->label('Pedido')
                    ->placeholder('—')
                    ->url(fn (SupportTicket $record) => $record->order_id
                        ? OrderResource::getUrl('view', ['record' => $record->order_id])
                        : null),

                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Atendente')
                    ->placeholder('Não atribuído')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state) => SupportTicket::STATUSES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'awaiting_customer' => 'info',
                        'awaiting_internal' => 'gray',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridade')
                    ->formatStateUsing(fn (string $state) => SupportTicket::PRIORITIES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'normal' => 'info',
                        'low' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('latest_message_at')
                    ->label('Última resposta')
                    ->getStateUsing(fn (SupportTicket $record) => $record->messages()->latest()->value('created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(SupportTicket::STATUSES),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(SupportTicket::PRIORITIES),

                Tables\Filters\SelectFilter::make('subject')
                    ->label('Assunto')
                    ->options(SupportTicket::SUBJECTS),

                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Atendente')
                    ->options(fn () => User::whereIn('role', ['admin', 'support'])->where('is_active', true)->pluck('name', 'id'))
                    ->placeholder('Todos'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('assign_to_me')
                    ->label('Pegar')
                    ->icon('heroicon-o-hand-raised')
                    ->color('primary')
                    ->visible(fn (SupportTicket $record) => ! $record->assigned_to)
                    ->requiresConfirmation()
                    ->action(function (SupportTicket $record): void {
                        $record->update([
                            'assigned_to' => auth()->id(),
                            'status' => 'in_progress',
                        ]);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('15s');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Informações do Ticket')
                    ->icon('heroicon-o-information-circle')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Ticket #'),

                        Infolists\Components\TextEntry::make('subject')
                            ->label('Assunto')
                            ->formatStateUsing(fn (string $state) => SupportTicket::SUBJECTS[$state] ?? $state)
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn (string $state) => SupportTicket::STATUSES[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'open' => 'danger',
                                'in_progress' => 'warning',
                                'awaiting_customer' => 'info',
                                'awaiting_internal' => 'gray',
                                'resolved' => 'success',
                                'closed' => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('priority')
                            ->label('Prioridade')
                            ->formatStateUsing(fn (string $state) => SupportTicket::PRIORITIES[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'urgent' => 'danger',
                                'high' => 'warning',
                                'normal' => 'info',
                                'low' => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('customer.name')
                            ->label('Cliente'),

                        Infolists\Components\TextEntry::make('customer.email')
                            ->label('E-mail do cliente'),

                        Infolists\Components\TextEntry::make('order.tracking_code')
                            ->label('Pedido')
                            ->placeholder('Não vinculado')
                            ->url(fn (SupportTicket $record) => $record->order_id
                                ? OrderResource::getUrl('view', ['record' => $record->order_id])
                                : null),

                        Infolists\Components\TextEntry::make('agent.name')
                            ->label('Atendente')
                            ->placeholder('Não atribuído'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i'),

                        Infolists\Components\TextEntry::make('first_response_at')
                            ->label('Primeira resposta')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('resolved_at')
                            ->label('Resolvido em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make('Mensagem inicial')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('message')
                            ->label('')
                            ->html()
                            ->prose(),
                    ]),

                Section::make('Respostas')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->schema([
                        Infolists\Components\ViewEntry::make('messages_thread')
                            ->label('')
                            ->view('filament.components.support-ticket-thread'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }
}
