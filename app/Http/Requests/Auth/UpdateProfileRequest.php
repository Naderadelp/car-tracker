<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'tank_size' => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:999'],
        ];
    }
}
