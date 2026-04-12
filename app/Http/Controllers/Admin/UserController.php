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
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
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

    public function edit(User $user): Response
    {
        return Inertia::render('admin/Users/Form', [
            'user' => $user->load('role:id,name'),
            'userProjectIds' => $user->projects()->pluck('projects.id'),
            'roles' => Role::select('id', 'name', 'slug')->orderBy('name')->get(),
            'projects' => Project::select('id', 'name', 'code')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
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

        $user->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deactivated successfully.')]);

        return redirect()->route('admin.users.index');
    }
}
