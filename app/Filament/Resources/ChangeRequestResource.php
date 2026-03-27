<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChangeRequestResource\Pages;
use App\Models\CustomerChangeRequest;
use App\Services\CustomerService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ChangeRequestResource extends Resource
{
    protected static ?string $model = CustomerChangeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Solicitações';

    protected static ?string $modelLabel = 'Solicitação';

    protected static ?string $pluralModelLabel = 'Solicitações';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('field')
                    ->label('Campo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'email' => 'E-mail',
                        'document' => 'CPF',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('current_value')
                    ->label('Atual')
                    ->limit(30),
                Tables\Columns\TextColumn::make('requested_value')
                    ->label('Solicitado')
                    ->limit(30),
                Tables\Columns\TextColumn::make('status')
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'approved' => 'Aprovada',
                        'rejected' => 'Rejeitada',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                Actions\ViewAction::make()->label('Ver'),
                Actions\Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprovar solicitação')
                    ->modalDescription('O dado do cliente será alterado automaticamente.')
                    ->visible(fn (CustomerChangeRequest $record): bool => $record->isPending())
                    ->action(function (CustomerChangeRequest $record): void {
                        /** @var \App\Models\User $admin */
                        $admin = auth()->user();
                        app(CustomerService::class)->applyChangeRequest($record, $admin);
                        Notification::make()->title('Solicitação aprovada e aplicada')->success()->send();
                    }),
                Actions\Action::make('reject')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Rejeitar solicitação')
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('Motivo da rejeição')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->visible(fn (CustomerChangeRequest $record): bool => $record->isPending())
                    ->action(function (CustomerChangeRequest $record, array $data): void {
                        /** @var \App\Models\User $admin */
                        $admin = auth()->user();
                        $record->update([
                            'status' => 'rejected',
                            'admin_id' => $admin->id,
                            'admin_notes' => $data['admin_notes'],
                            'resolved_at' => now(),
                        ]);
                        Notification::make()->title('Solicitação rejeitada')->danger()->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Detalhes da solicitação')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('customer.name')
                            ->label('Cliente'),
                        Infolists\Components\TextEntry::make('customer.email')
                            ->label('E-mail do cliente'),
                        Infolists\Components\TextEntry::make('field')
                            ->label('Campo')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'email' => 'E-mail',
                                'document' => 'CPF',
                                default => $state,
                            }),
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
                        Infolists\Components\TextEntry::make('current_value')
                            ->label('Valor atual'),
                        Infolists\Components\TextEntry::make('requested_value')
                            ->label('Valor solicitado'),
                        Infolists\Components\TextEntry::make('reason')
                            ->label('Motivo')
                            ->placeholder('Não informado')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('admin.name')
                            ->label('Analisado por')
                            ->placeholder('Não analisada'),
                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('Notas do admin')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Criada em')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('resolved_at')
                            ->label('Resolvida em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Não resolvida'),
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
            'index' => Pages\ListChangeRequests::route('/'),
            'view' => Pages\ViewChangeRequest::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
