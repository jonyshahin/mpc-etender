<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignProjectUsersRequest;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Project::withCount(['tenders', 'users'])
            ->select('id', 'name', 'code', 'location', 'status', 'start_date', 'end_date', 'created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $query->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'));

        return Inertia::render('admin/Projects/Index', [
            'projects' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only('search', 'status', 'sort', 'direction'),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Project::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created successfully.')]);

        return redirect()->route('admin.projects.index');
    }

    public function edit(Project $project): Response
    {
        return Inertia::render('admin/Projects/Form', [
            'project' => $project,
            'assignedUsers' => $project->users()
                ->select('users.id', 'users.name', 'users.email')
                ->get()
                ->map(fn ($u) => [
                    'user_id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'project_role' => $u->pivot->project_role,
                ]),
            'availableUsers' => User::active()
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated successfully.')]);

        return redirect()->route('admin.projects.index');
    }

    public function addUser(AssignProjectUsersRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $userId = $data['user_id'];

        if ($project->users()->where('users.id', $userId)->exists()) {
            return back()->withErrors(['user_id' => __('User is already assigned to this project.')]);
        }

        $project->users()->attach($userId, [
            'project_role' => $data['project_role'],
            'assigned_at' => now(),
            'assigned_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User added to project.')]);

        return back();
    }

    public function updateUserRole(Request $request, Project $project, User $user): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'project_role' => ['required', 'string', 'max:50'],
        ]);

        if (! $project->users()->where('users.id', $user->id)->exists()) {
            return back()->withErrors(['user_id' => __('User is not assigned to this project.')]);
        }

        $project->users()->updateExistingPivot($user->id, [
            'project_role' => $data['project_role'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User role updated.')]);

        return back();
    }

    public function removeUser(Project $project, User $user): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->users()->detach($user->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User removed from project.')]);

        return back();
    }
}
