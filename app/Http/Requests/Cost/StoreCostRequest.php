<?php

namespace App\Http\Requests\Cost;

use App\Models\Cost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Cost::class, $this->route('car')]);
    }

    public function rules(): array
    {
        return [
            'spent_at'   => ['required', 'date', 'before_or_equal:today'],
            'title'      => ['required', 'string', 'max:255'],
            'amount_egp' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'category'   => ['required', 'string', Rule::in(Cost::CATEGORIES)],
        ];
    }
}
