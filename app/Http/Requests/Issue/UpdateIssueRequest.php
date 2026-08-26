<?php

namespace App\Http\Requests\Issue;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('issue'));
    }

    /**
     * `resolved` is a boolean on the wire rather than a timestamp: the client
     * ticks a box, and the controller turns that into resolved_at.
     */
    public function rules(): array
    {
        return [
            'occurred_at' => ['sometimes', 'date', 'before_or_equal:today'],
            'title'       => ['sometimes', 'string', 'max:255'],
            'severity'    => ['sometimes', 'string', Rule::in(Issue::SEVERITIES)],
            'summary'     => ['nullable', 'string', 'max:5000'],
            'solution'    => ['nullable', 'string', 'max:5000'],
            'note'        => ['nullable', 'string', 'max:5000'],
            'resolved'    => ['sometimes', 'boolean'],
            'photo'       => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
