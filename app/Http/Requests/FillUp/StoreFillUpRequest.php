<?php

namespace App\Http\Requests\FillUp;

use App\Models\FillUp;
use Illuminate\Foundation\Http\FormRequest;

class StoreFillUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [FillUp::class, $this->route('car')]);
    }

    public function rules(): array
    {
        return [
            'liters'    => ['required', 'numeric', 'min:0.1'],
            'cost_egp'  => ['required', 'numeric', 'min:0'],
            'fill_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
