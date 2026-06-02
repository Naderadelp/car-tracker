<?php

namespace App\Repositories\Eloquent;

use App\Models\DeviceToken;
use App\Repositories\Contracts\DeviceTokenRepository;

class DeviceTokenRepositoryEloquent extends EloquentRepository implements DeviceTokenRepository
{
    public function model(): string
    {
        return DeviceToken::class;
    }

    public function upsertToken(int $userId, string $token, string $device): void
    {
        app($this->model())->newQuery()->updateOrCreate(
            ['user_id' => $userId, 'device' => $device],
            ['token' => $token]
        );
        $this->resetModel();
    }
}
