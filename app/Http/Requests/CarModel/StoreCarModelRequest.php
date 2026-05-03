<?php

namespace App\Http\Requests\CarModel;

use App\Models\CarModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CarModel::class);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('car_models', 'name')->where('brand_id', $this->route('brand')->id),
            ],
        ];
    }
}
