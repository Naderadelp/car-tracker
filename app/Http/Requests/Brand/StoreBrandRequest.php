<?php

namespace App\Http\Requests\Brand;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Brand::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:brands,name'],
        ];
    }
}
