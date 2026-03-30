<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderPassengersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $passengers = $this->input('passengers', []);

        foreach ($passengers as $i => $passenger) {
            if (! empty($passenger['birth_date']) && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $passenger['birth_date'], $m)) {
                $passengers[$i]['birth_date'] = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            if (! empty($passenger['passport_expiry']) && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $passenger['passport_expiry'], $m)) {
                $passengers[$i]['passport_expiry'] = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
        }

        $this->merge(['passengers' => $passengers]);
    }

    public function rules(): array
    {
        $order = $this->route('order');
        $isMercosul = $order instanceof Order && $order->isMercosul();

        $cpfValidation = function (string $attribute, mixed $value, \Closure $fail) {
            $cpf = preg_replace('/\D/', '', $value);

            if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
                $fail('CPF inválido.');
                return;
            }

            for ($t = 9; $t < 11; $t++) {
                $sum = 0;
                for ($i = 0; $i < $t; $i++) {
                    $sum += (int) $cpf[$i] * (($t + 1) - $i);
                }
                $digit = ((10 * $sum) % 11) % 10;
                if ((int) $cpf[$t] !== $digit) {
                    $fail('CPF inválido.');
                    return;
                }
            }
        };

        $passengers = $this->input('passengers', []);
        $passengerRules = [];

        foreach ($passengers as $i => $passenger) {
            $nationality = $passenger['nationality'] ?? 'BR';
            $isBR = $nationality === 'BR';
            $needsPassport = ! $isBR || ! $isMercosul;

            $passengerRules["passengers.{$i}.nationality"] = ['required', 'string', 'size:2'];
            $passengerRules["passengers.{$i}.full_name"] = ['required', 'string', 'max:255'];
            $passengerRules["passengers.{$i}.birth_date"] = ['required', 'date', 'before:today'];
            $passengerRules["passengers.{$i}.email"] = ['required', 'email', 'max:255'];
            $passengerRules["passengers.{$i}.phone"] = ['required', 'string', 'max:30'];
            $passengerRules["passengers.{$i}.save_passenger"] = ['nullable', 'boolean'];

            if ($isBR) {
                $passengerRules["passengers.{$i}.document"] = [
                    'required', 'string', 'regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/', $cpfValidation,
                ];
            } else {
                $passengerRules["passengers.{$i}.document"] = ['nullable', 'string', 'max:50'];
            }

            if ($needsPassport) {
                $passengerRules["passengers.{$i}.passport_number"] = ['required', 'string', 'max:50'];
                $passengerRules["passengers.{$i}.passport_expiry"] = ['required', 'date', 'after:today'];
            } else {
                $passengerRules["passengers.{$i}.passport_number"] = ['nullable', 'string', 'max:50'];
                $passengerRules["passengers.{$i}.passport_expiry"] = ['nullable', 'date', 'after:today'];
            }
        }

        $paymentMethod = $this->input('payment_method', 'pix');
        $isCreditCard = $paymentMethod === 'credit_card';

        $payerRules = [
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_email' => ['required', 'email', 'max:255'],
            'payer_document' => ['required', 'string', 'regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/', $cpfValidation],
        ];

        $billingRules = $isCreditCard ? [
            'billing_zipcode' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'billing_street' => ['required', 'string', 'max:255'],
            'billing_number' => ['required', 'string', 'max:20'],
            'billing_complement' => ['nullable', 'string', 'max:255'],
            'billing_neighborhood' => ['required', 'string', 'max:255'],
            'billing_city' => ['required', 'string', 'max:255'],
            'billing_state' => ['required', 'string', 'size:2'],
        ] : [];

        return array_merge([
            'passengers' => 'required|array',
            'payment_method' => ['required', 'string', Rule::in($this->allowedPaymentMethods())],
        ], $passengerRules, $payerRules, $billingRules, $isCreditCard ? [
            'card_number' => ['required', 'string', 'min:13'],
            'card_cvv' => ['required', 'string', 'min:2', 'max:4'],
            'card_month' => ['required', 'integer', 'min:1', 'max:12'],
            'card_year' => ['required', 'integer', 'min:' . (int) date('y'), 'max:' . ((int) date('y') + 15)],
            'card_name' => ['required', 'string', 'min:2', 'max:255'],
            'installments' => ['required', 'integer', 'min:1', 'max:' . $this->resolveMaxInstallments()],
        ] : []);
    }

    public function messages(): array
    {
        return [
            'passengers.*.nationality.required' => 'Informe a nacionalidade.',
            'passengers.*.document.required' => 'Informe o CPF.',
            'passengers.*.document.regex' => 'Informe um CPF válido (ex: 123.456.789-00).',
            'passengers.*.passport_number.required' => 'Informe o número do passaporte.',
            'passengers.*.passport_expiry.required' => 'Informe a validade do passaporte.',
            'passengers.*.passport_expiry.after' => 'A validade do passaporte deve ser uma data futura.',
            'passengers.*.birth_date.date' => 'Informe uma data de nascimento válida (dd/mm/aaaa).',
            'passengers.*.birth_date.before' => 'A data de nascimento não pode ser futura.',
            'payment_method.in' => 'Selecione uma forma de pagamento válida.',
            'card_number.required' => 'Informe o número do cartão.',
            'card_cvv.required' => 'Informe o CVV do cartão.',
            'card_name.required' => 'Informe o nome impresso no cartão.',
            'payer_name.required' => 'Informe o nome completo do pagador.',
            'payer_email.required' => 'Informe o e-mail do pagador.',
            'payer_email.email' => 'Informe um e-mail válido para o pagador.',
            'payer_document.required' => 'Informe o CPF do pagador.',
            'payer_document.regex' => 'Informe um CPF válido (ex: 123.456.789-00).',
            'billing_zipcode.required' => 'Informe o CEP de cobrança.',
            'billing_zipcode.regex' => 'CEP inválido (ex: 01001-000).',
            'billing_street.required' => 'Informe a rua.',
            'billing_number.required' => 'Informe o número.',
            'billing_neighborhood.required' => 'Informe o bairro.',
            'billing_city.required' => 'Informe a cidade.',
            'billing_state.required' => 'Selecione o estado.',
        ];
    }

    private function allowedPaymentMethods(): array
    {
        $methods = [];

        $gatewayPix = Setting::get('gateway_pix');
        if ($gatewayPix !== null) {
            if (! empty($gatewayPix)) {
                $methods[] = 'pix';
            }
        } elseif (Setting::get('pix_enabled', true)) {
            $methods[] = 'pix';
        }

        $gatewayCc = Setting::get('gateway_credit_card');
        if ($gatewayCc !== null) {
            if (! empty($gatewayCc)) {
                $methods[] = 'credit_card';
            }
        } elseif (Setting::get('credit_card_enabled', true)) {
            $methods[] = 'credit_card';
        }

        return $methods ?: ['pix', 'credit_card'];
    }

    private function resolveMaxInstallments(): int
    {
        $ccGateway = Setting::get('gateway_credit_card') ?: config('services.payment.gateway', 'appmax');

        return (int) Setting::get('max_installments_' . $ccGateway, Setting::get('max_installments', 12));
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $order = $this->route('order');

                if (! $order instanceof Order) {
                    return;
                }

                $expected = $order->passengers_count;
                $received = count($this->input('passengers', []));

                if ($received !== $expected) {
                    $validator->errors()->add(
                        'passengers',
                        "Esperado exatamente {$expected} passageiro(s), mas {$received} enviado(s)."
                    );
                }

                $documents = [];
                $passports = [];
                foreach ($this->input('passengers', []) as $i => $passenger) {
                    $nat = $passenger['nationality'] ?? 'BR';

                    if ($nat === 'BR') {
                        $doc = preg_replace('/\D/', '', $passenger['document'] ?? '');
                        if ($doc && in_array($doc, $documents, true)) {
                            $validator->errors()->add(
                                "passengers.{$i}.document",
                                'Este CPF já foi utilizado em outro passageiro.'
                            );
                        }
                        if ($doc) {
                            $documents[] = $doc;
                        }
                    }

                    $passport = trim($passenger['passport_number'] ?? '');
                    if ($passport) {
                        if (in_array(strtoupper($passport), $passports, true)) {
                            $validator->errors()->add(
                                "passengers.{$i}.passport_number",
                                'Este passaporte já foi utilizado em outro passageiro.'
                            );
                        }
                        $passports[] = strtoupper($passport);
                    }
                }
            },
        ];
    }
}
