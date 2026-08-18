<?php

namespace App\Filament\Support;

use App\Models\Permission;
use Illuminate\Support\Str;

/**
 * Groups spatie permission names for display in the admin panel.
 *
 * Permissions are named `{action}-{subject}` (see
 * Database\Seeders\RolePermissionsSeeder), e.g. `index-car`,
 * `force-delete-fuel-price`, `secure-download-document`. Subjects are
 * multi-word and overlapping (`car` vs `car-log` vs `car-model`,
 * `service` vs `service-center`), so a name is matched against the longest
 * subject it ends with rather than split on the first hyphen.
 */
class PermissionGroups
{
    /**
     * The subjects declared by RolePermissionsSeeder.
     *
     * @var list<string>
     */
    public const SUBJECTS = [
        'car',
        'fill-up',
        'trip',
        'parking-record',
        'service-center',
        'service',
        'item',
        'brand',
        'car-model',
        'document',
        'user',
        'role',
        'permission',
        'car-log',
        'fuel-price',
        'reminder',
    ];

    public const OTHER = 'other';

    /**
     * Subjects ordered longest first so `car-model` wins over `car`.
     *
     * @return list<string>
     */
    public static function subjectsBySpecificity(): array
    {
        $subjects = self::SUBJECTS;

        usort($subjects, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $subjects;
    }

    /**
     * The subject a permission name belongs to.
     */
    public static function subjectFor(string $permissionName): string
    {
        foreach (self::subjectsBySpecificity() as $subject) {
            if ($permissionName === $subject || str_ends_with($permissionName, '-'.$subject)) {
                return $subject;
            }
        }

        return self::OTHER;
    }

    /**
     * The action part of a permission name, e.g. `force-delete-car` => "Force delete".
     */
    public static function actionLabelFor(string $permissionName): string
    {
        $subject = self::subjectFor($permissionName);

        if ($subject === self::OTHER) {
            return Str::headline($permissionName);
        }

        $action = rtrim(Str::beforeLast($permissionName, $subject), '-');

        return $action === '' ? Str::headline($permissionName) : Str::headline($action);
    }

    public static function groupLabel(string $subject): string
    {
        return $subject === self::OTHER ? 'Other' : Str::headline(Str::plural($subject));
    }

    /**
     * Every permission bucketed by subject.
     *
     * @return array<string, array<int|string, string>> subject => [permission id => action label]
     */
    public static function all(): array
    {
        $groups = [];

        foreach (Permission::query()->orderBy('name')->get() as $permission) {
            $groups[self::subjectFor($permission->name)][$permission->getKey()] = self::actionLabelFor($permission->name);
        }

        uksort($groups, function (string $a, string $b): int {
            if ($a === self::OTHER) {
                return 1;
            }

            if ($b === self::OTHER) {
                return -1;
            }

            return $a <=> $b;
        });

        return $groups;
    }
}
