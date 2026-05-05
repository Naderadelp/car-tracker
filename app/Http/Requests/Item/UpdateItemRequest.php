<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('item'));
    }

    public function rules(): array
    {
        $itemId = $this->route('item')->id;

        return [
            'name'  => ['sometimes', 'string', 'max:255', Rule::unique('items', 'name')->ignore($itemId)],
            'price' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
