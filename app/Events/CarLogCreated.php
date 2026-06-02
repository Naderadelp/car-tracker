<?php

namespace App\Events;

use App\Models\CarLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CarLogCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CarLog $carLog
    ) {}
}
