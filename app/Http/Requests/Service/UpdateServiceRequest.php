<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('service'));
    }

    public function rules(): array
    {
        return [
            'km'           => ['sometimes', 'integer', 'min:0'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'car_model_id' => ['sometimes', 'nullable', 'exists:car_models,id'],
        ];
    }
}
