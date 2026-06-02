<?php

namespace App\Listeners;

use App\Classes\FireBaseNotification;
use App\Events\GasStationCheckIn;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendGasStationReminderNotification implements ShouldQueue
{
    public function handle(GasStationCheckIn $event): void
    {
        $car = $event->car;
        $car->loadMissing('user.deviceTokens');

        $user = $car->user;

        if ($user === null) {
            return;
        }

        (new FireBaseNotification)->notifyUser(
            $user,
            ['title' => 'Fill Up?', 'body' => "You're at a gas station — tap to log your fill-up"],
            ['type' => 'quick_fill_up', 'car_id' => (string) $car->id],
        );
    }
}
