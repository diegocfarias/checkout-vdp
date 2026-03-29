<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                ->fillForm(fn () => [
                    'email' => $this->record->email,
                    'document' => $this->record->document,
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'email' => $data['email'],
                        'document' => preg_replace('/\D/', '', $data['document'] ?? ''),
                    ]);
                    Notification::make()->title('Dados atualizados e auditados')->success()->send();
                    $this->refreshFormData(['email', 'document']);
                }),

            Actions\Action::make('manage_affiliate')
                ->label(fn () => $this->record->is_affiliate ? 'Editar afiliado' : 'Tornar afiliado')
                ->icon('heroicon-o-gift')
                ->color(fn () => $this->record->is_affiliate ? 'info' : 'success')
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
                        ->visible(fn (Get $get) => $get('is_affiliate') && ! $this->record->referral_code),
                    TextInput::make('custom_referral_code')
                        ->label('Código personalizado')
                        ->helperText('Deve ser único e não pode coincidir com cupons existentes.')
                        ->maxLength(20)
                        ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null)
                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                        ->visible(fn (Get $get) => $get('is_affiliate') && ! $this->record->referral_code && $get('code_mode') === 'manual')
                        ->required(fn (Get $get) => $get('is_affiliate') && ! $this->record->referral_code && $get('code_mode') === 'manual')
                        ->rules([
                            fn () => function (string $attribute, $value, \Closure $fail) {
                                if (! $value) {
                                    return;
                                }
                                if (Customer::isReferralCodeTaken(strtoupper(trim($value)), $this->record->id)) {
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
                ->fillForm(fn () => [
                    'is_affiliate' => $this->record->is_affiliate,
                    'code_mode' => 'auto',
                    'affiliate_discount_pct' => $this->record->affiliate_discount_pct,
                    'affiliate_credit_pct' => $this->record->affiliate_credit_pct,
                ])
                ->action(function (array $data): void {
                    $isAffiliate = (bool) ($data['is_affiliate'] ?? false);

                    $updateData = [
                        'is_affiliate' => $isAffiliate,
                        'affiliate_discount_pct' => $data['affiliate_discount_pct'] ?: null,
                        'affiliate_credit_pct' => $data['affiliate_credit_pct'] ?: null,
                    ];

                    if ($isAffiliate && ! $this->record->referral_code) {
                        if (($data['code_mode'] ?? 'auto') === 'manual' && ! empty($data['custom_referral_code'])) {
                            $updateData['referral_code'] = strtoupper(trim($data['custom_referral_code']));
                        } else {
                            $updateData['referral_code'] = $this->record->generateReferralCode();
                        }
                    }

                    $this->record->update($updateData);

                    $msg = $isAffiliate
                        ? 'Afiliado habilitado! Código: ' . $this->record->fresh()->referral_code
                        : 'Afiliado desabilitado.';

                    Notification::make()->title($msg)->success()->send();
                    $this->refreshFormData(['is_affiliate', 'referral_code']);
                }),
        ];
    }
}
