<?php

namespace App\Listeners;

use App\Classes\FireBaseNotification;
use App\Events\CarLogCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServiceCompletedNotification implements ShouldQueue
{
    public function handle(CarLogCreated $event): void
    {
        $log = $event->carLog;
        $log->loadMissing('car.user.deviceTokens');

        $car  = $log->car;
        $user = $car?->user;

        if ($user === null) {
            return;
        }

        $km   = number_format($log->odometer_at_service);
        $cost = number_format((float) $log->actual_cost, 2);

        (new FireBaseNotification)->notifyUser(
            $user,
            ['title' => 'Service Logged', 'body' => "Service logged at {$km} km — cost {$cost} EGP"],
            ['type' => 'service_completed', 'car_id' => (string) $car->id, 'log_id' => (string) $log->id],
        );
    }
}
