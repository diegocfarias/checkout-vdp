<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'document' => ['required', 'string', 'regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/', $this->cpfValidation()],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está cadastrado.',
            'document.regex' => 'Informe um CPF válido (ex: 123.456.789-00).',
            'password.confirmed' => 'As senhas não conferem.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ];
    }

    private function cpfValidation(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
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
    }
}
