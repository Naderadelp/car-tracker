<?php

namespace App\Http\Requests\Cost;

use App\Models\Cost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cost'));
    }

    /**
     * Every field is optional. On a carried-across row, changing `amount_egp`
     * is what sets `amount_overridden` — see CostController::update(), which is
     * where FR-045 is enforced rather than here.
     */
    public function rules(): array
    {
        return [
            'spent_at'   => ['sometimes', 'date', 'before_or_equal:today'],
            'title'      => ['sometimes', 'string', 'max:255'],
            'amount_egp' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'category'   => ['sometimes', 'string', Rule::in(Cost::CATEGORIES)],
        ];
    }
}
