<?php

namespace App\Listeners;

use App\Classes\FireBaseNotification;
use App\Events\OdometerAdvanced;
use App\Models\Service;
use Illuminate\Contracts\Queue\ShouldQueue;

class CheckUpcomingServicesNotification implements ShouldQueue
{
    public function handle(OdometerAdvanced $event): void
    {
        $car   = $event->car;
        $newKm = $event->newKm;

        $services = Service::query()
            ->where('car_model_id', $car->car_model_id)
            ->whereNull('user_id')
            ->whereNull('car_id')
            ->where('km', '>', $newKm)
            ->where('km', '<=', $newKm + 500)
            ->get();

        if ($services->isEmpty()) {
            return;
        }

        $car->loadMissing('user.deviceTokens');
        $user = $car->user;

        if ($user === null) {
            return;
        }

        foreach ($services as $service) {
            $remaining = $service->km - $newKm;

            (new FireBaseNotification)->notifyUser(
                $user,
                ['title' => 'Upcoming Service', 'body' => "Upcoming service in {$remaining} km"],
                ['type' => 'upcoming_service', 'car_id' => (string) $car->id, 'km_remaining' => (string) $remaining],
            );
        }
    }
}
