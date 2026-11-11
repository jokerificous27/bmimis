<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\RolePermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property Role $role
 * @property Permission $permission
 */
#[Fillable(['role', 'permission'])]
class RolePermission extends Model
{
    /** @use HasFactory<RolePermissionFactory> */
    use HasFactory;

    private const CACHE_KEY = 'role_permissions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'permission' => Permission::class,
        ];
    }

    /**
     * Permission values currently granted to a role.
     *
     * @return list<string>
     */
    public static function valuesFor(Role $role): array
    {
        $map = Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()
            ->get(['role', 'permission'])
            ->groupBy(fn (self $row) => $row->role->value)
            ->map(fn ($rows) => $rows->map(fn (self $row) => $row->permission->value)->values()->all())
            ->all());

        return $map[$role->value] ?? [];
    }

    /**
     * Replace the permissions of a role.
     *
     * @param  list<string>  $permissions
     */
    public static function sync(Role $role, array $permissions): void
    {
        DB::transaction(function () use ($role, $permissions): void {
            self::query()->where('role', $role->value)->delete();

            foreach (array_unique($permissions) as $permission) {
                self::create(['role' => $role, 'permission' => Permission::from($permission)]);
            }
        });

        self::flushCache();
    }

    /**
     * Seed the default permission matrix for every role.
     */
    public static function seedDefaults(): void
    {
        foreach (Role::cases() as $role) {
            self::sync($role, array_map(fn (Permission $permission) => $permission->value, $role->defaultPermissions()));
        }
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
