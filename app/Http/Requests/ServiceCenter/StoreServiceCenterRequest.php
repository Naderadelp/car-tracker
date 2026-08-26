<?php

namespace App\Http\Requests\ServiceCenter;

use App\Models\ServiceCenter;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ServiceCenter::class);
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            // Gap F6
            'name_ar'    => ['nullable', 'string', 'max:255'],
            'address_ar' => ['nullable', 'string', 'max:500'],
            'address'  => ['required', 'string', 'max:500'],
            'open_at'  => ['required', 'date_format:H:i,H:i:s'],
            'close_at' => ['required', 'date_format:H:i,H:i:s', 'after:open_at'],
            'mobile'   => ['required', 'string', 'max:50'],
            'lat'      => ['required', 'numeric', 'between:-90,90'],
            'lng'      => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
