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
        $carModel = $this->route('carModel');

        return [
            'name'       => [
                'required', 'string', 'max:100',
                Rule::unique('car_models', 'name')
                    ->where('brand_id', $carModel->brand_id)
                    ->ignore($carModel->id),
            ],
            'model_year' => ['nullable', 'integer', 'digits:4'],
        ];
    }
}
