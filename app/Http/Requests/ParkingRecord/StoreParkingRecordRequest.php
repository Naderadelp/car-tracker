<?php

namespace App\Http\Requests\ParkingRecord;

use App\Models\ParkingRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreParkingRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [ParkingRecord::class, $this->route('car')]);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('parked_at')) {
            $this->merge([
                'parked_at' => Carbon::now(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name'        => ['nullable', 'string', 'max:255', 'required_without_all:latitude,longitude'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Gap F7 — the reverse-geocoded address, distinct from the label
            // and the driver's own note.
            'address'     => ['nullable', 'string', 'max:500'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'parked_at'   => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
