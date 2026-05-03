<?php

namespace App\Http\Requests\Trip;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Trip::class, $this->route('car')]);
    }

    public function rules(): array
    {
        return [
            'coordinates'              => ['required', 'array', 'min:2'],
            'coordinates.*.lat'        => ['required', 'numeric', 'between:-90,90'],
            'coordinates.*.lng'        => ['required', 'numeric', 'between:-180,180'],
            'coordinates.*.timestamp'  => ['required', 'date'],
        ];
    }
}
