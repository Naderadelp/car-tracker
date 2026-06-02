<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\DeviceTokenRepository;
use Closure;
use Illuminate\Http\Request;

class FirebaseTokenStoreMiddleware
{
    public function __construct(
        private readonly DeviceTokenRepository $deviceTokenRepository
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (auth()->check() && $request->hasHeader('DEVICE-TOKEN') && $request->hasHeader('DEVICE-TYPE')) {
            $device = strtolower($request->header('DEVICE-TYPE'));

            if (in_array($device, ['android', 'ios'], true)) {
                $this->deviceTokenRepository->upsertToken(
                    auth()->id(),
                    $request->header('DEVICE-TOKEN'),
                    $device
                );
            }
        }

        return $next($request);
    }
}
