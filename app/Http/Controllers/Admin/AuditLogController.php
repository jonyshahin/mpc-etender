<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The audit trail, read-only.
 *
 * There is deliberately no store/update/destroy here and there never will be:
 * AuditLog blocks save-on-existing and delete at the model level, so a mutation
 * route would only be a way to ask it to throw.
 */
class AuditLogController extends Controller
{
    /**
     * Columns the list may be ordered by.
     *
     * Unlike the other admin lists this page never took a sort column from the
     * request at all — it was hardcoded to latest('created_at') — so the
     * `?sort=` 500 that the tender, vendor and project lists carried could not
     * happen here. The whitelist arrives with the sortable headers, before
     * there is anything to exploit rather than after.
     */
    private const SORTABLE = [
        'created_at',
        'action',
        'auditable_type',
    ];

    /** Sentinel the page renders as "hidden" — see {@see self::redact()}. */
    private const REDACTED = '__redacted__';

    /**
     * Key fragments whose values must not be serialised into the page.
     *
     * old_values/new_values are free-form JSON: whatever a caller passed is
     * what ships. Bid is the cautionary case — it carried no $hidden and a
     * get-mutator that decrypted on access, so simply serialising the model
     * leaked sealed pricing. The same failure mode is available here for the
     * cost of one careless AuditLog::create([...$bid->getChanges()]).
     *
     * Fail closed: an auditor still sees *that* the key changed, just not the
     * value. Booleans and nulls pass through, so `must_change_password: true`
     * stays readable — a boolean cannot be the secret.
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'token',
        'secret',
        'encrypted',
        'pricing',
        'price',
        'total_amount',
        'hash',
        'otp',
        'two_factor',
        'recovery',
        'api_key',
        'signature',
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search'));
        $userId = $request->input('user_id');
        $action = $request->input('action');
        $entityType = $request->input('entity_type');

        // Normalised to a bare calendar day, or dropped. An unparseable value
        // is not echoed back either, so the control never redisplays garbage.
        $from = $this->calendarDay($request->input('from'));
        $to = $this->calendarDay($request->input('to'));

        // The window and the search, which every count below shares. The three
        // categorical filters stay out so each facet can be counted against a
        // scope that excludes only itself.
        $base = fn () => AuditLog::query()
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $inner) use ($search) {
                $inner->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('vendor', fn (Builder $v) => $v->where('company_name', 'like', "%{$search}%"));
            }))
            // Both bounds are project-zone calendar days turned into UTC
            // instants. Comparing the raw 'YYYY-MM-DD' against a UTC column —
            // which is what this did — shifts the window by the offset: under
            // Asia/Baghdad it dropped the first three hours of the `from` day
            // and swept in the first three of the day after `to`.
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $this->dayStart($from)))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $this->dayEnd($to)));

        // $except names the facet to leave out, so a facet's own counts do not
        // collapse to the selection the moment you pick from it.
        $scoped = fn (?string $except = null) => $base()
            ->when($userId && $except !== 'user_id', fn (Builder $q) => $q->where('user_id', $userId))
            ->when($action && $except !== 'action', fn (Builder $q) => $q->where('action', $action))
            ->when($entityType && $except !== 'entity_type', fn (Builder $q) => $q->where('auditable_type', $entityType));

        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $logs = $scoped()
            ->with(['user:id,name', 'vendor:id,company_name'])
            ->select('id', 'user_id', 'vendor_id', 'auditable_type', 'auditable_id', 'action', 'old_values', 'new_values', 'ip_address', 'created_at')
            ->orderBy($sort, $direction)
            // A request and the model events it triggers land in the same
            // second, and an unbroken tie makes page 2 a reshuffle of page 1.
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            // Explicit rows rather than the models: what the page needs, and
            // nothing else the columns happen to be holding.
            ->through(fn (AuditLog $log) => $this->row($log));

        return Inertia::render('admin/AuditLogs/Index', [
            'logs' => $logs,
            // Complete, because DataTable merges this into every sort request.
            // A partial echo sorts with the filters wiped, and the direction
            // toggle — which compares against filters.sort — never reaches
            // descending. The page did pass `filters` to the table, but fed it
            // $request->only(...), which on an unfiltered request is an empty
            // array and on any other omits sort and direction entirely.
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => $this->summary($base),
            'entityOptions' => $this->countsFor($scoped('entity_type'), 'auditable_type'),
            // Served from the rows that exist instead of a literal list. The
            // hardcoded one offered "exported" and "imported", which nothing in
            // the app has ever written, and omitted every HTTP method, every
            // vendor_category_request_* event and the password-reset events —
            // so most of the trail was unfilterable and two options were dead.
            'actionOptions' => $this->countsFor($scoped('action'), 'action'),
            'actorOptions' => $this->actorOptions($scoped('user_id')),
        ]);
    }

    /**
     * Headline figures for the window, before the categorical filters narrow it.
     *
     * @param  callable(): Builder  $base
     * @return array<string, int>
     */
    private function summary(callable $base): array
    {
        return [
            'total' => $base()->count(),
            'actors' => $base()->whereNotNull('user_id')->distinct()->count('user_id'),
            // Rows carrying a before/after payload. The rest are bare request
            // and access records, which is most of the table.
            'changes' => $base()
                ->where(fn (Builder $q) => $q->whereNotNull('old_values')->orWhereNotNull('new_values'))
                ->count(),
            // "Today" on the project's clock, not the server's.
            'today' => $base()
                ->where('created_at', '>=', CarbonImmutable::now($this->zone())->startOfDay()->utc())
                ->count(),
        ];
    }

