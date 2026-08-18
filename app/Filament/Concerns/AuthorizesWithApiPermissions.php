<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Authorizes Filament resource actions with the API's own permission names.
 *
 * The application already models authorization as spatie permissions named
 * `{action}-{subject}` (`index-car`, `destroy-fuel-price`, ...) stored under
 * the `api` guard. Rather than duplicating that in Filament — or relying on
 * App\Policies\*, several of which only implement the subset of methods the
 * REST controllers call — this maps Filament's action names onto the existing
 * permissions.
 *
 * Guard note: the check runs through App\Models\User::checkPermissionTo(),
 * which resolves permissions via App\Models\User::guardName() => `api`. The
 * panel session itself is established on the `web` guard, so this is the point
 * where the two deliberately diverge. checkPermissionTo() is used instead of
 * hasPermissionTo() because it returns false for unknown permission names
 * rather than throwing PermissionDoesNotExist.
 *
 * This sits *behind* App\Models\User::canAccessPanel(), which already restricts
 * the panel to the `admin` role; it is a second, per-action layer.
 */
trait AuthorizesWithApiPermissions
{
    /**
     * Filament action name => the `{action}` half of the permission name.
     *
     * @return array<string, string>
     */
    public static function permissionActionMap(): array
    {
        return [
            'viewAny' => 'index',
            'view' => 'show',
            'create' => 'create',
            'replicate' => 'create',
            'update' => 'edit',
            'reorder' => 'edit',
            'delete' => 'destroy',
            'deleteAny' => 'destroy',
            'forceDelete' => 'force-delete',
            'forceDeleteAny' => 'force-delete',
            'restore' => 'restore',
            'restoreAny' => 'restore',
        ];
    }

    /**
     * The `{subject}` half of the permission name, e.g. `car-model`.
     */
    public static function getPermissionSubject(): string
    {
        return static::$permissionSubject;
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        if (static::shouldSkipAuthorization()) {
            return Response::allow();
        }

        $name = match (true) {
            $action instanceof \BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        $permissionAction = static::permissionActionMap()[$name] ?? null;

        if ($permissionAction === null) {
            return parent::getAuthorizationResponse($action, $record);
        }

        $user = Filament::getCurrentPanel()?->auth()->user() ?? auth()->user();

        if (! $user instanceof User) {
            return Response::deny();
        }

        return $user->checkPermissionTo($permissionAction.'-'.static::getPermissionSubject())
            ? Response::allow()
            : Response::deny();
    }
}
