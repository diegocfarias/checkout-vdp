<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $volta = $this->input('volta');

        if (empty($volta) || (is_array($volta) && empty(array_filter($volta)))) {
            $this->merge(['volta' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'ida' => 'required|array',
            'ida.miles_price' => 'required|string',
            'ida.money_price' => 'required|string',
            'ida.tax' => 'required|string',
            'ida.unique_id' => 'required|string',
            'ida.outbound_date' => 'required|date',
            'ida.cia' => 'required|string',

            'volta' => 'nullable|array',
            'volta.miles_price' => 'required_with:volta|string',
            'volta.money_price' => 'required_with:volta|string',
            'volta.tax' => 'required_with:volta|string',
            'volta.unique_id' => 'required_with:volta|string',
            'volta.inbound_date' => 'required_with:volta|date',
            'volta.cia' => 'required_with:volta|string',

            'departure_iata' => 'required|string|size:3',
            'arrival_iata' => 'required|string|size:3',
            'total_adults' => 'required|integer|min:1',
            'total_children' => 'required|integer|min:0',
            'total_babies' => 'required|integer|min:0',
            'userId' => 'required|string',
            'conversationId' => 'required|string',
            'cabin' => 'required|string',
        ];
    }
}
