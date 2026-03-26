<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FlightSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'departure' => 'required|string|size:3',
            'arrival' => 'required|string|size:3',
            'outbound_date' => 'required|date|after_or_equal:today',
            'inbound_date' => 'nullable|date|after_or_equal:outbound_date',
            'adults' => 'required|integer|min:1|max:9',
            'children' => 'required|integer|min:0|max:9',
            'infants' => 'required|integer|min:0|max:9',
            'cabin' => 'required|string|in:EC,EX',
            'trip_type' => 'required|string|in:oneway,roundtrip',
        ];
    }

    public function messages(): array
    {
        return [
            'departure.required' => 'Selecione o aeroporto de origem.',
            'arrival.required' => 'Selecione o aeroporto de destino.',
            'outbound_date.required' => 'Selecione a data de ida.',
            'outbound_date.after_or_equal' => 'A data de ida deve ser hoje ou no futuro.',
            'inbound_date.after_or_equal' => 'A data de volta deve ser igual ou após a data de ida.',
            'adults.min' => 'É necessário pelo menos 1 adulto.',
            'cabin.in' => 'Classe inválida.',
        ];
    }
}
