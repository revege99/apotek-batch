<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use App\Support\NavigationAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use RuntimeException;

class UserAccessController extends Controller
{
    /**
     * Display the user and access management page.
     */
    public function index(Request $request): View
    {
        NavigationAccess::syncPermissions();

        $search = trim((string) $request->query('search', ''));
        $roleId = (int) $request->query('role_id', 0);
        $roles = $this->availableRoles();
        $editingUser = $this->resolveEditingUser($request);

        $items = User::query()
            ->with('roles:id,code,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($roleId > 0, fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('roles.id', $roleId)))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('settings.user-access', [
            ...$this->pageData(),
            'items' => $items,
            'roles' => $roles,
            'roleId' => $roleId,
            'search' => $search,
            'editingUser' => $editingUser,
            'stats' => [
                'total' => User::query()->count(),
                'superadmin' => User::query()->whereHas('roles', fn ($query) => $query->where('code', 'superadmin'))->count(),
                'admin' => User::query()->whereHas('roles', fn ($query) => $query->where('code', 'admin'))->count(),
                'role_count' => $roles->count(),
            ],
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            '_modal' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ], [
            'role_id.required' => 'Pilih role user terlebih dahulu.',
            'username.required' => 'Username login wajib diisi.',
        ]);

        DB::transaction(function () use ($validated): void {
            $user = User::query()->create([
                'name' => trim((string) $validated['name']),
                'username' => strtolower(trim((string) $validated['username'])),
                'email' => trim((string) $validated['email']),
                'password' => Hash::make((string) $validated['password']),
            ]);

            $user->roles()->sync([(int) $validated['role_id']]);
            $this->syncUserMenuPermissionsFromRole($user, (int) $validated['role_id']);
        });

        return redirect()
            ->route('pengaturan.user')
            ->with('toast', [
                'type' => 'success',
                'message' => 'User baru berhasil ditambahkan.',
            ]);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            '_modal' => ['nullable', 'string'],
            '_edit_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ], [
            'role_id.required' => 'Pilih role user terlebih dahulu.',
            'username.required' => 'Username login wajib diisi.',
        ]);

        $targetRole = Role::query()->findOrFail((int) $validated['role_id']);

        try {
            $this->guardRoleMutation($request->user(), $user, $targetRole);

            DB::transaction(function () use ($user, $validated, $targetRole): void {
                $payload = [
                    'name' => trim((string) $validated['name']),
                    'username' => strtolower(trim((string) $validated['username'])),
                    'email' => trim((string) $validated['email']),
                ];

                if (filled($validated['password'] ?? null)) {
                    $payload['password'] = Hash::make((string) $validated['password']);
                }

                $user->update($payload);
                $user->roles()->sync([$targetRole->id]);
                $this->syncUserMenuPermissionsFromRole($user, $targetRole->id);
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('pengaturan.user', ['edit' => $user->id])
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('pengaturan.user')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data user berhasil diperbarui.',
            ]);
    }

    /**
     * Remove a user from access management.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ($actor && $actor->id === $user->id) {
            return redirect()
                ->route('pengaturan.user')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'User yang sedang login tidak bisa dihapus dari halaman ini.',
                ]);
        }

        if ($user->hasRole('superadmin') && $this->superadminCount() <= 1) {
            return redirect()
                ->route('pengaturan.user')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Superadmin terakhir tidak boleh dihapus.',
                ]);
        }

        DB::transaction(function () use ($user): void {
            $user->roles()->detach();
            $user->delete();
        });

        return redirect()
            ->route('pengaturan.user')
            ->with('toast', [
                'type' => 'success',
                'message' => 'User berhasil dihapus.',
            ]);
    }

    /**
     * Resolve role constraints before changing a user.
     */
    private function guardRoleMutation(?User $actor, User $targetUser, Role $targetRole): void
    {
        if ($actor && $actor->id === $targetUser->id && $targetUser->hasRole('superadmin') && $targetRole->code !== 'superadmin') {
            throw_if($this->superadminCount() <= 1, RuntimeException::class, 'Superadmin terakhir tidak boleh menurunkan role dirinya sendiri.');
        }

        if ($targetUser->hasRole('superadmin') && $targetRole->code !== 'superadmin' && $this->superadminCount() <= 1) {
            throw new RuntimeException('Superadmin terakhir tidak boleh dipindahkan ke role lain.');
        }
    }

    /**
     * Build selectable roles with permission summaries.
     */
    private function availableRoles()
    {
        $permissionMap = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.code', 'like', 'menu.%')
            ->select('role_permissions.role_id', 'permissions.name')
            ->orderBy('permissions.module')
            ->orderBy('permissions.name')
            ->get()
            ->groupBy('role_id');

        return Role::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) use ($permissionMap): Role {
                $permissions = collect($permissionMap->get($role->id, collect()))
                    ->pluck('name')
                    ->values();

                $role->permission_names = $permissions;
                $role->permission_summary = $permissions->implode(', ');

                return $role;
            });
    }

    /**
     * Resolve the user being edited from query string.
     */
    private function resolveEditingUser(Request $request): ?User
    {
        $editId = (int) $request->query('edit', 0);

        if ($editId <= 0) {
            return null;
        }

        return User::query()
            ->with('roles:id,code,name')
            ->find($editId);
    }

    /**
     * Count current superadmin users.
     */
    private function superadminCount(): int
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('code', 'superadmin'))
            ->count();
    }

    /**
     * Resolve page metadata from navigation config.
     *
     * @return array{page: array<string, mixed>, section: string}
     */
    private function pageData(): array
    {
        $routeName = 'pengaturan.user';
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $group): bool => collect($group['children'] ?? [])
                ->contains(fn (array $child): bool => ($child['route'] ?? null) === $routeName));

        $page = collect($section['children'] ?? [])
            ->firstWhere('route', $routeName);

        return [
            'page' => $page ?? ['label' => 'User'],
            'section' => $section['label'] ?? 'Pengaturan',
        ];
    }

    /**
     * Copy current role menu permissions into the user direct access table.
     */
    private function syncUserMenuPermissionsFromRole(User $user, int $roleId): void
    {
        $user->loadMissing('roles:id,code');

        if ($user->roles->contains(fn (Role $role): bool => $role->code === 'superadmin')) {
            $permissionIds = Permission::query()
                ->where('code', 'like', 'menu.%')
                ->pluck('id')
                ->all();
        } else {
        $permissionIds = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.code', 'like', 'menu.%')
            ->pluck('role_permissions.permission_id')
            ->all();
        }

        DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->whereIn('permission_id', \App\Models\Permission::query()->where('code', 'like', 'menu.%')->pluck('id'))
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
    }
}
