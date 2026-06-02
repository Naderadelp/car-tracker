<?php

namespace App\Http\Requests\FuelPrice;

use App\Models\FuelPrice;
use Illuminate\Foundation\Http\FormRequest;

class StoreFuelPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FuelPrice::class);
    }

    public function rules(): array
    {
        return [
            'type'           => ['required', 'in:92,95,electric'],
            'price_per_unit' => ['required', 'numeric', 'min:0.01'],
            'effective_from' => ['required', 'date'],
        ];
    }
}
