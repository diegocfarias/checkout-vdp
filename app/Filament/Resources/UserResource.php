<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuários';

    protected static ?string $modelLabel = 'Usuário';

    protected static ?string $pluralModelLabel = 'Usuários';

    protected static string|\UnitEnum|null $navigationGroup = 'Gestão';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Dados do usuário')
                    ->icon('heroicon-o-user')
                    ->columns(2)
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

                        Select::make('role')
                            ->label('Perfil')
                            ->options(self::roleOptions())
                            ->required()
                            ->default('issuer')
                            ->live(),

                        Toggle::make('is_active')
                            ->label('Ativo')
                            ->helperText('Usuários inativos ficam fora dos fluxos operacionais.')
                            ->default(true),

                        TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->revealable()
                            ->required(fn ($record) => $record === null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255),

                        TextInput::make('pushover_user_key')
                            ->label('Pushover User Key')
                            ->helperText('Usado para notificar emissores sobre novas emissões.')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('role') === 'issuer')
                            ->placeholder('Ex: uQiRzpo4DXghDmr9QZgQ...'),
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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Perfil')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::roleLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'admin' => 'danger',
                        'issuer' => 'primary',
                        'support' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('has_pushover')
                    ->label('Pushover')
                    ->getStateUsing(fn (User $record): bool => filled($record->pushover_user_key))
                    ->boolean()
                    ->trueIcon('heroicon-o-bell-alert')
                    ->falseIcon('heroicon-o-bell-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Perfil')
                    ->options(self::roleOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Ativos')
                    ->falseLabel('Inativos')
                    ->placeholder('Todos'),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->label('Editar'),

                Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->visible(fn (User $record): bool => $record->id !== auth()->id()),
            ])
            ->defaultSort('name');
    }

    public static function roleOptions(): array
    {
        return [
            'admin' => 'Administrador',
            'issuer' => 'Emissor',
            'support' => 'Atendente',
        ];
    }

    public static function roleLabel(?string $role): string
    {
        return self::roleOptions()[$role] ?? ($role ? ucfirst($role) : '-');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
