<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IssuerResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IssuerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Emissores';

    protected static ?string $modelLabel = 'Emissor';

    protected static ?string $pluralModelLabel = 'Emissores';

    protected static ?string $navigationGroup = 'Gestão';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'issuer');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Dados do Emissor')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->revealable()
                            ->required(fn ($record) => $record === null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255),

                        TextInput::make('pushover_device_id')
                            ->label('Pushover Device ID')
                            ->helperText('ID do dispositivo no Pushover para receber notificações de novas emissões.')
                            ->maxLength(255)
                            ->placeholder('device_name'),

                        Toggle::make('is_active')
                            ->label('Ativo')
                            ->helperText('Emissores inativos não recebem notificações e não aparecem na fila.')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),

                Tables\Columns\IconColumn::make('has_pushover')
                    ->label('Pushover')
                    ->getStateUsing(fn (User $record) => filled($record->pushover_device_id))
                    ->boolean()
                    ->trueIcon('heroicon-o-bell-alert')
                    ->falseIcon('heroicon-o-bell-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('total_emissions')
                    ->label('Emissões')
                    ->getStateUsing(fn (User $record) => $record->emissions()->completed()->count())
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Ativos')
                    ->falseLabel('Inativos')
                    ->placeholder('Todos'),
            ])
            ->defaultSort('name');
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = 'issuer';

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIssuers::route('/'),
            'create' => Pages\CreateIssuer::route('/create'),
            'edit' => Pages\EditIssuer::route('/{record}/edit'),
        ];
    }
}
