<?php

namespace App\Http\Requests\FuelPrice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFuelPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fuelPrice'));
    }

    public function rules(): array
    {
        return [
            'type'           => ['sometimes', 'in:92,95,electric'],
            'price_per_unit' => ['sometimes', 'numeric', 'min:0.01'],
            'effective_from' => ['sometimes', 'date'],
        ];
    }
}
