<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\CarResource;
use App\Http\Resources\UserResource;
use App\Models\CarModel;
use App\Models\EmailOtp;
use App\Repositories\Contracts\CarRepository;
use App\Repositories\Contracts\UserRepository;
use App\Services\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    public function __construct(
        protected UserRepository $userRepository,
        protected CarRepository $carRepository,
        protected EmailOtpService $otpService,
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

            $user->assignRole('user');

            $carModelId = CarModel::where('brand_id', $request->brand_id)
                ->where('name', $request->car_model_name)
                ->where('model_year', $request->model_year)
                ->value('id');

            $car = $this->carRepository->create([
                'user_id'              => $user->id,
                'brand_id'             => $request->brand_id,
                'car_model_id'         => $carModelId,
                'current_km'           => $request->current_km,
                'tank_size'            => $request->tank_size,
                'has_warranty'         => $request->has_warranty,
                'warranty_limit_km'    => $request->warranty_limit_km,
                'warranty_expiry_date' => $request->warranty_expiry_date,
            ]);

            $car = $this->carRepository->find($car->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        $this->otpService->send($request->email, EmailOtp::PURPOSE_REGISTER);

        return $this->success([
            'user' => new UserResource($user),
            'car'  => new CarResource($car),
        ], 201, 'Account created. Check your email for the verification code.');
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $user = $this->userRepository->findWhereFirst(['email' => $request->email]);

        if ($user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['Email is already verified.'],
            ]);
        }

        $this->otpService->verify($request->email, EmailOtp::PURPOSE_REGISTER, $request->otp);

        $this->userRepository->update(['email_verified_at' => now()], $user->id);

        return $this->success(['message' => 'Email verified successfully.'], 200);
    }

    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $user = $this->userRepository->findWhereFirst(['email' => $request->email]);

        if ($user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['Email is already verified.'],
            ]);
        }

        $this->otpService->send($request->email, EmailOtp::PURPOSE_REGISTER);

        return $this->success([], 200, 'Verification code sent.');
    }

    public function login(LoginUserRequest $request): JsonResponse
    {
        $user = $this->userRepository->findWhereFirst(['email' => $request->email]);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email before logging in.'],
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

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            if ($request->filled('name')) {
                $this->userRepository->update(['name' => $request->name], $user->id);
            }

            if ($request->has('tank_size')) {
                $car = $this->carRepository->findWhereFirst(['user_id' => $user->id]);

                if ($car) {
                    $this->carRepository->update(['tank_size' => $request->tank_size], $car->id);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        $user = $this->userRepository->find($user->id);
        $car  = $this->carRepository->findWhereFirst(['user_id' => $user->id]);

        return $this->success([
            'user' => new UserResource($user),
            'car'  => $car ? new CarResource($car) : null,
        ], 200, 'Profile updated successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success([], 200, 'Logged out successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->otpService->send($request->email, EmailOtp::PURPOSE_RESET);

        return $this->success([], 200, 'Verification code sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->otpService->verify($request->email, EmailOtp::PURPOSE_RESET, $request->otp);

        $user = $this->userRepository->findWhereFirst(['email' => $request->email]);
        $this->userRepository->update(['password' => Hash::make($request->password)], $user->id);

        return $this->success([], 200, 'Password has been reset.');
    }
}
