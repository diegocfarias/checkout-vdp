<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageSettings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Configurações';

    protected static ?string $title = 'Configurações';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $interestRates = Setting::get('interest_rates', []);

        $formattedRates = [];
        if (is_array($interestRates)) {
            foreach ($interestRates as $installment => $rate) {
                $formattedRates[(string) $installment] = (string) $rate;
            }
        }

        $this->form->fill([
            'mix_enabled' => Setting::get('mix_enabled', true),
            'pricing_miles_enabled' => Setting::get('pricing_miles_enabled', true),
            'pricing_pct_enabled' => Setting::get('pricing_pct_enabled', false),
            'pricing_miles_azul' => Setting::get('pricing_miles_azul', '30.00'),
            'pricing_miles_gol' => Setting::get('pricing_miles_gol', '30.00'),
            'pricing_miles_latam' => Setting::get('pricing_miles_latam', '30.00'),
            'pricing_pct_azul' => Setting::get('pricing_pct_azul', '80'),
            'pricing_pct_gol' => Setting::get('pricing_pct_gol', '80'),
            'pricing_pct_latam' => Setting::get('pricing_pct_latam', '80'),
            'pix_enabled' => Setting::get('pix_enabled', true),
            'credit_card_enabled' => Setting::get('credit_card_enabled', true),
            'max_installments' => Setting::get('max_installments', 12),
            'interest_rates' => $formattedRates,
            'order_expiration_minutes' => Setting::get('order_expiration_minutes', 30),
            'whatsapp_number' => Setting::get('whatsapp_number', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Busca de Voos')
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([
                        Toggle::make('mix_enabled')
                            ->label('Mix de companhias')
                            ->helperText('Permite agrupar voos de companhias diferentes no mesmo pedido.')
                            ->default(true),
                    ]),

                Section::make('Precificação')
                    ->icon('heroicon-o-currency-dollar')
                    ->description('Quando ambos estão ligados, milhas tem prioridade. Se o voo não retornar milhas, usa o percentual. Se ambos estiverem desligados, usa o preço original da API.')
                    ->schema([
                        Toggle::make('pricing_miles_enabled')
                            ->label('Precificação por milhas')
                            ->helperText('Calcula o preço como: (milhas / 1000) × valor do milheiro + taxas.')
                            ->default(true)
                            ->live(),

                        TextInput::make('pricing_miles_azul')
                            ->label('Valor do milheiro — Azul (R$)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->placeholder('30.00')
                            ->visible(fn ($get) => $get('pricing_miles_enabled')),

                        TextInput::make('pricing_miles_gol')
                            ->label('Valor do milheiro — Gol (R$)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->placeholder('30.00')
                            ->visible(fn ($get) => $get('pricing_miles_enabled')),

                        TextInput::make('pricing_miles_latam')
                            ->label('Valor do milheiro — Latam (R$)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->placeholder('30.00')
                            ->visible(fn ($get) => $get('pricing_miles_enabled')),

                        Toggle::make('pricing_pct_enabled')
                            ->label('Precificação por percentual')
                            ->helperText('Calcula o preço como: preço da API × (percentual / 100) + taxas. Usado como fallback quando milhas não estão disponíveis.')
                            ->default(false)
                            ->live(),

                        TextInput::make('pricing_pct_azul')
                            ->label('Percentual — Azul (%)')
                            ->numeric()
                            ->minValue(1)
                            ->step(0.01)
                            ->placeholder('80')
                            ->visible(fn ($get) => $get('pricing_pct_enabled')),

                        TextInput::make('pricing_pct_gol')
                            ->label('Percentual — Gol (%)')
                            ->numeric()
                            ->minValue(1)
                            ->step(0.01)
                            ->placeholder('80')
                            ->visible(fn ($get) => $get('pricing_pct_enabled')),

                        TextInput::make('pricing_pct_latam')
                            ->label('Percentual — Latam (%)')
                            ->numeric()
                            ->minValue(1)
                            ->step(0.01)
                            ->placeholder('80')
                            ->visible(fn ($get) => $get('pricing_pct_enabled')),
                    ]),

                Section::make('Pagamento')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Toggle::make('pix_enabled')
                            ->label('PIX habilitado')
                            ->helperText('Habilita o pagamento via PIX.')
                            ->default(true),

                        Toggle::make('credit_card_enabled')
                            ->label('Cartão de crédito habilitado')
                            ->helperText('Habilita o pagamento via cartão de crédito.')
                            ->default(true),

                        TextInput::make('max_installments')
                            ->label('Parcelas máximas')
                            ->helperText('Número máximo de parcelas no cartão de crédito.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->required(),

                        KeyValue::make('interest_rates')
                            ->label('Taxas de juros por parcela (%)')
                            ->helperText('Chave = número da parcela, Valor = taxa de juros em %.')
                            ->keyLabel('Parcela')
                            ->valueLabel('Taxa (%)')
                            ->reorderable(false),
                    ]),

                Section::make('Pedido')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextInput::make('order_expiration_minutes')
                            ->label('Tempo de expiração (minutos)')
                            ->helperText('Tempo em minutos até o pedido expirar automaticamente.')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(1440)
                            ->required(),
                    ]),

                Section::make('Contato')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        TextInput::make('whatsapp_number')
                            ->label('Número do WhatsApp')
                            ->helperText('Número completo com DDI e DDD (ex: 5511999999999). Deixe vazio para não exibir.')
                            ->placeholder('5511999999999'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('mix_enabled', (bool) $data['mix_enabled'], 'boolean');
        Setting::set('pricing_miles_enabled', (bool) $data['pricing_miles_enabled'], 'boolean');
        Setting::set('pricing_pct_enabled', (bool) $data['pricing_pct_enabled'], 'boolean');
        Setting::set('pricing_miles_azul', $data['pricing_miles_azul'] ?? '30.00', 'string');
        Setting::set('pricing_miles_gol', $data['pricing_miles_gol'] ?? '30.00', 'string');
        Setting::set('pricing_miles_latam', $data['pricing_miles_latam'] ?? '30.00', 'string');
        Setting::set('pricing_pct_azul', $data['pricing_pct_azul'] ?? '80', 'string');
        Setting::set('pricing_pct_gol', $data['pricing_pct_gol'] ?? '80', 'string');
        Setting::set('pricing_pct_latam', $data['pricing_pct_latam'] ?? '80', 'string');
        Setting::set('pix_enabled', (bool) $data['pix_enabled'], 'boolean');
        Setting::set('credit_card_enabled', (bool) $data['credit_card_enabled'], 'boolean');
        Setting::set('max_installments', (int) $data['max_installments'], 'integer');
        Setting::set('order_expiration_minutes', (int) $data['order_expiration_minutes'], 'integer');
        Setting::set('whatsapp_number', $data['whatsapp_number'] ?? '', 'string');

        $rates = $data['interest_rates'] ?? [];
        $cleanRates = [];
        foreach ($rates as $k => $v) {
            $cleanRates[(int) $k] = (float) $v;
        }
        ksort($cleanRates);
        Setting::set('interest_rates', $cleanRates, 'json');

        Notification::make()
            ->title('Configurações salvas com sucesso!')
            ->success()
            ->send();
    }
}
