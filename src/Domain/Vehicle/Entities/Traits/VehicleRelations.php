<?php

namespace Src\Domain\Vehicle\Entities\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Domain\User\Entities\User;

trait VehicleRelations
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
