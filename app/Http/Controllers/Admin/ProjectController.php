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

        return redirect()->route('admin.projects.index')
            ->with('flash', ['type' => 'success', 'message' => __('Project created successfully.')]);
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

        return redirect()->route('admin.projects.index')
            ->with('flash', ['type' => 'success', 'message' => __('Project updated successfully.')]);
    }

    public function assignUsers(AssignProjectUsersRequest $request, Project $project): RedirectResponse
    {
        $pivotData = collect($request->validated('users'))->mapWithKeys(fn ($item) => [
            $item['user_id'] => [
                'project_role' => $item['project_role'],
                'assigned_at' => now(),
                'assigned_by' => $request->user()->id,
            ],
        ])->all();

        $project->users()->sync($pivotData);

        return back()->with('flash', ['type' => 'success', 'message' => __('Users assigned successfully.')]);
    }
}
