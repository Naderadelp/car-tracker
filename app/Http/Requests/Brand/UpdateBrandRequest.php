<?php

namespace App\Http\Requests\Brand;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('brand'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($this->route('brand'))],
        ];
    }
}
