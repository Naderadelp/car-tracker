<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * FR-013. `exists:users,email` used to be here, which turned this
             * endpoint into an account-enumeration oracle: a registered address
             * returned 200 and an unregistered one returned 422 with a
             * validation error naming the field. Anyone could test an address
             * list against it.
             *
             * The existence check now happens in the controller, which sends a
             * code only when there is an account and answers identically either
             * way.
             */
            'email' => ['required', 'email'],
        ];
    }
}
