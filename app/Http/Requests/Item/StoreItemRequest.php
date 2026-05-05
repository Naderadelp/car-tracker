<?php

namespace App\Http\Requests\Item;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Item::class);
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255', 'unique:items,name'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
