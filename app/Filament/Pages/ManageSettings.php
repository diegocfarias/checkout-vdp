<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
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

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    private function formatRates(array|string|null $rates): array
    {
        if (! is_array($rates)) {
            return [];
        }

        $formatted = [];
        foreach ($rates as $installment => $rate) {
            $formatted[(string) $installment] = (string) $rate;
        }

        return $formatted;
    }

    public function mount(): void
    {
        $gatewayPix = Setting::get('gateway_pix');
        $gatewayCc = Setting::get('gateway_credit_card');

        if ($gatewayPix === null) {
            $gatewayPix = Setting::get('pix_enabled', true) ? config('services.payment.gateway', 'abacatepay') : '';
        }
        if ($gatewayCc === null) {
            $gatewayCc = Setting::get('credit_card_enabled', true) ? config('services.payment.gateway', 'appmax') : '';
        }

        $this->form->fill([
            'emission_value_per_order' => Setting::get('emission_value_per_order', '0'),
            'pushover_app_token' => Setting::get('pushover_app_token', ''),
            'mix_enabled' => Setting::get('mix_enabled', true),
            'pricing_miles_enabled' => Setting::get('pricing_miles_enabled', true),
            'pricing_pct_enabled' => Setting::get('pricing_pct_enabled', false),
            'pricing_miles_azul' => Setting::get('pricing_miles_azul', '30.00'),
            'pricing_miles_gol' => Setting::get('pricing_miles_gol', '30.00'),
            'pricing_miles_latam' => Setting::get('pricing_miles_latam', '30.00'),
            'pricing_pct_azul' => Setting::get('pricing_pct_azul', '80'),
            'pricing_pct_gol' => Setting::get('pricing_pct_gol', '80'),
            'pricing_pct_latam' => Setting::get('pricing_pct_latam', '80'),
            'gateway_pix' => $gatewayPix,
            'pix_discount' => Setting::get('pix_discount', '0'),
            'gateway_credit_card' => $gatewayCc,
            'max_installments_appmax' => Setting::get('max_installments_appmax', Setting::get('max_installments', 12)),
            'max_installments_c6bank' => Setting::get('max_installments_c6bank', Setting::get('max_installments', 12)),
            'interest_rates_appmax' => $this->formatRates(Setting::get('interest_rates_appmax', Setting::get('interest_rates', []))),
            'interest_rates_c6bank' => $this->formatRates(Setting::get('interest_rates_c6bank', Setting::get('interest_rates', []))),
            'pix_expiration_minutes' => Setting::get('pix_expiration_minutes', 30),
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
                            ->helperText('Acréscimo sobre o preço da API. Ex: 10 = preço 10% acima da API. Fórmula: preço × (1 + %/100) + taxas.')
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

                Section::make('PIX')
                    ->icon('heroicon-o-qr-code')
                    ->schema([
                        Select::make('gateway_pix')
                            ->label('Gateway para PIX')
                            ->options([
                                '' => 'Desabilitado',
                                'abacatepay' => 'AbacatePay',
                                'appmax' => 'AppMax',
                                'c6bank' => 'C6 Bank',
                            ])
                            ->helperText('Selecione o gateway de pagamento para PIX ou desabilite.')
                            ->live(),

                        TextInput::make('pix_discount')
                            ->label('Desconto PIX (%)')
                            ->helperText('Percentual de desconto para pagamentos via PIX. Ex: 5 = 5% de desconto.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn ($get) => ! empty($get('gateway_pix'))),

                        TextInput::make('pix_expiration_minutes')
                            ->label('Expiração do PIX (minutos)')
                            ->helperText('Tempo em minutos até o código PIX expirar. Após isso, o cliente não poderá mais pagar.')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(1440)
                            ->suffix('min')
                            ->visible(fn ($get) => ! empty($get('gateway_pix'))),
                    ]),

                Section::make('Cartão de crédito')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Select::make('gateway_credit_card')
                            ->label('Gateway para cartão')
                            ->options([
                                '' => 'Desabilitado',
                                'appmax' => 'AppMax',
                                'c6bank' => 'C6 Bank',
                            ])
                            ->helperText('Selecione o gateway de pagamento para cartão de crédito ou desabilite.')
                            ->live(),

                        TextInput::make('max_installments_appmax')
                            ->label('Parcelas máximas (AppMax)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->visible(fn ($get) => $get('gateway_credit_card') === 'appmax'),

                        KeyValue::make('interest_rates_appmax')
                            ->label('Taxas de juros por parcela — AppMax (%)')
                            ->helperText('Chave = número da parcela, Valor = taxa de juros em %.')
                            ->keyLabel('Parcela')
                            ->valueLabel('Taxa (%)')
                            ->reorderable(false)
                            ->visible(fn ($get) => $get('gateway_credit_card') === 'appmax'),

                        TextInput::make('max_installments_c6bank')
                            ->label('Parcelas máximas (C6 Bank)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->visible(fn ($get) => $get('gateway_credit_card') === 'c6bank'),

                        KeyValue::make('interest_rates_c6bank')
                            ->label('Taxas de juros por parcela — C6 Bank (%)')
                            ->helperText('Chave = número da parcela, Valor = taxa de juros em %.')
                            ->keyLabel('Parcela')
                            ->valueLabel('Taxa (%)')
                            ->reorderable(false)
                            ->visible(fn ($get) => $get('gateway_credit_card') === 'c6bank'),
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

                Section::make('Emissão')
                    ->icon('heroicon-o-paper-airplane')
                    ->description('Configurações de emissão de passagens e notificações Pushover.')
                    ->schema([
                        TextInput::make('emission_value_per_order')
                            ->label('Valor por emissão (R$)')
                            ->helperText('Valor pago ao emissor por cada emissão concluída. Será congelado no registro ao concluir.')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('R$')
                            ->placeholder('0.00'),

                        TextInput::make('pushover_app_token')
                            ->label('Pushover App Token')
                            ->helperText('Token do aplicativo Pushover para notificações de novas emissões. Cada emissor deve ter sua própria User Key configurada no cadastro.')
                            ->password()
                            ->revealable()
                            ->placeholder('Token do app'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('mix_enabled', (bool) $data['mix_enabled'], 'boolean');
        $pricingFields = [
            'pricing_miles_enabled', 'pricing_pct_enabled',
            'pricing_miles_azul', 'pricing_miles_gol', 'pricing_miles_latam',
            'pricing_pct_azul', 'pricing_pct_gol', 'pricing_pct_latam',
            'pix_discount',
        ];

        $oldPricing = [];
        foreach ($pricingFields as $field) {
            $oldPricing[$field] = Setting::get($field);
        }

        Setting::set('pricing_miles_enabled', (bool) $data['pricing_miles_enabled'], 'boolean');
        Setting::set('pricing_pct_enabled', (bool) $data['pricing_pct_enabled'], 'boolean');
        Setting::set('pricing_miles_azul', $data['pricing_miles_azul'] ?? '30.00', 'string');
        Setting::set('pricing_miles_gol', $data['pricing_miles_gol'] ?? '30.00', 'string');
        Setting::set('pricing_miles_latam', $data['pricing_miles_latam'] ?? '30.00', 'string');
        Setting::set('pricing_pct_azul', $data['pricing_pct_azul'] ?? '80', 'string');
        Setting::set('pricing_pct_gol', $data['pricing_pct_gol'] ?? '80', 'string');
        Setting::set('pricing_pct_latam', $data['pricing_pct_latam'] ?? '80', 'string');
        Setting::set('gateway_pix', $data['gateway_pix'] ?? '', 'string');
        Setting::set('pix_discount', $data['pix_discount'] ?? '0', 'string');
        Setting::set('gateway_credit_card', $data['gateway_credit_card'] ?? '', 'string');

        Setting::set('pix_enabled', ! empty($data['gateway_pix']), 'boolean');
        Setting::set('credit_card_enabled', ! empty($data['gateway_credit_card']), 'boolean');

        foreach (['appmax', 'c6bank'] as $gw) {
            $maxKey = "max_installments_{$gw}";
            Setting::set($maxKey, (int) ($data[$maxKey] ?? 12), 'integer');

            $ratesKey = "interest_rates_{$gw}";
            $rates = $data[$ratesKey] ?? [];
            $clean = [];
            foreach ($rates as $k => $v) {
                $clean[(int) $k] = (float) $v;
            }
            ksort($clean);
            Setting::set($ratesKey, $clean, 'json');
        }

        $ccGateway = $data['gateway_credit_card'] ?? '';
        if ($ccGateway) {
            Setting::set('max_installments', (int) ($data["max_installments_{$ccGateway}"] ?? 12), 'integer');
            Setting::set('interest_rates', Setting::get("interest_rates_{$ccGateway}", []), 'json');
        }

        Setting::set('pix_expiration_minutes', (int) ($data['pix_expiration_minutes'] ?? 30), 'integer');
        Setting::set('order_expiration_minutes', (int) $data['order_expiration_minutes'], 'integer');
        Setting::set('whatsapp_number', $data['whatsapp_number'] ?? '', 'string');

        Setting::set('emission_value_per_order', $data['emission_value_per_order'] ?? '0', 'string');
        Setting::set('pushover_app_token', $data['pushover_app_token'] ?? '', 'string');

        $newPricing = [];
        foreach ($pricingFields as $field) {
            $newPricing[$field] = Setting::get($field);
        }

        if ($oldPricing !== $newPricing) {
            Setting::set('pricing_version', (string) now()->timestamp, 'string');
        }

        Notification::make()
            ->title('Configurações salvas com sucesso!')
            ->success()
            ->send();
    }
}
