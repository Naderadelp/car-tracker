<?php

namespace App\Listeners;

use App\Classes\FireBaseNotification;
use App\Events\OdometerAdvanced;
use Illuminate\Contracts\Queue\ShouldQueue;

class CheckDueRemindersNotification implements ShouldQueue
{
    public function handle(OdometerAdvanced $event): void
    {
        $car   = $event->car;
        $newKm = $event->newKm;

        $reminders = $car->reminders()
            ->whereNull('notified_at')
            ->whereNotNull('remind_at_km')
            ->where('remind_at_km', '<=', $newKm)
            ->get();

        if ($reminders->isEmpty()) {
            return;
        }

        $car->loadMissing('user.deviceTokens');
        $user = $car->user;

        if ($user === null) {
            return;
        }

        foreach ($reminders as $reminder) {
            (new FireBaseNotification)->notifyUser(
                $user,
                [
                    'title' => $reminder->title ?? 'Reminder',
                    'body'  => $reminder->description ?? 'You have a car reminder due.',
                ],
                [
                    'type'        => 'reminder',
                    'reminder_id' => (string) $reminder->id,
                    'car_id'      => (string) $car->id,
                ],
            );

            $reminder->update(['notified_at' => now()]);
        }
    }
}
