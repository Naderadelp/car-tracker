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
                'color'                => $request->color,
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

        return $this->success([], 200, 'Email verified successfully.');
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
        $car  = $this->carRepository->findWhereFirst(['user_id' => $user->id]);

        // Gap C4: the car ships alongside the user so a cold app launch needs
        // one request rather than two. This mirrors updateProfile(), which has
        // always returned both.
        return $this->success([
            'user' => new UserResource($user),
            'car'  => $car ? new CarResource($car) : null,
        ]);
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

    /**
     * FR-011, FR-012 — in-app account deletion.
     *
     * Both app stores require this from any app that offers account creation,
     * so its absence blocks store review rather than merely integration.
     *
     * Media is cleared before the owning rows go, because Spatie stores files
     * on disk keyed by the model: deleting the row first would strand the file
     * with nothing pointing at it.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            foreach ($user->documents as $document) {
                $document->clearMediaCollection('vehicle_documents');
                $document->delete();
            }

            foreach ($user->cars as $car) {
                $car->fillUps()->delete();
                $car->trips()->delete();
                $car->parkingRecords()->delete();
                $car->reminders()->delete();
                $car->forceDelete();
            }

            $user->deviceTokens()->delete();

            // Every session ends, not just this one.
            $user->tokens()->delete();

            /*
             * The email and name are scrubbed before the soft delete, for two
             * reasons. The row survives the delete, so leaving the address on
             * it would keep personal data the driver asked us to remove; and
             * `users.email` is unique, so a soft-deleted row would otherwise
             * hold the address hostage and the driver could never sign up
             * again with their own email.
             */
            $user->forceFill([
                'email' => "deleted-{$user->id}@deleted.invalid",
                'name'  => 'Deleted account',
            ])->save();

            $user->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        return $this->success([], 200, 'Account deleted.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success([], 200, 'Logged out successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        /*
         * FR-013 — the response must be identical whether or not the address
         * has an account.
         *
         * Three things had to be equalised, not one:
         *   - the `exists` validation rule (removed from ForgotPasswordRequest)
         *   - actually sending mail to an address with no account, which both
         *     leaks by side channel and lets the endpoint be used to spam
         *     arbitrary inboxes
         *   - the resend-cooldown ValidationException, which would otherwise
         *     answer "please wait" for a registered address and 200 for an
         *     unregistered one on the second request
         */
        $user = $this->userRepository->findWhereFirst(['email' => $request->email]);

        if ($user) {
            try {
                $this->otpService->send($request->email, EmailOtp::PURPOSE_RESET);
            } catch (ValidationException) {
                // Cooldown hit. Answer as though the code went out.
            }
        }

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
