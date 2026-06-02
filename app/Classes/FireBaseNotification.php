<?php

namespace App\Classes;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class FireBaseNotification
{
    protected ?Messaging $messaging;

    public function __construct()
    {
        $this->messaging = $this->initialize();
    }

    protected function initialize(): ?Messaging
    {
        $credentialsPath = config('firebase.credentials');

        if (empty($credentialsPath)) {
            Log::warning('Firebase credentials not configured. Push notifications disabled.');
            return null;
        }

        $factory = (new Factory())->withServiceAccount($credentialsPath);
        return $factory->createMessaging();
    }

    public function notifyUser(User $user, array $notification, array $data): void
    {
        $tokens = $user->deviceTokens->pluck('token')->toArray();

        if (empty($tokens)) {
            return;
        }

        $this->sendMessage($notification, $data, $tokens);
    }

    public function sendMessage(array $notification, array $data, array $deviceTokens): void
    {
        if ($this->messaging === null) {
            return;
        }

        foreach ($deviceTokens as $token) {
            try {
                $message = CloudMessage::fromArray([
                    'token'        => $token,
                    'notification' => $notification,
                    'data'         => $data,
                ]);
                $this->messaging->send($message);
            } catch (\Exception $e) {
                Log::warning("Firebase send failed for token {$token}: " . $e->getMessage());
            }
        }
    }
}
