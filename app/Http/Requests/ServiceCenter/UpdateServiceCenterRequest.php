<?php

namespace App\Http\Requests\ServiceCenter;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('serviceCenter'));
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'address'  => ['sometimes', 'string', 'max:500'],
            'open_at'  => ['sometimes', 'date_format:H:i,H:i:s'],
            'close_at' => ['sometimes', 'date_format:H:i,H:i:s', 'after:open_at'],
            'mobile'   => ['sometimes', 'string', 'max:50'],
            'lat'      => ['sometimes', 'numeric', 'between:-90,90'],
            'lng'      => ['sometimes', 'numeric', 'between:-180,180'],
        ];
    }
}
