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
        ];
    }
}
