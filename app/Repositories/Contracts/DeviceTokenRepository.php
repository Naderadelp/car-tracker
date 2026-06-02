<?php

namespace App\Repositories\Contracts;

interface DeviceTokenRepository extends RepositoryInterface
{
    public function upsertToken(int $userId, string $token, string $device): void;
}
