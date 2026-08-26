<?php

namespace App\Http\Requests\ParkingRecord;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gap F7 — the resource was create/delete only, so a driver who mistyped a
 * label or wanted to correct a reverse-geocoded address had to delete the
 * record and start again.
 */
class UpdateParkingRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('parkingRecord'));
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'address'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude'    => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude'   => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'parked_at'   => ['sometimes', 'date', 'before_or_equal:now'],
        ];
    }
}
