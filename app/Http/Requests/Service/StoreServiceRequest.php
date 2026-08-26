<?php

namespace App\Http\Requests\Service;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $car = $this->route('car');

        if ($car) {
            return $this->user()->id === $car->user_id
                && $this->user()->can('create', [Service::class, $car]);
        }

        return $this->user()->can('create-service');
    }

    public function rules(): array
    {
        return [
            'km'           => ['required', 'integer', 'min:0'],
            'price'        => ['required', 'numeric', 'min:0'],
            'car_model_id' => [
                $this->route('car') ? 'nullable' : 'required',
                'exists:car_models,id',
            ],

            /*
             * Gap F3 — a driver's own checklist lines, a label and a price
             * each. `item_id` links a catalogue entry instead; supplying
             * neither a name nor an item_id would produce a blank line, so the
             * name is required unless one is given.
             */
            'items'           => ['sometimes', 'array', 'max:100'],
            'items.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'items.*.name'    => ['required_without:items.*.item_id', 'nullable', 'string', 'max:255'],
            'items.*.price'   => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
