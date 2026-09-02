<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Columns the user list may be ordered by.
     *
     * A whitelist because orderBy() does not check the column: Laravel
     * validates the direction and throws on anything but asc/desc, but hands
     * the column straight to the grammar, so `?sort=nope` was a 500.
     */
    private const SORTABLE = [
        'name',
        'email',
        'is_active',
        'last_login_at',
        'created_at',
    ];

    /**
     * The two states an account can be in.
     *
     * `is_active` is a boolean column rather than an enum, so there is no case
     * in App\Enums to read this from. The tabs, the filter and the counts all
     * come off this one list so they cannot drift apart.
     */
    private const STATUSES = ['active', 'inactive'];

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
     *
     * Must be called inside the transaction that performs the write. The check
     * was a bare read followed by an unrelated update: two concurrent
     * demotions of two different super admins each saw the other as the one
     * still remaining, both passed, and the system was left with none. Taking
     * a lock over every super-admin row — the target included, so the two
     * requests always overlap — serialises them, and the second then reads the
     * first's write rather than the state that preceded it.
     */
    private function assertNotLastSuperAdmin(User $user): void
    {
        if (! $user->isSuperAdmin()) {
            return;
        }

        $superAdmins = fn () => User::whereHas('role', fn ($q) => $q->where('slug', Role::SUPER_ADMIN));

        $superAdmins()->lockForUpdate()->pluck('users.id');

        $remaining = $superAdmins()
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->exists();

        abort_if(! $remaining, 422, __('This is the last active super admin.'));
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search'));
        $roleId = $request->input('role_id') ?: null;
        // Tri-state, and only ever one of the two names the tabs use. The old
        // filter read `$request->has('is_active')` and coerced with boolean(),
        // so an empty `?is_active=` — which is what a cleared select submits —
        // narrowed the list to the deactivated accounts.
        $status = in_array($request->input('status'), self::STATUSES, true)
            ? $request->input('status')
            : null;

        // Everything bar the active/inactive filter, so the tab counts come off
        // the same scope the rows do.
        $base = fn () => User::query()
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($roleId, fn ($q) => $q->where('role_id', $roleId));

        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $can = $this->capabilityChecker($request->user());

        $users = $base()
            ->with('role:id,name,slug')
            // select() before withCount(): select() replaces the select list,
            // so calling it afterwards drops the count subquery and every row
            // arrives with no projects_count at all.
            ->select('id', 'name', 'email', 'role_id', 'is_active', 'is_2fa_enabled', 'last_login_at', 'created_at')
            ->withCount('projects')
            ->when($status !== null, fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'is_2fa_enabled' => $user->is_2fa_enabled,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                'projects_count' => $user->projects_count,
                'role' => $user->role?->only('id', 'name', 'slug'),
                // The list offered Edit and Delete on every row, including the
                // super admins a plain admin may not administer and the actor's
                // own account. The policy refuses both, so the only feedback
                // was a 403 on a button that looked available.
                'can_edit' => $can('update', $user),
                'can_deactivate' => $user->is_active && $can('deactivate', $user),
            ]);

        return Inertia::render('admin/Users/Index', [
            'users' => $users,
            'roles' => Role::select('id', 'name', 'slug')->orderBy('name')->get(),
            // The create dialog was handed the full list while store()
            // authorizes assignRole, so a plain admin was offered Super Admin
            // and got a 403 after filling the form in. The filter dropdown
            // above still gets every role — searching for super admins is not
            // the same as being able to make one.
            'assignableRoles' => $this->assignableRoles($request->user()),
            // The route group gates on the admin *role*; StoreUserRequest gates
            // on the `admin.users` *permission*. A role holding one without the
            // other reached this page and saw a button that 403s on submit.
            'canCreate' => $request->user()->hasPermission('admin.users'),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'role_id' => $roleId,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusCounts' => $this->userStatusCounts($base()),
            'summary' => $this->userSummary($base()),
            'statusOptions' => array_map(
                fn (string $value) => ['value' => $value, 'labelKey' => "status.{$value}"],
                self::STATUSES,
            ),
        ]);
    }

    /**
     * Whether this actor may edit or deactivate a given row.
     *
     * The policy is asked once per distinct case rather than once per row: its
     * answers turn on two facts about the target — whether it is a super admin
     * and whether it is the actor's own account — while hasPermission() runs a
     * query on every call. Per row that was thirty queries on a fifteen-row
     * page to arrive at four different answers.
     *
     * @return Closure(string, User): bool
     */
    private function capabilityChecker(User $actor): Closure
    {
        $decided = [];

        return function (string $ability, User $target) use ($actor, &$decided): bool {
            $key = implode(':', [
                $ability,
                $target->isSuperAdmin() ? 'super' : 'standard',
                $target->id === $actor->id ? 'self' : 'other',
            ]);

            return $decided[$key] ??= $actor->can($ability, $target);
        };
    }

    /**
     * How many accounts are active and how many are not.
     *
     * Two counts rather than one grouped query: a boolean column groups as 1/0
     * on MySQL and SQLite but as true/false on Postgres, and an array keyed off
     * that difference reads back empty on the database it was not written for.
     *
     * @return array<string, int>
     */
    private function userStatusCounts(Builder $query): array
    {
        return [
            'active' => (clone $query)->where('is_active', true)->count(),
            'inactive' => (clone $query)->where('is_active', false)->count(),
        ];
    }

    /**
     * Headline figures, on the same scope as the rows beneath them.
     *
     * @return array<string, int>
     */
    private function userSummary(Builder $query): array
    {
        $active = fn () => (clone $query)->where('is_active', true);

        return [
            'total' => (clone $query)->count(),
            'active' => $active()->count(),
            // An account nobody has ever signed in to is either a handover that
            // never happened or a credential still sitting in someone's inbox.
            'never_signed_in' => $active()->whereNull('last_login_at')->count(),
            // 2FA is mandatory for internal staff, so an active account without
            // it is a standing exception rather than a preference.
            'without_2fa' => $active()->where('is_2fa_enabled', false)->count(),
        ];
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

        $projectIds = $data['project_ids'] ?? [];
        unset($data['project_ids']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        // `is_active` is a required field on this form and reaches the same
        // column destroy() guards, but every check here used to sit inside the
        // role branch — so a request that changed only the checkbox skipped all
        // of them. Deactivating through the edit form answers to the same two
        // rules as deactivating through the row action.
        $isDeactivating = $user->is_active && ! $data['is_active'];

        DB::transaction(function () use ($request, $user, $data, $projectIds, $isDeactivating) {
            if ($data['role_id'] !== $user->role_id) {
                $this->authorize('changeOwnRole', $user);
                $this->authorize('assignRole', [User::class, $this->roleFrom($data['role_id'])]);
                $this->assertNotLastSuperAdmin($user);
            }

            if ($isDeactivating) {
                $this->authorize('deactivate', $user);
                $this->assertNotLastSuperAdmin($user);
            }

            $user->update($data);

            $pivotData = collect($projectIds)->mapWithKeys(fn ($id) => [
                $id => ['project_role' => 'member', 'assigned_at' => now(), 'assigned_by' => $request->user()->id],
            ])->all();
            $user->projects()->sync($pivotData);
        });

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

        // One transaction with the check, so the row lock it takes still holds
        // when the write lands.
        DB::transaction(function () use ($user) {
            $this->assertNotLastSuperAdmin($user);

            $user->update(['is_active' => false]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deactivated successfully.')]);

        return redirect()->route('admin.users.index');
    }
}
