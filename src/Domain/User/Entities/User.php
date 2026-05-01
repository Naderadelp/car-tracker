<?php

namespace Src\Domain\User\Entities;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Src\Domain\User\Entities\Traits\UserAttributes;
use Src\Domain\User\Entities\Traits\UserRelations;

class User extends Authenticatable
{
    use HasApiTokens, LogsActivity, Notifiable, UserAttributes, UserRelations;

    public static $logAttributes = ['*'];
    protected static $logName = 'User';

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
