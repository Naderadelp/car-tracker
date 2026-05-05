<?php

namespace App\Http\Requests\ServiceCenter;

use App\Models\ServiceCenter;
use Illuminate\Foundation\Http\FormRequest;

class NearbyServiceCentersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ServiceCenter::class);
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
