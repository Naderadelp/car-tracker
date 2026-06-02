<?php

namespace App\Http\Requests\Reminder;

use App\Models\Reminder;
use Illuminate\Foundation\Http\FormRequest;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Reminder::class, $this->route('car')]);
    }

    public function rules(): array
    {
        return [
            'remind_on'    => ['nullable', 'date', 'required_without:remind_at_km'],
            'remind_at_km' => ['nullable', 'integer', 'min:0', 'required_without:remind_on'],
            'title'        => ['nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
        ];
    }
}
