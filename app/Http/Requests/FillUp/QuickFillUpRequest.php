<?php

namespace App\Http\Requests\FillUp;

use App\Models\FillUp;
use Illuminate\Foundation\Http\FormRequest;

class QuickFillUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [FillUp::class, $this->route('car')]);
    }

    public function rules(): array
    {
        return [
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'fuel_type'   => ['required', 'in:92,95,electric'],
            'liters'      => ['nullable', 'numeric', 'min:0.01'],
            'station_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'station_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'tank_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
