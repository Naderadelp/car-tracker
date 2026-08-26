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
            'coordinates'        => ['required', 'array', 'min:2'],
            'coordinates.*.lat'  => ['required', 'numeric', 'between:-90,90'],
            'coordinates.*.lng'  => ['required', 'numeric', 'between:-180,180'],

            /*
             * Gap F5 — Trips History shows start time, end time, duration and
             * top speed, and the Home chart buckets distance by weekday from
             * those timestamps. All four were discarded on post.
             *
             * Optional, so clients that only send coordinates keep working.
             */
            'started_at'       => ['nullable', 'date'],
            'ended_at'         => ['nullable', 'date', 'after_or_equal:started_at'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'max_speed_kmh'    => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }
}
