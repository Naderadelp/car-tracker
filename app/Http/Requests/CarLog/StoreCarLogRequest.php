<?php

namespace App\Http\Requests\CarLog;

use App\Models\CarLog;
use Illuminate\Foundation\Http\FormRequest;

class StoreCarLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [CarLog::class, $this->route('car')]);
    }

    public function rules(): array
    {
        return [
            'service_id'          => ['nullable', 'exists:services,id'],
            // Gap F4 — every history row in the app reads
            // title · workshop · category.
            'title'               => ['nullable', 'string', 'max:255'],
            'workshop'            => ['nullable', 'string', 'max:255'],
            'category'            => ['nullable', 'string', 'max:64'],
            'notes'               => ['nullable', 'string', 'max:5000'],
            'odometer_at_service' => ['required', 'integer', 'min:0'],
            'actual_cost'         => ['required', 'numeric', 'min:0'],
            'performed_at'        => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
