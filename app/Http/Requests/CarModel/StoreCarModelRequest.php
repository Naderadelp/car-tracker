<?php

namespace App\Http\Requests\CarModel;

use App\Models\CarModel;
use Illuminate\Foundation\Http\FormRequest;

class StoreCarModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CarModel::class);
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:100'],
            'model_year' => ['nullable', 'integer', 'digits:4'],
        ];
    }
}
