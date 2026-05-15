<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    protected $fillable = [
        'email',
        'purpose',
        'code_hash',
        'expires_at',
        'attempts',
        'last_sent_at',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'last_sent_at' => 'datetime',
        'attempts'     => 'integer',
    ];

    public const PURPOSE_REGISTER = 'register';
    public const PURPOSE_RESET    = 'password_reset';
}
