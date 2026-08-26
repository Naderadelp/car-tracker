<?php

namespace App\Http\Requests\CarLog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('log'));
    }

    public function rules(): array
    {
        return [
            'service_id'          => ['sometimes', 'nullable', 'exists:services,id'],
            // Gap F4
            'title'               => ['nullable', 'string', 'max:255'],
            'workshop'            => ['nullable', 'string', 'max:255'],
            'category'            => ['nullable', 'string', 'max:64'],
            'notes'               => ['nullable', 'string', 'max:5000'],
            'odometer_at_service' => ['sometimes', 'integer', 'min:0'],
            'actual_cost'         => ['sometimes', 'numeric', 'min:0'],
            'performed_at'        => ['sometimes', 'date', 'before_or_equal:today'],
        ];
    }
}
