<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements CanResetPassword
{
    use CanResetPasswordTrait;
    use HasApiTokens;
    use Notifiable;

    protected $fillable = [
        'role_id',
        'first_name',
        'middle_name',
        'last_name',
        'id_number',
        'profile_photo_path',
        'username',
        'email',
        'contact_number',
        'password',
        'account_status',
        'verified_at',
        'verified_by',
        'banned_at',
        'ban_reason',
        'is_super_admin',
        'email_verified_at',
        'email_verification_code',
        'email_code_expires_at',
        'id_front_path',
        'id_back_path',
        'id_submitted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'verified_at' => 'datetime',
            'banned_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'email_verified_at' => 'datetime',
            'email_code_expires_at' => 'datetime',
            'id_submitted_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------
     | Permissions (Super Admin managed)
     | ------------------------------------------------------------
     | Super admin  -> always everything (protected, not editable).
     | Other roles  -> role defaults, overridden by per-user rows in
     |                 user_permissions (module => [actions]).
     */

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /** Effective permission map: [module => [actions]] for this user. */
    public function permissionMap(): array
    {
        if ($this->isSuperAdmin()) {
            return ['*' => ['*']];
        }

        $defaults = config('permissions.role_defaults.'.($this->role?->name ?? ''), []);

        if ($defaults === '*') {
            return ['*' => ['*']];
        }

        $map = is_array($defaults) ? $defaults : [];

        foreach ($this->permissions as $override) {
            $map[$override->module] = array_values((array) $override->actions);
        }

        return $map;
    }

    public function canAccess(string $module, string $action = 'view'): bool
    {
        $map = $this->permissionMap();

        if (in_array('*', $map['*'] ?? [], true)) {
            return true;
        }

        $actions = $map[$module] ?? [];

        return in_array('*', $actions, true) || in_array($action, $actions, true);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(BorrowingRequest::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'Administrator';
    }

    /* ------------------------------------------------------------
     | Account status helpers
     | ------------------------------------------------------------
     | Lifecycle: a student self-registers as Pending, an admin
     | verifies them -> Active, and may later ban -> Banned.
     | Staff/faculty/admin created by the super admin start Active.
     |
     | Verification and ban status are INDEPENDENT dimensions:
     | `verified_at` records whether an admin approved the account,
     | while `account_status` only tracks access (Active/Banned).
     | Banning must never change verification, and unbanning must
     | restore exactly the pre-ban state.
     */

    public const STATUSES = ['Pending', 'Active', 'Banned'];

    public function isPending(): bool
    {
        return $this->account_status === 'Pending';
    }

    public function isActive(): bool
    {
        return $this->account_status === 'Active';
    }

    public function isBanned(): bool
    {
        return $this->account_status === 'Banned';
    }

    public function isStudent(): bool
    {
        return $this->role?->name === 'Student';
    }

    /**
     * Whether an administrator has approved this account's verification.
     * Driven solely by `verified_at` — independent of `account_status`,
     * so ban/unban can never alter it.
     *
     * Students are verified explicitly via the verify endpoint. Staff,
     * faculty and admin accounts are provisioned trusted by the Super
     * Admin (no verification step), so they count as verified.
     */
    public function isIdVerified(): bool
    {
        if (! $this->isStudent()) {
            return true;
        }

        return $this->verified_at !== null;
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
