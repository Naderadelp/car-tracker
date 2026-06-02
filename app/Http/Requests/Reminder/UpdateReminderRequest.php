<?php

namespace App\Http\Requests\Reminder;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('reminder'));
    }

    public function rules(): array
    {
        return [
            'remind_on'    => ['sometimes', 'nullable', 'date'],
            'remind_at_km' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'title'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $reminder = $this->route('reminder');

            $remindOn = $this->has('remind_on') ? $this->input('remind_on') : $reminder->remind_on;
            $remindKm = $this->has('remind_at_km') ? $this->input('remind_at_km') : $reminder->remind_at_km;

            if (blank($remindOn) && blank($remindKm)) {
                $validator->errors()->add(
                    'remind_on',
                    'A reminder must keep at least a date or a kilometer trigger.',
                );
            }
        });
    }
}
