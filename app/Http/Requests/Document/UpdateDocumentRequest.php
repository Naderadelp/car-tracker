<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return auth()->id() === $document->user_id;
    }

    public function rules(): array
    {
        return [
            'type'          => ['sometimes', 'string', Rule::in(Document::TYPES)],
            'expiry_date'   => ['nullable', 'date', 'after:today'],
            'document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
