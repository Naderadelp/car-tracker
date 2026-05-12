<?php

namespace App\Http\Requests\CarModel;

use App\Models\CarModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('carModel'));
    }

    public function rules(): array
    {
        return [
            'name'       => [
                'required', 'string', 'max:100',
                Rule::unique('car_models', 'name')
                    ->where('brand_id', $this->route('brand')->id)
                    ->where('model_year', $this->input('model_year'))
                    ->ignore($this->route('carModel')->id),
            ],
            'model_year' => ['required', 'integer', 'digits:4'],
        ];
    }
}
