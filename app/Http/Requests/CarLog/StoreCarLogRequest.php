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
            'odometer_at_service' => ['required', 'integer', 'min:0'],
            'actual_cost'         => ['required', 'numeric', 'min:0'],
            'performed_at'        => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
