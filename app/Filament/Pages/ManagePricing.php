<?php

namespace App\Filament\Pages;

use App\Models\PricingChangeLog;
use App\Services\PricingSettingsService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManagePricing extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Precificação';

    protected static ?string $title = 'Precificação';

    protected static string|\UnitEnum|null $navigationGroup = 'Gestão';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.manage-pricing';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->pricing()->settings());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Prioridade para voos em milhas')
                    ->icon('heroicon-o-arrows-up-down')
                    ->description('Quando o voo tiver milhas, a ordem abaixo decide qual regra será tentada primeiro.')
                    ->schema([
                        Select::make('pricing_miles_priority_order')
                            ->label('Ordem de precificação')
                            ->multiple()
                            ->reorderable()
                            ->options(PricingSettingsService::milesMethodOptions())
                            ->helperText('Arraste para ordenar. Métodos sem dados suficientes são pulados até encontrar uma regra aplicável.')
                            ->required(),
                    ]),

                Section::make('Milhas por milheiro')
                    ->icon('heroicon-o-ticket')
                    ->description('Mantém a regra atual: milhas ÷ 1000 × valor do milheiro. A taxa é somada separadamente.')
                    ->schema([
                        Toggle::make('pricing_miles_enabled')
                            ->label('Usar precificação por milheiro')
                            ->default(true)
                            ->live(),

                        TextInput::make('pricing_miles_azul')
                            ->label('Milheiro Azul (R$)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('R$')
                            ->visible(fn ($get) => $get('pricing_miles_enabled')),

                        TextInput::make('pricing_miles_gol')
                            ->label('Milheiro Gol (R$)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('R$')
                            ->visible(fn ($get) => $get('pricing_miles_enabled')),

                        TextInput::make('pricing_miles_latam')
                            ->label('Milheiro Latam (R$)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('R$')
                            ->visible(fn ($get) => $get('pricing_miles_enabled')),
                    ]),

                Section::make('Milhas por margem sobre total da API')
                    ->icon('heroicon-o-percent-badge')
                    ->description('Para voos com milhas, aplica margem sobre o total retornado pela integração: passagem + taxa.')
                    ->schema([
                        Toggle::make('pricing_miles_pct_enabled')
                            ->label('Usar percentual para voos em milhas')
                            ->default(false)
                            ->live(),

                        TextInput::make('pricing_miles_pct_azul')
                            ->label('Margem Azul (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(500)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn ($get) => $get('pricing_miles_pct_enabled')),

                        TextInput::make('pricing_miles_pct_gol')
                            ->label('Margem Gol (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(500)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn ($get) => $get('pricing_miles_pct_enabled')),

                        TextInput::make('pricing_miles_pct_latam')
                            ->label('Margem Latam (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(500)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn ($get) => $get('pricing_miles_pct_enabled')),
                    ]),

                Section::make('Voos convencionais')
                    ->icon('heroicon-o-banknotes')
                    ->description('Regra separada para voos sem milhas: aplica margem sobre o valor da passagem da API. A taxa permanece separada.')
                    ->schema([
                        Toggle::make('pricing_pct_enabled')
                            ->label('Usar percentual para voos convencionais')
                            ->default(false)
                            ->live(),

                        TextInput::make('pricing_pct_azul')
                            ->label('Margem convencional Azul (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(500)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn ($get) => $get('pricing_pct_enabled')),

                        TextInput::make('pricing_pct_gol')
                            ->label('Margem convencional Gol (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(500)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn ($get) => $get('pricing_pct_enabled')),

                        TextInput::make('pricing_pct_latam')
                            ->label('Margem convencional Latam (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(500)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn ($get) => $get('pricing_pct_enabled')),
                    ]),

                Section::make('Taxa interna')
                    ->icon('heroicon-o-receipt-percent')
                    ->description('Usada apenas quando a integração retorna taxa vazia ou zerada.')
                    ->schema([
                        TextInput::make('boarding_tax_fallback_pct')
                            ->label('Taxa fallback (%)')
                            ->helperText('Calculada sobre o valor base da passagem depois da regra de precificação.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Histórico de precificação')
            ->query(fn (): Builder => PricingChangeLog::query()->with(['user', 'restoredFrom'])->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Ação')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'restored' ? 'Restauração' : 'Alteração')
                    ->color(fn (string $state): string => $state === 'restored' ? 'warning' : 'info'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->placeholder('Sistema'),

                Tables\Columns\TextColumn::make('summary')
                    ->label('Resumo')
                    ->getStateUsing(fn (PricingChangeLog $record): string => $this->historySummary($record)),
            ])
            ->actions([
                Actions\Action::make('restore')
                    ->label('Restaurar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Restaurar precificação')
                    ->modalDescription('A configuração atual será substituída por este registro e uma nova entrada será criada no histórico.')
                    ->action(function (PricingChangeLog $record): void {
                        $this->pricing()->restore($record);
                        $this->form->fill($this->pricing()->settings());

                        Notification::make()
                            ->title('Precificação restaurada')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated([10, 25, 50]);
    }

    public function save(): void
    {
        $log = $this->pricing()->save($this->form->getState());

        Notification::make()
            ->title($log ? 'Precificação salva com sucesso!' : 'Nenhuma alteração para salvar.')
            ->success()
            ->send();
    }

    private function historySummary(PricingChangeLog $record): string
    {
        $settings = $record->settings ?? [];
        $priority = collect($settings['pricing_miles_priority_order'] ?? [])
            ->map(fn (string $method): string => PricingSettingsService::milesMethodOptions()[$method] ?? $method)
            ->implode(' > ');

        return sprintf(
            'Milheiro: %s | %% milhas: %s | Convencional: %s | Prioridade: %s',
            ! empty($settings['pricing_miles_enabled']) ? 'ativo' : 'inativo',
            ! empty($settings['pricing_miles_pct_enabled']) ? 'ativo' : 'inativo',
            ! empty($settings['pricing_pct_enabled']) ? 'ativo' : 'inativo',
            $priority ?: '-',
        );
    }

    private function pricing(): PricingSettingsService
    {
        return app(PricingSettingsService::class);
    }
}
