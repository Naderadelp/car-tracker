<?php

namespace App\Http\Requests\Car;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarRequest extends FormRequest
{
    /**
     * Delegates to the Gate so Gate::before() (global admin bypass) and
     * CarPolicy::update() both fire — constitution Principle II. Never a raw
     * ownership check here.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('car'));
    }

    /**
     * Every field is optional: the app edits mileage on its own, warranty on
     * its own, and colour on its own.
     *
     * `current_km` has no `gte` floor. Decision D3: a driver may correct their
     * mileage downwards, because typos and replaced instrument clusters are
     * both real, and refusing would leave a driver permanently stuck with a
     * fat-fingered reading and no way out from inside the app. Records already
     * filed keep the figures they were filed with.
     */
    public function rules(): array
    {
        return [
            'current_km'           => ['sometimes', 'integer', 'min:0', 'max:9999999'],
            'color'                => ['sometimes', 'nullable', 'string', 'max:32'],
            'tank_size'            => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:999'],
            'has_warranty'         => ['sometimes', 'boolean'],
            'warranty_limit_km'    => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999999'],
            'warranty_expiry_date' => ['sometimes', 'nullable', 'date'],
            'purchase_price_egp'   => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'purchased_at'         => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
