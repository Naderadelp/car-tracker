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
            'expiry_date'   => ['nullable', 'date', 'after:today'],
            'document_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
