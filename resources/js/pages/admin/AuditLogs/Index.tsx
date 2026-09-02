import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    Building2,
    ChevronLeft,
    ChevronRight,
    Clock,
    FileDiff,
    ScrollText,
    Search,
    Server,
    Users,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { StatTile } from '@/components/dashboard/StatTile';
import { DataTable } from '@/components/DataTable';
import { Pagination } from '@/components/Pagination';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { actionLabel, entityLabel, REDACTED, shortEntityId } from '@/lib/audit';
import { formatTimestamp, projectTimeZone, projectZoneToday } from '@/lib/datetime';
import { cn } from '@/lib/utils';

type Actor = {
    type: 'user' | 'vendor' | 'system';
    name: string | null;
};

type AuditRow = {
    id: string;
    created_at: string | null;
    action: string;
    entity_type: string;
    entity_id: string;
    ip_address: string | null;
    actor: Actor;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
};

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = {
    search: string | null;
    user_id: string | null;
    action: string | null;
    entity_type: string | null;
    from: string | null;
    to: string | null;
    sort: string;
    direction: string;
};

type Option = { value: string; count: number };
type LabelledOption = Option & { label: string };

type Props = {
    logs: PaginatedData<AuditRow>;
    filters: Filters;
    summary: {
        total: number;
        actors: number;
        changes: number;
        today: number;
    };
    /**
     * All three option lists are drawn from the rows that exist rather than
     * from a literal in this file. The hardcoded action list offered "exported"
     * and "imported" — which nothing in the app writes — while omitting the
     * HTTP methods the request middleware records, every
     * vendor_category_request_* event and every password-reset event.
     */
    entityOptions: Option[];
    actionOptions: Option[];
    actorOptions: LabelledOption[];
};

/** Radix rejects an empty string as an item value, so "no filter" needs one. */
const ANY = 'all';

type Query = Partial<Record<keyof Filters, string | undefined>>;

