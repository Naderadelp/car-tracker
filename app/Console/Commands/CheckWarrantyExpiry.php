<?php

namespace App\Console\Commands;

use App\Classes\FireBaseNotification;
use App\Models\Car;
use Illuminate\Console\Command;

class CheckWarrantyExpiry extends Command
{
    protected $signature = 'app:check-warranty-expiry';

    protected $description = 'Send push notifications for cars with warranty expiring soon';

    public function handle(): void
    {
        $cars = Car::with('user.deviceTokens')
            ->where(function ($q) {
                $q->whereBetween('warranty_expiry_date', [today(), today()->addDays(30)])
                  ->orWhereRaw(
                      'warranty_limit_km IS NOT NULL AND (warranty_limit_km - current_km) BETWEEN 0 AND 500'
                  );
            })
            ->get();

        foreach ($cars as $car) {
            $user = $car->user;

            if ($user === null) {
                continue;
            }

            (new FireBaseNotification)->notifyUser(
                $user,
                ['title' => 'Warranty Expiring', 'body' => 'Your car warranty expires soon'],
                ['type' => 'warranty_expiry', 'car_id' => (string) $car->id],
            );
        }
    }
}
