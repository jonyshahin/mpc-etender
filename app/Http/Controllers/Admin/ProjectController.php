<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignProjectUsersRequest;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Columns the list may be ordered by.
     *
     * A whitelist because orderBy() does not check the column: Laravel
     * validates the direction and throws on anything but asc/desc, but hands
     * the column straight to the grammar, so `?sort=nope` was a 500.
     */
    private const SORTABLE = [
        'name',
        'code',
        'location',
        'status',
        'start_date',
        'end_date',
        'created_at',
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search'));
        $status = $request->input('status');

        // Everything bar the status filter, so the tab counts can come off the
        // same scope the rows do.
        $base = fn () => Project::query()
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%");
            }));

        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $projects = $base()
            // select() before withCount(): select() replaces the select list,
            // so calling it afterwards drops the count subqueries entirely —
            // the Tenders and Team Size columns were rendering empty.
            ->select('id', 'name', 'name_ar', 'code', 'location', 'client_name', 'status', 'start_date', 'end_date', 'created_at')
            ->withCount(['tenders', 'users'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/Projects/Index', [
            'projects' => $projects,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusCounts' => $this->projectStatusCounts($base()),
            'summary' => $this->projectSummary($base()),
            'statusOptions' => ProjectStatus::options(),
        ]);
    }

    /**
     * How many projects sit at each status, every status present.
     *
     * @return array<string, int>
     */
    private function projectStatusCounts(Builder $query): array
    {
        $counts = $query->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return collect(ProjectStatus::cases())
            ->mapWithKeys(fn (ProjectStatus $case) => [$case->value => (int) ($counts[$case->value] ?? 0)])
            ->all();
    }

    /**
     * Headline figures, on the same scope as the rows beneath them.
     *
     * @return array<string, int>
     */
    private function projectSummary(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', ProjectStatus::Active)->count(),
            'tenders' => (int) Tender::whereIn('project_id', (clone $query)->select('id'))->count(),
            // A project nobody is assigned to hides every one of its tenders,
            // including from a super admin — worth surfacing, not burying.
            'unstaffed' => (clone $query)->doesntHave('users')->count(),
        ];
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
            'statusOptions' => ProjectStatus::options(),
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
