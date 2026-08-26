<?php

namespace App\Http\Requests\FillUp;

use App\Models\FillUp;
use Illuminate\Foundation\Http\FormRequest;

class StoreFillUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [FillUp::class, $this->route('car')]);
    }

    public function rules(): array
    {
        return [
            'liters'    => ['required', 'numeric', 'min:0.1'],
            'cost_egp'  => ['required', 'numeric', 'min:0'],
            'fill_date' => ['required', 'date', 'before_or_equal:today'],
            'tank_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],

            /*
             * Gap B3, FR-009. The fuel form collects all three and the service
             * accepted none of them: the odometer was hardcoded to
             * cars.current_km, so a driver who filled up before logging their
             * mileage got the wrong reading on the record permanently.
             *
             * All optional — FR-010 keeps the current_km fallback for clients
             * that do not send a reading.
             */
            'odometer'     => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'station_name' => ['nullable', 'string', 'max:255'],
            'fuel_type'    => ['nullable', 'string', 'in:92,95,electric'],
        ];
    }
}
