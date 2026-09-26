<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Super Admin only: view the permission catalog and edit the per-user
 * permission overrides for Administrator, Staff and Faculty accounts.
 *
 * Rules enforced here:
 *  - only super admins may edit permissions (route middleware + checks)
 *  - super admin accounts can never be targeted, not even by super admins
 *  - only Admin/Staff/Faculty users are editable
 */
class PermissionController extends Controller
{
    /** Editable roles — Students are mobile-app only. */
    private const EDITABLE_ROLES = ['Administrator', 'Staff', 'Faculty'];

    /** The permission editor UI: catalog + all editable users with their maps. */
    public function index(Request $request)
    {
        $users = User::with(['role:id,name', 'permissions'])
            ->whereHas('role', fn ($q) => $q->whereIn('name', self::EDITABLE_ROLES))
            ->where('is_super_admin', false)
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'username', 'email', 'role_id', 'account_status', 'is_super_admin']);

        return response()->json([
            'modules' => collect(config('permissions.modules'))
                ->map(fn ($m, $key) => [
                    'key' => $key,
                    'label' => $m['label'],
                    'description' => $m['description'],
                    'actions' => array_values($m['actions']),
                ])->values(),
            'users' => $users->map(fn ($u) => $this->presentUser($u)),
        ]);
    }

    /** Show one user's effective permission map. */
    public function show(Request $request, User $user)
    {
        $this->guardEditable($request, $user);

        return response()->json($this->presentUser($user->load(['role', 'permissions'])));
    }

    /** Replace a user's overrides with the submitted map. */
    public function update(Request $request, User $user)
    {
        $this->guardEditable($request, $user);

        $validModules = array_keys(config('permissions.modules'));

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*.module' => ['required', 'string', 'in:' . implode(',', $validModules)],
            'permissions.*.actions' => ['required', 'array', 'min:1'],
            'permissions.*.actions.*' => ['string', 'max:50'],
        ]);

        // Validate actions against the catalog, always including "view".
        $catalog = config('permissions.modules');
        $rows = [];
        foreach ($data['permissions'] as $perm) {
            $module = $perm['module'];
            $allowed = $catalog[$module]['actions'];
            $actions = array_values(array_unique(array_intersect($perm['actions'], $allowed)));

            if (!in_array('view', $actions, true)) {
                return response()->json([
                    'message' => "The '{$module}' module requires at least the 'view' action.",
                ], 422);
            }

            $rows[$module] = ['actions' => $actions];
        }

        $user->permissions()->delete();
        foreach ($rows as $module => $row) {
            $user->permissions()->create(['module' => $module, 'actions' => $row['actions']]);
        }

        // Live sessions keep working, but stale capability caches must go.
        $user->setRelation('permissions', $user->permissions()->get());

        return response()->json([
            'message' => 'Permissions updated successfully.',
            'user' => $this->presentUser($user->load(['role', 'permissions'])),
        ]);
    }

    /** Reset a user to their role defaults (delete all overrides). */
    public function reset(Request $request, User $user)
    {
        $this->guardEditable($request, $user);

        $user->permissions()->delete();

        return response()->json([
            'message' => 'Permissions reset to role defaults.',
            'user' => $this->presentUser($user->load(['role', 'permissions'])),
        ]);
    }

    /* ------------------------------------------------------------ */

    /** 403 unless the target is a non-super-admin Admin/Staff/Faculty user. */
    private function guardEditable(Request $request, User $user): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Only the Super Admin can manage permissions.');

        $roleName = $user->role?->name;

        if ($user->isSuperAdmin() || !in_array($roleName, self::EDITABLE_ROLES, true)) {
            abort(403, 'Permissions can only be managed for Admin, Staff, and Faculty users.');
        }
    }

    private function presentUser(User $u): array
    {
        $defaults = config('permissions.role_defaults.' . ($u->role?->name ?? ''), []);

        return [
            'id' => $u->id,
            'first_name' => $u->first_name,
            'middle_name' => $u->middle_name,
            'last_name' => $u->last_name,
            'username' => $u->username,
            'email' => $u->email,
            'role' => $u->role?->only(['id', 'name']),
            'account_status' => $u->account_status,
            'permission_map' => $u->permissionMap(),
            'role_defaults' => $defaults === '*' ? ['*' => ['*']] : $defaults,
            'has_overrides' => $u->permissions->isNotEmpty(),
        ];
    }
}
