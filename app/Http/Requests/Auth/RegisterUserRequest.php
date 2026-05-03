<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

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
            'car_model_id'         => ['required', 'integer', 'exists:car_models,id'],
            'year'                 => ['required', 'integer', 'digits:4'],
            'current_km'      => ['required', 'integer', 'min:0'],
            'has_warranty'         => ['required', 'boolean'],
            'warranty_limit_km'    => ['required_if:has_warranty,true', 'nullable', 'integer'],
            'warranty_expiry_date' => ['required_if:has_warranty,true', 'nullable', 'date'],
        ];
    }
}
