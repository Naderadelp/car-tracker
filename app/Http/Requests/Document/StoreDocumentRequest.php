<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Document::class, $this->route('car')]);
    }

    public function rules(): array
    {
        return [
            'type'          => ['required', 'string', Rule::in(Document::TYPES)],

            /*
             * Gap B2, FR-007. `after:today` used to refuse an expiry date in
             * the past — but an expired licence is precisely the record a
             * driver most needs to keep, and the app renders `expired` as a
             * first-class state driving a red row and an alert.
             */
            'expiry_date'   => ['nullable', 'date'],

            /*
             * FR-006. The add-document sheet collects a type and a date and
             * nothing else, so demanding a file rejected every save the app
             * made. A scan can be attached later (FR-008).
             */
            'document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
