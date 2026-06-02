<?php

namespace App\Console\Commands;

use App\Classes\FireBaseNotification;
use App\Models\Reminder;
use Illuminate\Console\Command;

class CheckReminders extends Command
{
    protected $signature = 'app:check-reminders';

    protected $description = 'Send push notifications for user reminders that have come due (by date or kilometer)';

    public function handle(): void
    {
        $reminders = Reminder::with('car.user.deviceTokens')
            ->whereNull('notified_at')
            ->where(function ($q) {
                $q->where(function ($dateQuery) {
                    $dateQuery->whereNotNull('remind_on')
                        ->whereDate('remind_on', '<=', today());
                })->orWhere(function ($kmQuery) {
                    $kmQuery->whereNotNull('remind_at_km')
                        ->whereHas('car', function ($carQuery) {
                            $carQuery->whereColumn('cars.current_km', '>=', 'reminders.remind_at_km');
                        });
                });
            })
            ->get();

        foreach ($reminders as $reminder) {
            $user = $reminder->car?->user;

            if ($user === null) {
                continue;
            }

            (new FireBaseNotification)->notifyUser(
                $user,
                [
                    'title' => $reminder->title ?? 'Reminder',
                    'body'  => $reminder->description ?? 'You have a car reminder due.',
                ],
                [
                    'type'        => 'reminder',
                    'reminder_id' => (string) $reminder->id,
                    'car_id'      => (string) $reminder->car_id,
                ],
            );

            $reminder->update(['notified_at' => now()]);
        }
    }
}
