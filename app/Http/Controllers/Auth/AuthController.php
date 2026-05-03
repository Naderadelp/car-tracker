<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\CarResource;
use App\Repositories\Contracts\CarRepository;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    public function __construct(
        protected UserRepository $userRepository,
        protected CarRepository $carRepository,
    ) {}

    public function register(RegisterUserRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = $this->userRepository->create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password,
            ]);

            $car = $this->carRepository->create([
                'user_id'              => $user->id,
                'brand_id'             => $request->brand_id,
                'car_model_id'         => $request->car_model_id,
                'current_km'           => $request->current_km,
                'has_warranty'         => $request->has_warranty,
                'warranty_limit_km'    => $request->warranty_limit_km,
                'warranty_expiry_date' => $request->warranty_expiry_date,
            ]);

            DB::commit();

            $token = $user->createToken('mobile_app_token')->plainTextToken;

            return $this->success([
                'user'    => new UserResource($user),
                'car' => new CarResource($car),
                'token'   => $token,
            ], 201, 'Account created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function login(LoginUserRequest $request): JsonResponse
    {
        $user = $this->userRepository->findWhereFirst(['email' => $request->email]);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($request->device_name ?? 'mobile_app_token')->plainTextToken;

        return $this->success([
            'user'  => new UserResource($user),
            'token' => $token,
        ], 200, 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->userRepository->find($request->user()->id);

        return $this->success(['user' => new UserResource($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success([], 200, 'Logged out successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Build a deep link so the mobile app can handle the reset natively
        Password::createUrlUsing(function ($notifiable, string $token) {
            return config('app.mobile_scheme') . '://reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });

        $status = Password::sendResetLink(['email' => $request->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->success([], 200, __($status));
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->success([], 200, __($status));
    }
}