    /**
     * Distinct values of $column within $query, commonest first.
     *
     * @return array<int, array{value: string, count: int}>
     */
    private function countsFor(Builder $query, string $column): array
    {
        return $query
            ->select($column, DB::raw('COUNT(*) as count'))
            ->groupBy($column)
            ->orderByDesc('count')
            ->orderBy($column)
            ->pluck('count', $column)
            ->map(fn ($count, $value) => ['value' => (string) $value, 'count' => (int) $count])
            ->values()
            ->all();
    }

    /**
     * The staff who appear in this window, by name.
     *
     * Bounded by the internal user list rather than by the size of the table:
     * a million rows still only name the people who wrote them.
     *
     * @return array<int, array{value: string, label: string, count: int}>
     */
    private function actorOptions(Builder $query): array
    {
        /** @var Collection<string, int> $counts */
        $counts = $query->whereNotNull('user_id')
            ->select('user_id', DB::raw('COUNT(*) as count'))
            ->groupBy('user_id')
            ->pluck('count', 'user_id');

        $names = User::whereIn('id', $counts->keys())->pluck('name', 'id');

        return $counts
            ->map(fn ($count, $id) => [
                'value' => (string) $id,
                // Deleting a user nulls the FK, so a name is always found —
                // but fail readable rather than blank if that stops holding.
                'label' => (string) ($names[$id] ?? $id),
                'count' => (int) $count,
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * One row, as the page consumes it.
     *
     * @return array<string, mixed>
     */
    private function row(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'created_at' => $log->created_at?->toJSON(),
            'action' => $log->action,
            'entity_type' => $log->auditable_type,
            'entity_id' => $log->auditable_id,
            'ip_address' => $log->ip_address,
            'actor' => $this->actor($log),
            'old_values' => $this->redact($log->old_values),
            'new_values' => $this->redact($log->new_values),
        ];
    }

    /**
     * Who did it.
     *
     * The page read user_id alone and labelled everything else "System", so a
     * vendor changing their own password — vendor_id set, user_id null by
     * design — was filed under nobody.
     *
     * @return array{type: string, name: string|null}
     */
    private function actor(AuditLog $log): array
    {
        if ($log->user) {
            return ['type' => 'user', 'name' => $log->user->name];
        }

        if ($log->vendor) {
            return ['type' => 'vendor', 'name' => $log->vendor->company_name];
        }

        return ['type' => 'system', 'name' => null];
    }

    /**
     * Replaces sensitive values with {@see self::REDACTED}, at any depth.
     *
     * $inherited carries the verdict down: a sensitive key holding an array
     * hides the whole subtree, not only the keys under it that happen to look
     * sensitive themselves.
     *
     * @param  array<array-key, mixed>|null  $values
     * @return array<array-key, mixed>|null
     */
    private function redact(?array $values, bool $inherited = false): ?array
    {
        if ($values === null) {
            return null;
        }

        $out = [];

        foreach ($values as $key => $value) {
            $sensitive = $inherited || $this->isSensitiveKey((string) $key);

            if (is_array($value)) {
                $out[$key] = $this->redact($value, $sensitive);
            } elseif ($sensitive && ! is_bool($value) && $value !== null) {
                $out[$key] = self::REDACTED;
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A request value as a real calendar day, or null.
     *
     * checkdate() rather than a Carbon parse: createFromFormat happily rolls
     * 2026-13-45 forward into 2027, which would filter on a window nobody
     * asked for instead of ignoring the input.
     */
    private function calendarDay(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    /** Midnight on $day in the project zone, as the UTC instant that is stored. */
    private function dayStart(string $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day, $this->zone())->startOfDay()->utc();
    }

    /** The last instant of $day in the project zone, in UTC. */
    private function dayEnd(string $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day, $this->zone())->endOfDay()->utc();
    }

    private function zone(): string
    {
        return (string) config('mpc.timezone');
    }
}
