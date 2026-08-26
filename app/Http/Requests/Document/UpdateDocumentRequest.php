<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('document'));
    }

    public function rules(): array
    {
        return [
            'type'          => ['sometimes', 'string', Rule::in(Document::TYPES)],
            // FR-007, matching StoreDocumentRequest: a past expiry date is a
            // legitimate correction, not an error.
            'expiry_date'   => ['nullable', 'date'],
            'document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
