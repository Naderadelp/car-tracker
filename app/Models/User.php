<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Traits\HasDefaultRoles;
use App\Models\Traits\UserRelations;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasDefaultRoles, LogsActivity, Notifiable, UserRelations;

    /**
     * The spatie/laravel-permission guard every role and permission row for this
     * model is stored under.
     *
     * This is deliberately NOT the guard a session is authenticated with. The
     * Filament admin panel signs users in through Laravel's session-based `web`
     * guard, while authorization data continues to live under the `api` label.
     *
     * @see guardName()
     */
    protected string $guard_name = 'api';

    public static $logAttributes = ['*'];
    protected static $logName = 'User';

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /**
     * The permission guard used to resolve this model's roles and permissions.
     *
     * Spatie\Permission\Guard::getNames() prefers this method over reflecting on
     * the $guard_name property, so declaring it makes the resolution explicit
     * rather than an implicit side effect of a protected property. Without a
     * pin of some kind, Guard::getNames() would fall back to scanning
     * config('auth.guards') for guards whose provider model is App\Models\User
     * and would then resolve the *authentication* guard (`web`) instead of the
     * `api` label the roles and permissions tables actually use — which is the
     * failure mode a session-authenticated Filament panel would otherwise hit.
     */
    public function guardName(): string
    {
        return $this->guard_name;
    }

    /**
     * Gate access to the Filament admin panel.
     *
     * This is a hard security boundary: the application has a large population
     * of ordinary `user` role accounts and none of them may reach /admin. Only
     * holders of the `admin` role are admitted, and the role is matched against
     * the `api` guard explicitly so a role row seeded under any other guard can
     * never satisfy the check.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        return $this->hasRole('admin', $this->guardName());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