export default function Index({
    logs,
    filters,
    summary,
    entityOptions,
    actionOptions,
    actorOptions,
}: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    // The two date controls hold their own value for the same reason the search
    // box does: they are controlled, so rendering them straight from `filters`
    // snaps the picker back to the old day until the response lands.
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [detail, setDetail] = useState<AuditRow | null>(null);
    const firstRender = useRef(true);

    const zone = projectTimeZone();
    const today = projectZoneToday();

    // Every navigation carries the whole filter set — rebuilding the query from
    // the one key that changed drops the date window along with the sort.
    const navigate = (next: Query) => {
        router.get(
            '/admin/audit-logs',
            {
                search: search || undefined,
                user_id: filters.user_id || undefined,
                action: filters.action || undefined,
                entity_type: filters.entity_type || undefined,
                from: from || undefined,
                to: to || undefined,
                sort: filters.sort,
                direction: filters.direction,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Debounced: the old page had an Apply button and no search at all, so
    // finding one IP address meant paging through the whole trail by hand.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => navigate({ search: search || undefined }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const columns = [
        {
            key: 'created_at',
            label: t('table.timestamp'),
            sortable: true,
            render: (value: string | null) => (
                <span className="whitespace-nowrap text-sm tabular-nums text-muted-foreground">
                    {formatTimestamp(value, locale)}
                </span>
            ),
        },
        {
            key: 'actor.name',
            label: t('audit.actor'),
            render: (_value: string | null, row: AuditRow) => <ActorCell actor={row.actor} />,
        },
        {
            key: 'action',
            label: t('table.action'),
            sortable: true,
            render: (value: string) => <ActionBadge action={value} />,
        },
        {
            key: 'entity_type',
            label: t('table.entity_type'),
            sortable: true,
            render: (value: string) => <span className="text-sm">{entityLabel(t, value)}</span>,
        },
        {
            key: 'entity_id',
            label: t('table.entity_id'),
            render: (value: string) => (
                // Only UUIDs are shortened. The request middleware stores the
                // route name here, and clipping everything to eight characters
                // turned `admin.projects.index` into `admin.pr…`.
                <span className="block max-w-40 truncate font-mono text-xs" title={value}>
                    {shortEntityId(value)}
                </span>
            ),
        },
        {
            key: 'ip_address',
            label: t('table.ip_address'),
            render: (value: string | null) => (
                <span className="font-mono text-xs text-muted-foreground">{value ?? '—'}</span>
            ),
        },
        {
            key: 'id',
            label: t('table.actions'),
            render: (_value: string, row: AuditRow) => (
                <Button variant="ghost" size="sm" onClick={() => setDetail(row)}>
                    {t('audit.details')}
                </Button>
            ),
        },
    ];

    const hasFilters = Boolean(
        filters.search ||
            filters.user_id ||
            filters.action ||
            filters.entity_type ||
            filters.from ||
            filters.to,
    );

    const clearAll = () => {
        setSearch('');
        setFrom('');
        setTo('');
        navigate({
            search: undefined,
            user_id: undefined,
            action: undefined,
            entity_type: undefined,
            from: undefined,
            to: undefined,
        });
    };

    // The sum of the pills beside it rather than summary.total: the tiles
    // describe the window, but the pills are counted with the action and user
    // filters still applied, so the two do not agree once either is set.
    const entityTotal = entityOptions.reduce((sum, option) => sum + option.count, 0);

    return (
        <>
            <Head title={t('pages.admin.audit_logs')} />

            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('pages.admin.audit_logs')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('pages.admin.audit_logs_description')}
                    </p>
                    {/* No create, edit or delete control anywhere on this page,
                        and the wording says why rather than leaving it looking
                        like an oversight. */}
                    <p className="mt-1 text-xs text-muted-foreground">{t('audit.append_only')}</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('audit.events')}
                        value={String(summary.total)}
                        hint={t('audit.in_this_window')}
                        icon={Activity}
                    />
                    <StatTile
                        label={t('audit.people')}
                        value={String(summary.actors)}
                        hint={t('audit.distinct_users')}
                        icon={Users}
                    />
                    <StatTile
                        label={t('audit.changes_recorded')}
                        value={String(summary.changes)}
                        hint={t('audit.rows_with_before_after')}
                        icon={FileDiff}
                    />
                    <StatTile
                        label={t('audit.today')}
                        value={String(summary.today)}
                        hint={t('audit.since_midnight', { zone })}
                        icon={Clock}
                    />
                </div>

                <div className="space-y-3">
                    <div className="flex flex-col gap-2 lg:flex-row">
                        <div className="relative max-w-sm flex-1">
                            <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                            <Input
                                type="search"
                                placeholder={t('audit.search_placeholder')}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="ps-9"
                                aria-label={t('audit.search_placeholder')}
                            />
                        </div>

                        {/* The controller has filtered on user_id all along;
                            the page never offered a control for it. */}
                        <Select
                            value={filters.user_id ?? ANY}
                            onValueChange={(value) =>
                                navigate({ user_id: value === ANY ? undefined : value })
                            }
                        >
                            <SelectTrigger className="w-full lg:w-56">
                                <SelectValue placeholder={t('audit.all_actors')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>{t('audit.all_actors')}</SelectItem>
                                {actorOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.action ?? ANY}
                            onValueChange={(value) =>
                                navigate({ action: value === ANY ? undefined : value })
                            }
                        >
                            <SelectTrigger className="w-full lg:w-56">
                                <SelectValue placeholder={t('form.all_actions')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>{t('form.all_actions')}</SelectItem>
                                {actionOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {actionLabel(t, option.value)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex flex-wrap items-end gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="audit-from">{t('form.from')}</Label>
                            <Input
                                id="audit-from"
                                type="date"
                                value={from}
                                // Today on the project's clock, not the UTC
                                // one, or today is unpickable until 03:00.
                                max={to || today}
                                onChange={(e) => {
                                    setFrom(e.target.value);
                                    navigate({ from: e.target.value || undefined });
                                }}
                                className="w-44"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="audit-to">{t('form.to')}</Label>
                            <Input
                                id="audit-to"
                                type="date"
                                value={to}
                                min={from || undefined}
                                max={today}
                                onChange={(e) => {
                                    setTo(e.target.value);
                                    navigate({ to: e.target.value || undefined });
                                }}
                                className="w-44"
                            />
                        </div>
                        {/* Said out loud because it is not free: the server
                            reads these two days on the project's clock, and the
                            timestamps beside them are rendered on it too. */}
                        <p className="pb-2 text-xs text-muted-foreground">
                            {t('audit.dates_in_zone', { zone })}
                        </p>
                    </div>

                    <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                        <FilterTab
                            label={t('audit.all_entities')}
                            count={entityTotal}
                            active={!filters.entity_type}
                            onSelect={() => navigate({ entity_type: undefined })}
                        />
                        {entityOptions.map((option) => (
                            <FilterTab
                                key={option.value}
                                label={entityLabel(t, option.value)}
                                count={option.count}
                                active={filters.entity_type === option.value}
                                onSelect={() => navigate({ entity_type: option.value })}
                            />
                        ))}
                    </div>
                </div>

                {logs.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <ScrollText
                            className="mx-auto size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">
                            {hasFilters ? t('audit.no_matches') : t('audit.none_yet')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {hasFilters ? t('audit.try_clearing') : t('audit.nothing_recorded')}
                        </p>
                        {hasFilters && (
                            <Button variant="outline" className="mt-4" onClick={clearAll}>
                                <X className="me-2 size-4" />
                                {t('tender.clear_filters')}
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        {/* Seven columns do not survive a phone; the same rows
                            render as cards below md. */}
                        <ul className="space-y-2 md:hidden">
                            {logs.data.map((log) => (
                                <li key={log.id}>
                                    <button
                                        type="button"
                                        onClick={() => setDetail(log)}
                                        className="block w-full rounded-xl border bg-card p-4 text-start transition-colors hover:bg-accent"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">
                                                    <ActorName actor={log.actor} />
                                                </span>
                                                <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                                                    {entityLabel(t, log.entity_type)} ·{' '}
                                                    <span className="font-mono">
                                                        {shortEntityId(log.entity_id)}
                                                    </span>
                                                </span>
                                            </span>
                                            <span className="shrink-0 whitespace-nowrap">
                                                <ActionBadge action={log.action} />
                                            </span>
                                        </div>
                                        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs tabular-nums text-muted-foreground">
                                            <span>{formatTimestamp(log.created_at, locale)}</span>
                                            {log.ip_address && (
                                                <span className="font-mono">{log.ip_address}</span>
                                            )}
                                        </div>
                                    </button>
                                </li>
                            ))}
                        </ul>

                        {/* The card list had no pager at all: below md the
                            table — and with it every page control — is hidden,
                            so a phone could only ever see the newest 25 rows. */}
                        {logs.last_page > 1 && (
                            <nav
                                className="flex items-center justify-between gap-3 md:hidden"
                                aria-label={t('audit.pagination')}
                            >
                                <PagerLink
                                    href={logs.prev_page_url}
                                    label={t('btn.previous')}
                                    icon={<ChevronLeft className="size-4 rtl:rotate-180" />}
                                />
                                {/* bdi: under dir="rtl" the bidi algorithm
                                    reorders the numbers either side of the
                                    slash, so "1 / 7" renders as "7 / 1". */}
                                <span className="text-sm tabular-nums text-muted-foreground">
                                    <bdi>
                                        {logs.current_page} / {logs.last_page}
                                    </bdi>
                                </span>
                                <PagerLink
                                    href={logs.next_page_url}
                                    label={t('btn.next')}
                                    icon={<ChevronRight className="size-4 rtl:rotate-180" />}
                                />
                            </nav>
                        )}

                        {/* DataTable carries its own pager, but it is hidden at this
                            width — without this the card view of a list longer than
                            one page was stranded on the first fifteen rows. */}
                        <Pagination className="md:hidden" links={logs.links ?? []} />

                        <div className="hidden md:block">
                            <DataTable
                                columns={columns}
                                data={logs}
                                // Already present here, unlike six of the ten
                                // list pages — but it was fed $request->only(),
                                // which carries no sort or direction, so the
                                // headers below could never reach descending.
                                filters={filters}
                            />
                        </div>
                    </>
                )}
            </div>

            <DetailDialog log={detail} onClose={() => setDetail(null)} />
        </>
    );
}

function PagerLink({
    href,
    label,
    icon,
}: {
    href: string | null;
    label: string;
    icon: ReactNode;
}) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                {icon}
                <span className="ms-1">{label}</span>
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveState preserveScroll>
                {icon}
                <span className="ms-1">{label}</span>
            </Link>
        </Button>
    );
}

/** The actor's name, or what stands in for one. */
function ActorName({ actor }: { actor: Actor }) {
    const { t } = useTranslation();

    return <>{actor.name ?? t('table.system')}</>;
}

/**
 * Who acted.
 *
 * The page read user_id alone, so every vendor-initiated event — a vendor
 * resetting their own password, submitting a category request, browsing a
 * tender — was attributed to "System".
 */
function ActorCell({ actor }: { actor: Actor }) {
    const { t } = useTranslation();

    const Icon = actor.type === 'vendor' ? Building2 : actor.type === 'system' ? Server : Users;

    return (
        <span className="flex items-center gap-2">
            <Icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
            <span className="min-w-0">
                <span className="block truncate text-sm">
                    <ActorName actor={actor} />
                </span>
                {actor.type === 'vendor' && (
                    <span className="block text-xs text-muted-foreground">
                        {t('audit.actor_vendor')}
                    </span>
                )}
            </span>
        </span>
    );
}

/**
 * Tones by what the event did, not by an exact match on a closed list — the
 * action vocabulary grows every time a service logs something new.
 */
function actionTone(action: string): string {
    if (/(deleted|rejected|withdrawn|cancelled|^delete$)/.test(action)) {
        return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300';
    }

    if (/(created|approved|published|opened|submitted|^post$)/.test(action)) {
        return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300';
    }

    if (/(updated|changed|extends|started|sealed|^put$|^patch$)/.test(action)) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
    }

    if (/(login|logout|password|reset)/.test(action)) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300';
    }

    return 'bg-muted text-muted-foreground';
}

function ActionBadge({ action }: { action: string }) {
    const { t } = useTranslation();

    return (
        <span
            className={cn(
                'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium',
                actionTone(action),
            )}
        >
            {actionLabel(t, action)}
        </span>
    );
}

function FilterTab({
    label,
    count,
    active,
    onSelect,
}: {
    label: string;
    count: number;
    active: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={active}
            className={cn(
                'flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors',
                active
                    ? 'border-transparent bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted',
            )}
        >
            {label}
            <span
                className={cn(
                    'rounded-full px-1.5 text-xs tabular-nums',
                    active ? 'bg-primary-foreground/20' : 'bg-muted',
                )}
            >
                {count}
            </span>
        </button>
    );
}

/**
 * The full record for one event.
 *
 * A dialog rather than an inline expander: the old chevron rendered its
 * before/after grid inside the table's last cell, so the payload was squeezed
 * into a column's width, and on a phone there was no expander at all.
 */
function DetailDialog({ log, onClose }: { log: AuditRow | null; onClose: () => void }) {
    const { t, locale } = useTranslation();

    if (!log) {
        return null;
    }

    const hasPayload = Boolean(log.old_values || log.new_values);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="text-start">{t('audit.event_details')}</DialogTitle>
                    <DialogDescription className="text-start tabular-nums">
                        {formatTimestamp(log.created_at, locale)}
                    </DialogDescription>
                </DialogHeader>

                <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Field label={t('table.action')}>
                        <ActionBadge action={log.action} />
                    </Field>
                    <Field label={t('audit.actor')}>
                        <ActorName actor={log.actor} />
                    </Field>
                    <Field label={t('table.entity_type')}>{entityLabel(t, log.entity_type)}</Field>
                    <Field label={t('table.ip_address')}>
                        <span className="font-mono text-xs">{log.ip_address ?? '—'}</span>
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label={t('table.entity_id')}>
                            {/* In full, and breakable: this is the value
                                someone copies to go and look at the record. */}
                            <span className="break-all font-mono text-xs">{log.entity_id}</span>
                        </Field>
                    </div>
                </dl>

                {hasPayload ? (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <ValuePanel label={t('audit.before')} values={log.old_values} tone="old" />
                        <ValuePanel label={t('audit.after')} values={log.new_values} tone="new" />
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">{t('audit.no_payload')}</p>
                )}
            </DialogContent>
        </Dialog>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 text-sm">{children}</dd>
        </div>
    );
}

function ValuePanel({
    label,
    values,
    tone,
}: {
    label: string;
    values: Record<string, unknown> | null;
    tone: 'old' | 'new';
}) {
    const { t } = useTranslation();

    const entries = values ? Object.entries(values) : [];

    return (
        <div className="space-y-1">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <div
                className={cn(
                    'rounded-md border p-3 text-sm',
                    tone === 'old'
                        ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200'
                        : 'border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950/50 dark:text-green-200',
                )}
            >
                {entries.length === 0 ? (
                    <span className="text-xs opacity-70">—</span>
                ) : (
                    <dl className="space-y-1">
                        {entries.map(([key, value]) => (
                            <div key={key} className="flex flex-wrap gap-x-2">
                                <dt className="font-mono text-xs font-medium">{key}</dt>
                                <dd className="min-w-0 break-all font-mono text-xs">
                                    {renderValue(t, value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
            </div>
        </div>
    );
}

/**
 * One recorded value.
 *
 * The controller replaces anything under a sensitive key with a sentinel
 * rather than sending it, at any depth — hence the replacer on the nested
 * case as well as the check on the flat one.
 */
function renderValue(t: (key: string) => string, value: unknown): ReactNode {
    if (value === REDACTED) {
        return <span className="italic opacity-70">{t('audit.redacted')}</span>;
    }

    if (value === null || value === undefined) {
        return <span className="opacity-70">—</span>;
    }

    if (typeof value === 'string') {
        return value;
    }

    return JSON.stringify(value, (_key, nested) =>
        nested === REDACTED ? t('audit.redacted') : nested,
    );
}
