<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\VehicleResource;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Contracts\VehicleRepository;
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
        protected VehicleRepository $vehicleRepository,
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

            $vehicle = $this->vehicleRepository->create([
                'user_id'              => $user->id,
                'brand'                => $request->brand,
                'model'                => $request->model,
                'year'                 => $request->year,
                'current_mileage'      => $request->current_mileage,
                'has_warranty'         => $request->has_warranty,
                'warranty_limit_km'    => $request->warranty_limit_km,
                'warranty_expiry_date' => $request->warranty_expiry_date,
            ]);

            DB::commit();

            $token = $user->createToken('mobile_app_token')->plainTextToken;

            return $this->success([
                'user'    => new UserResource($user),
                'vehicle' => new VehicleResource($vehicle),
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
        $user = $this->userRepository->with(['vehicles'])->find($request->user()->id);

        return $this->success(['user' => new UserResource($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success([], 200, 'Logged out successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(['email' => $request->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->success([], 200, __($status));
    }
}
