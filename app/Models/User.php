<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\NavigationAccess;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    /**
     * Permissions assigned directly to the user.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withTimestamps();
    }

    /**
     * Determine whether the user has one of the given roles.
     *
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roleCodes = is_array($roles) ? $roles : [$roles];

        return $this->roles()
            ->whereIn('code', $roleCodes)
            ->exists();
    }

    /**
     * Determine whether the user is a superadmin.
     */
    public function isSuperadmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /**
     * Resolve all permission codes assigned through roles.
     */
    public function permissionCodes(): Collection
    {
        return once(function (): Collection {
            $directCodes = $this->permissions()
                ->where('permissions.code', 'like', 'menu.%')
                ->pluck('permissions.code')
                ->unique()
                ->values();

            if ($directCodes->isNotEmpty()) {
                return $directCodes;
            }

            return $this->roles()
                ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('permissions.code', 'like', 'menu.%')
                ->pluck('permissions.code')
                ->unique()
                ->values();
        });
    }

    /**
     * Determine whether the user has the given permission code.
     */
    public function hasPermissionCode(string $code): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->permissionCodes()->contains($code);
    }

    /**
     * Determine whether the user may access the route.
     */
    public function canAccessRoute(?string $routeName): bool
    {
        return NavigationAccess::canAccessRoute($this, $routeName);
    }
}
