<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use App\Support\NavigationAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    /**
     * Display the access management page.
     */
    public function index(Request $request): View
    {
        NavigationAccess::syncPermissions();

        $users = User::query()
            ->with('roles:id,name,code')
            ->orderBy('id')
            ->get();

        $permissionMap = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('permissions.code', 'like', 'menu.%')
            ->select('user_permissions.user_id', 'permissions.code')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => collect($rows)->pluck('code')->values()->all());

        $matrix = NavigationAccess::permissionMatrix();

        return view('settings.role-access', [
            ...$this->pageData(),
            'users' => $users,
            'matrix' => $matrix,
            'permissionMap' => $permissionMap,
            'stats' => [
                'user_count' => $users->count(),
                'menu_count' => $matrix->sum(fn (array $group): int => count($group['children'] ?? [])),
                'section_count' => $matrix->count(),
                'selected_count' => collect($permissionMap)->flatten()->count(),
            ],
        ]);
    }

    /**
     * Update role menu permissions.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        NavigationAccess::syncPermissions();

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $allowedCodes = Permission::query()
            ->where('code', 'like', 'menu.%')
            ->pluck('code')
            ->all();

        $selectedCodes = collect($validated['permissions'] ?? [])
            ->filter(fn ($code): bool => in_array($code, $allowedCodes, true))
            ->values();

        if ($user->isSuperadmin()) {
            $selectedCodes = collect($allowedCodes);
        }

        $permissionIds = Permission::query()
            ->whereIn('code', $selectedCodes)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($user, $permissionIds): void {
            DB::table('user_permissions')
                ->where('user_id', $user->id)
                ->whereIn('permission_id', Permission::query()->where('code', 'like', 'menu.%')->pluck('id'))
                ->delete();

            $timestamp = now();

            foreach ($permissionIds as $permissionId) {
                DB::table('user_permissions')->insert([
                    'user_id' => $user->id,
                    'permission_id' => $permissionId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        });

        return redirect()
            ->route('pengaturan.hak-akses')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Hak akses menu berhasil diperbarui untuk user '.$user->name.'.',
            ]);
    }

    /**
     * Resolve page metadata from navigation config.
     *
     * @return array{page: array<string, mixed>, section: string}
     */
    private function pageData(): array
    {
        $routeName = 'pengaturan.hak-akses';
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $group): bool => collect($group['children'] ?? [])
                ->contains(fn (array $child): bool => ($child['route'] ?? null) === $routeName));

        $page = collect($section['children'] ?? [])
            ->firstWhere('route', $routeName);

        return [
            'page' => $page ?? ['label' => 'Hak Akses'],
            'section' => $section['label'] ?? 'Pengaturan',
        ];
    }
}
