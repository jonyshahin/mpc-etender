<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdatePermissionsRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Roles/Index', [
            'roles' => Role::withCount(['permissions', 'users'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Role::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created successfully.')]);

        return redirect()->route('admin.roles.index');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->is_system) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('System roles cannot be modified.')]);

            return back();
        }

        $role->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated successfully.')]);

        return redirect()->route('admin.roles.index');
    }

    public function permissions(Role $role): Response
    {
        return Inertia::render('admin/Roles/Permissions', [
            'role' => $role->load('permissions:id,slug'),
            'permissions' => Permission::orderBy('module')->orderBy('name')->get(),
            'rolePermissionIds' => $role->permissions()->pluck('permissions.id'),
        ]);
    }

    public function updatePermissions(UpdatePermissionsRequest $request, Role $role): RedirectResponse
    {
        $role->permissions()->sync($request->validated('permission_ids'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permissions updated successfully.')]);

        return redirect()->route('admin.roles.index');
    }
}
