<?php

namespace Src\Domain\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Src\Common\Traits\Responder;
use Src\Domain\Auth\Http\Requests\ForgotPasswordRequest;
use Src\Domain\Auth\Http\Requests\LoginUserRequest;
use Src\Domain\Auth\Http\Requests\RegisterUserRequest;
use Src\Domain\User\Http\Resources\UserResource;
use Src\Domain\User\Repositories\Contracts\UserRepository;
use Src\Domain\Vehicle\Http\Resources\VehicleResource;
use Src\Domain\Vehicle\Repositories\Contracts\VehicleRepository;

class AuthController extends Controller
{
    use Responder;

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
                'user_id'               => $user->id,
                'brand'                 => $request->brand,
                'model'                 => $request->model,
                'year'                  => $request->year,
                'current_mileage'       => $request->current_mileage,
                'has_warranty'          => $request->has_warranty,
                'warranty_limit_km'     => $request->warranty_limit_km,
                'warranty_expiry_date'  => $request->warranty_expiry_date,
            ]);

            DB::commit();

            $token = $user->createToken('mobile_app_token')->plainTextToken;

            $this->setApiResponse(fn () => response()->json([
                'message' => 'Account created successfully.',
                'data'    => [
                    'user'    => new UserResource($user),
                    'vehicle' => new VehicleResource($vehicle),
                    'token'   => $token,
                ],
            ], 201));
        } catch (\Exception $e) {
            DB::rollBack();
            $this->setApiResponse(fn () => response()->json(['error' => $e->getMessage()], 422));
        }

        return $this->response();
    }

    public function login(LoginUserRequest $request): JsonResponse
    {
        $user = $this->userRepository->findWhereFirst(['email' => $request->email]);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $deviceName = $request->device_name ?? 'mobile_app_token';
        $token = $user->createToken($deviceName)->plainTextToken;

        $this->setApiResponse(fn () => response()->json([
            'message' => 'Login successful.',
            'data'    => [
                'user'  => new UserResource($user),
                'token' => $token,
            ],
        ], 200));

        return $this->response();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->userRepository->with(['vehicles'])->find($request->user()->id);

        $this->setData('user', $user);
        $this->useCollection(UserResource::class, 'user');

        return $this->response();
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        $this->setApiResponse(fn () => response()->json([
            'message' => 'Logged out successfully.',
        ], 200));

        return $this->response();
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(['email' => $request->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $this->setApiResponse(fn () => response()->json([
            'message' => __($status),
        ], 200));

        return $this->response();
    }
}
