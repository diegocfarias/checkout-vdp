<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
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
        }

        $this->merge(['passengers' => $passengers]);
    }

    public function rules(): array
    {
        $order = $this->route('order');
        $isMercosul = $order instanceof Order && $order->isMercosul();

        $documentRule = $isMercosul
            ? ['required', 'string', 'regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/', function (string $attribute, mixed $value, \Closure $fail) {
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
            }]
            : ['required', 'string', 'max:50'];

        return [
            'passengers' => 'required|array',
            'passengers.*.full_name' => 'required|string|max:255',
            'passengers.*.document' => $documentRule,
            'passengers.*.birth_date' => 'required|date',
            'passengers.*.email' => 'required|email|max:255',
            'passengers.*.phone' => 'required|string|max:30',
        ];
    }

    public function messages(): array
    {
        return [
            'passengers.*.document.regex' => 'Para voos dentro do Mercosul, informe um CPF válido (ex: 123.456.789-00).',
            'passengers.*.birth_date.date' => 'Informe uma data de nascimento válida (dd/mm/aaaa).',
        ];
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
            },
        ];
    }
}
