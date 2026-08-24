<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Filament\Facades\Filament;

/**
 * Gates a dashboard widget behind one of the API's own spatie permissions.
 *
 * Filament\Widgets\Widget::canView() returns true by default, which would put
 * every dashboard aggregate in front of any account that clears
 * App\Models\User::canAccessPanel(). The resources deliberately do not work
 * that way — see App\Filament\Concerns\AuthorizesWithApiPermissions — so the
 * widgets reuse the same permission vocabulary rather than inventing a second
 * one: a widget declares the `{action}-{subject}` permission that already
 * guards the data it summarises.
 *
 * Guard note: checkPermissionTo() resolves through
 * App\Models\User::guardName() => `api`, while the panel session itself is
 * established on `web`. It is used in preference to hasPermissionTo() because
 * it returns false for an unknown permission name instead of throwing
 * PermissionDoesNotExist.
 */
trait AuthorizesWidgetWithApiPermission
{
    public static function canView(): bool
    {
        return static::currentUserCan(static::$viewPermission);
    }

    /**
     * The panel user, or null when the widget is being rendered without one.
     */
    protected static function panelUser(): ?User
    {
        $user = Filament::getCurrentPanel()?->auth()->user() ?? auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Whether the panel user holds the given API permission.
     *
     * Widgets that summarise more than one subject call this per section so a
     * user who may read cars but not users does not get a drivers headline.
     */
    protected static function currentUserCan(string $permission): bool
    {
        return static::panelUser()?->checkPermissionTo($permission) ?? false;
    }
}
