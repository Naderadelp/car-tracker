<?php

namespace App\Http\Requests\Issue;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Issue::class, $this->route('car')]);
    }

    /**
     * Only the date, title and severity are required. A fault is often recorded
     * from the roadside with nothing but "brakes squealing, bad" — demanding a
     * description there would push the driver to skip the record entirely.
     */
    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date', 'before_or_equal:today'],
            'title'       => ['required', 'string', 'max:255'],
            'severity'    => ['required', 'string', Rule::in(Issue::SEVERITIES)],
            'summary'     => ['nullable', 'string', 'max:5000'],
            'solution'    => ['nullable', 'string', 'max:5000'],
            'note'        => ['nullable', 'string', 'max:5000'],
            'photo'       => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
