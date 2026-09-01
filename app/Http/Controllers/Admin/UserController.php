<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * The roles this actor is allowed to hand out.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(User $actor)
    {
        return Role::select('id', 'name', 'slug')
            ->when(! $actor->isSuperAdmin(), fn ($q) => $q->where('slug', '!=', Role::SUPER_ADMIN))
            ->orderBy('name')
            ->get();
    }

    private function roleFrom(string $roleId): Role
    {
        return Role::findOrFail($roleId);
    }

    /**
     * Refuse to remove the last super admin.
     *
     * Every other rule here assumes a super admin exists to apply it —
     * they alone can grant the role back, so losing the last one is not
     * recoverable from inside the app.
     */
    private function assertNotLastSuperAdmin(User $user): void
    {
        if (! $user->isSuperAdmin()) {
            return;
        }

        $remaining = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))
            ->exists();

        abort_if(! $remaining, 422, __('This is the last active super admin.'));
    }

    public function index(Request $request): Response
    {
        $query = User::with('role:id,name,slug')
            ->select('id', 'name', 'email', 'role_id', 'is_active', 'last_login_at', 'created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleId = $request->input('role_id')) {
            $query->where('role_id', $roleId);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('direction', 'desc');
        $query->orderBy($sortField, $sortDir);

        return Inertia::render('admin/Users/Index', [
            'users' => $query->paginate(15)->withQueryString(),
            'roles' => Role::select('id', 'name', 'slug')->orderBy('name')->get(),
            'filters' => $request->only('search', 'role_id', 'is_active', 'sort', 'direction'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->authorize('assignRole', [User::class, $this->roleFrom($data['role_id'])]);
        $projectIds = $data['project_ids'] ?? [];
        unset($data['project_ids']);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        if ($projectIds) {
            $pivotData = collect($projectIds)->mapWithKeys(fn ($id) => [
                $id => ['project_role' => 'member', 'assigned_at' => now(), 'assigned_by' => $request->user()->id],
            ])->all();
            $user->projects()->attach($pivotData);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created successfully.')]);

        return redirect()->route('admin.users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('admin/Users/Form', [
            'user' => $user->load('role:id,name'),
            'userProjectIds' => $user->projects()->pluck('projects.id'),
            // Only the roles this actor may actually grant. Offering a
            // choice the server will refuse is a trap, not a safeguard.
            'roles' => $this->assignableRoles($request->user()),
            // Nobody sets their own level; the field is locked rather than
            // silently reverted on save.
            'canChangeRole' => $request->user()->id !== $user->id,
            'projects' => Project::select('id', 'name', 'code')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // role_id was validated only as exists:roles,id, and nothing looked at
        // which role it was or whose account this is — so anyone with
        // admin.users could hand out super admin, take it away, or take it.
        $this->authorize('update', $user);

        $data = $request->validated();

        if ($data['role_id'] !== $user->role_id) {
            $this->authorize('changeOwnRole', $user);
            $this->authorize('assignRole', [User::class, $this->roleFrom($data['role_id'])]);
            $this->assertNotLastSuperAdmin($user);
        }
        $projectIds = $data['project_ids'] ?? [];
        unset($data['project_ids']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        $pivotData = collect($projectIds)->mapWithKeys(fn ($id) => [
            $id => ['project_role' => 'member', 'assigned_at' => now(), 'assigned_by' => $request->user()->id],
        ])->all();
        $user->projects()->sync($pivotData);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated successfully.')]);

        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === request()->user()->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot delete your own account.')]);

            return back();
        }

        // Previously only self-deletion was blocked, so an admin could
        // deactivate every super admin and leave nobody able to undo it.
        $this->authorize('deactivate', $user);
        $this->assertNotLastSuperAdmin($user);

        $user->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deactivated successfully.')]);

        return redirect()->route('admin.users.index');
    }
}
