<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'email'                => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'             => ['required', 'string', 'min:8'],
            'brand_id'             => ['required', 'integer', 'exists:brands,id'],
            'car_model_name'       => [
                'required', 'string', 'max:100',
                Rule::exists('car_models', 'name')->where(function ($query) {
                    $query->where('brand_id', $this->input('brand_id'))
                          ->where('model_year', $this->input('model_year'));
                }),
            ],
            'model_year'           => ['required', 'integer', 'digits:4'],
            'current_km'           => ['required', 'integer', 'min:0'],
            'tank_size'            => ['nullable', 'numeric', 'min:0.1', 'max:999'],
            'has_warranty'         => ['required', 'boolean'],
            'warranty_limit_km'    => ['required_if:has_warranty,true', 'nullable', 'integer'],
            'warranty_expiry_date' => ['required_if:has_warranty,true', 'nullable', 'date'],
        ];
    }
}
