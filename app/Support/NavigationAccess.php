<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NavigationAccess
{
    /**
     * Build visible navigation for the given user.
     */
    public static function navigationFor(?User $user): Collection
    {
        return collect(config('apotik.navigation'))
            ->map(function (array $group) use ($user): array {
                $children = collect($group['children'] ?? [])
                    ->filter(fn (array $child): bool => static::canAccessChild($user, $child))
                    ->values()
                    ->all();

                return [
                    ...$group,
                    'children' => $children,
                ];
            })
            ->filter(fn (array $group): bool => count($group['children'] ?? []) > 0)
            ->values();
    }

    /**
     * Determine whether the user may access the current route.
     */
    public static function canAccessRoute(?User $user, ?string $routeName): bool
    {
        if ($user === null || $routeName === null) {
            return false;
        }

        if ($user->isSuperadmin() || static::isExemptRoute($routeName)) {
            return true;
        }

        $child = static::findChildByRouteName($routeName);

        if ($child === null) {
            return true;
        }

        return static::canAccessChild($user, $child);
    }

    /**
     * Synchronize menu permissions from navigation config.
     */
    public static function syncPermissions(): void
    {
        $timestamp = now();
        $children = static::allChildren();

        foreach ($children as $group) {
            foreach ($group['children'] as $child) {
                Permission::query()->updateOrCreate([
                    'code' => static::permissionCode($child['route']),
                ], [
                    'name' => $child['label'],
                    'module' => static::moduleKey($group['label']),
                    'description' => 'Akses menu '.$child['label'],
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ]);
            }
        }

        static::syncLegacyRoleMappings($timestamp);
    }

    /**
     * Collect all menu permission items.
     */
    public static function permissionMatrix(): Collection
    {
        return static::allChildren()
            ->map(function (array $group): array {
                return [
                    'label' => $group['label'],
                    'summary' => $group['summary'] ?? '',
                    'children' => collect($group['children'])
                        ->map(fn (array $child): array => [
                            ...$child,
                            'permission_code' => static::permissionCode($child['route']),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();
    }

    /**
     * Find the navigation child matching the given route name.
     */
    public static function findChildByRouteName(string $routeName): ?array
    {
        return static::allChildren()
            ->flatMap(fn (array $group) => collect($group['children']))
            ->first(function (array $child) use ($routeName): bool {
                $baseRoute = (string) ($child['route'] ?? '');

                return $routeName === $baseRoute || Str::startsWith($routeName, $baseRoute.'.');
            });
    }

    /**
     * Build permission code for route.
     */
    public static function permissionCode(string $routeName): string
    {
        return 'menu.'.$routeName;
    }

    /**
     * Determine whether the user may access a menu child.
     */
    private static function canAccessChild(?User $user, array $child): bool
    {
        if ($user === null) {
            return false;
        }

        if (($child['superadmin_only'] ?? false) === true) {
            return $user->isSuperadmin();
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->hasPermissionCode(static::permissionCode((string) $child['route']));
    }

    /**
     * Load all grouped children from config.
     */
    private static function allChildren(): Collection
    {
        return collect(config('apotik.navigation'))
            ->filter(fn (array $group): bool => isset($group['children']))
            ->values();
    }

    /**
     * Determine whether the route should bypass menu access control.
     */
    private static function isExemptRoute(string $routeName): bool
    {
        return Str::startsWith($routeName, 'dashboard')
            || Str::startsWith($routeName, 'profile.')
            || Str::startsWith($routeName, 'verification.')
            || Str::startsWith($routeName, 'password.')
            || Str::startsWith($routeName, 'logout');
    }

    /**
     * Normalize module key from section label.
     */
    private static function moduleKey(string $label): string
    {
        return Str::slug($label);
    }

    /**
     * Seed route permissions based on existing coarse permission mapping.
     */
    private static function syncLegacyRoleMappings($timestamp): void
    {
        $legacyByModule = [
            'master-data' => 'manage_master_data',
            'pembelian' => 'manage_purchases',
            'penjualan' => 'manage_sales',
            'stok-batch' => 'manage_inventory',
            'keuangan' => 'manage_finance',
            'laporan' => 'view_reports',
            'pengaturan' => 'manage_settings',
            'setup-saldo-awal' => 'manage_settings',
        ];

        $legacyRoleMap = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('permissions.code', array_values($legacyByModule))
            ->select('role_permissions.role_id', 'permissions.code')
            ->get()
            ->groupBy('role_id');

        $permissionIdMap = Permission::query()
            ->where('code', 'like', 'menu.%')
            ->pluck('id', 'code');

        foreach (Role::query()->where('is_active', true)->get() as $role) {
            if (in_array($role->code, ['superadmin', 'admin'], true)) {
                foreach ($permissionIdMap as $permissionId) {
                    DB::table('role_permissions')->updateOrInsert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ], [
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }

                continue;
            }

            $legacyCodes = collect($legacyRoleMap->get($role->id, collect()))
                ->pluck('code')
                ->all();

            foreach (static::allChildren() as $group) {
                $module = static::moduleKey((string) $group['label']);
                $legacyCode = $legacyByModule[$module] ?? null;

                if ($legacyCode === null || ! in_array($legacyCode, $legacyCodes, true)) {
                    continue;
                }

                foreach ($group['children'] as $child) {
                    if (($child['superadmin_only'] ?? false) === true) {
                        continue;
                    }

                    $permissionId = $permissionIdMap[static::permissionCode((string) $child['route'])] ?? null;

                    if ($permissionId === null) {
                        continue;
                    }

                    DB::table('role_permissions')->updateOrInsert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ], [
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }
        }
    }

}
