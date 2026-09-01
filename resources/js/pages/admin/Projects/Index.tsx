import { Head, Link, router } from '@inertiajs/react';
import { Briefcase, CalendarRange, FileText, Play, Plus, Search, UserX, Users, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StatTile } from '@/components/dashboard/StatTile';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { CreateProjectDialog } from './CreateDialog';

type ProjectRow = {
    id: string;
    name: string;
    name_ar: string | null;
    code: string;
    location: string | null;
    client_name: string | null;
    status: string;
    start_date: string | null;
    end_date: string | null;
    created_at: string;
    tenders_count: number;
    users_count: number;
};

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Filters = {
    search: string | null;
    status: string | null;
    sort: string;
    direction: string;
};

type Props = {
    projects: PaginatedData<ProjectRow>;
    filters: Filters;
    statusCounts: Record<string, number>;
    summary: {
        total: number;
        active: number;
        tenders: number;
        unstaffed: number;
    };
    /**
     * Sent by the server from the ProjectStatus enum rather than listed here.
     * The hardcoded list this replaces had drifted: it offered a "draft" option
     * that validation has never accepted, so choosing it always came back empty.
     */
    statusOptions: Array<{ value: string; labelKey: string }>;
};

export default function Index({ projects, filters, statusCounts, summary, statusOptions }: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const firstRender = useRef(true);

    // Every navigation carries the whole filter set — rebuilding the query from
    // the one key that changed drops the sort and direction.
    const navigate = (next: Partial<Filters>) => {
        router.get(
            '/admin/projects',
            {
                search: search || undefined,
                status: filters.status || undefined,
                sort: filters.sort,
                direction: filters.direction,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Debounced: the old page searched only on Enter, with nothing on screen to
    // say that typing alone did nothing.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => navigate({ search: search || undefined }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const timeline = (row: ProjectRow) => {
        if (!row.start_date && !row.end_date) {
            return null;
        }

        const from = row.start_date ? formatDate(row.start_date, locale) : '—';
        const to = row.end_date ? formatDate(row.end_date, locale) : '—';

        return `${from} – ${to}`;
    };

    const columns = [
        {
            key: 'code',
            label: t('table.code'),
            sortable: true,
            render: (value: string) => <span className="font-mono text-sm">{value}</span>,
        },
        {
            key: 'name',
            label: t('table.name'),
            sortable: true,
            render: (value: string, row: ProjectRow) => (
                <span className="block">
                    <span className="block font-medium">{value}</span>
                    {/* Stacked rather than inline: ms-2 resolves against the
                        span's own dir, so inside dir="rtl" the gap landed on the
                        far side and the two names ran together. */}
                    {row.name_ar && (
                        <span className="block text-xs text-muted-foreground" dir="rtl">
                            {row.name_ar}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'location',
            label: t('table.location'),
            sortable: true,
            render: (value: string | null, row: ProjectRow) => (
                <span className="block">
                    <span className="block">{value ?? '—'}</span>
                    {row.client_name && (
                        <span className="block text-xs text-muted-foreground">
                            {row.client_name}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'status',
            label: t('table.status'),
            sortable: true,
            render: (value: string) => <StatusBadge status={value} />,
        },
        {
            key: 'start_date',
            label: t('project.timeline'),
            sortable: true,
            render: (_value: string | null, row: ProjectRow) => {
                const range = timeline(row);

                return range ? (
                    <span className="whitespace-nowrap text-sm text-muted-foreground">{range}</span>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        {t('project.not_scheduled')}
                    </span>
                );
            },
        },
        {
            // These two columns rendered blank in production: the query called
            // select() after withCount(), which drops the count subqueries.
            key: 'tenders_count',
            label: t('table.tenders'),
            render: (value: number) => <Count value={value} />,
        },
        {
            key: 'users_count',
            label: t('table.team_size'),
            render: (value: number) => <Count value={value} warn={value === 0} />,
        },
    ];

    const hasFilters = Boolean(filters.search || filters.status);

    const clearAll = () => {
        setSearch('');
        navigate({ search: undefined, status: undefined });
    };

    return (
        <>
            <Head title={t('pages.admin.projects')} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.admin.projects')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('pages.admin.projects_description')}
                        </p>
                    </div>
                    <Button onClick={() => setShowCreateDialog(true)}>
                        <Plus className="me-2 size-4" />
                        {t('btn.new_project')}
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('pages.admin.projects')}
                        value={String(summary.total)}
                        hint={t('project.on_the_books')}
                        icon={Briefcase}
                    />
                    <StatTile
                        label={t('status.active')}
                        value={String(summary.active)}
                        hint={t('project.currently_running')}
                        icon={Play}
                    />
                    <StatTile
                        label={t('table.tenders')}
                        value={String(summary.tenders)}
                        hint={t('project.across_these_projects')}
                        icon={FileText}
                    />
                    {/* Worth its own tile: a project with nobody assigned hides
                        every tender under it, including from a super admin. */}
                    <StatTile
                        label={t('project.unstaffed')}
                        value={String(summary.unstaffed)}
                        hint={t('project.nobody_assigned')}
                        icon={UserX}
                    />
                </div>

                <div className="space-y-3">
                    <div className="relative max-w-sm">
                        <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder={t('form.search_projects')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="ps-9"
                            aria-label={t('form.search_projects')}
                        />
                    </div>

                    <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                        <FilterTab
                            label={t('form.all_statuses')}
                            count={summary.total}
                            active={!filters.status}
                            onSelect={() => navigate({ status: undefined })}
                        />
                        {statusOptions.map((option) => (
                            <FilterTab
                                key={option.value}
                                label={t(option.labelKey)}
                                count={statusCounts[option.value] ?? 0}
                                active={filters.status === option.value}
                                onSelect={() => navigate({ status: option.value })}
                            />
                        ))}
                    </div>
                </div>

                {projects.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <Briefcase
                            className="mx-auto size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">
                            {hasFilters ? t('project.no_matches') : t('project.none_yet')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {hasFilters ? t('project.try_clearing') : t('project.add_first')}
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
                            {projects.data.map((project) => (
                                <li key={project.id}>
                                    <Link
                                        href={`/admin/projects/${project.id}/edit`}
                                        className="block rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">
                                                    {project.name}
                                                </span>
                                                <span className="mt-0.5 block truncate font-mono text-xs text-muted-foreground">
                                                    {project.code}
                                                </span>
                                            </span>
                                            <span className="shrink-0 whitespace-nowrap">
                                                <StatusBadge status={project.status} />
                                            </span>
                                        </div>
                                        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            {project.location && <span>{project.location}</span>}
                                            {timeline(project) && (
                                                <span className="inline-flex items-center gap-1">
                                                    <CalendarRange
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                    {timeline(project)}
                                                </span>
                                            )}
                                            {/* Icon plus number rather than
                                                ":count tenders": t() does plain
                                                substitution with no plural forms,
                                                so that read "1 tenders" — and
                                                Arabic has six forms a two-key
                                                English workaround would not cover. */}
                                            <span
                                                className="inline-flex items-center gap-1"
                                                title={t('table.tenders')}
                                            >
                                                <FileText className="size-3" aria-hidden="true" />
                                                <span className="tabular-nums">
                                                    {project.tenders_count}
                                                </span>
                                                <span className="sr-only">{t('table.tenders')}</span>
                                            </span>
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1',
                                                    project.users_count === 0 &&
                                                        'text-amber-600 dark:text-amber-500',
                                                )}
                                                title={
                                                    project.users_count === 0
                                                        ? t('project.no_team')
                                                        : t('table.team_size')
                                                }
                                            >
                                                <Users className="size-3" aria-hidden="true" />
                                                <span className="tabular-nums">
                                                    {project.users_count}
                                                </span>
                                                <span className="sr-only">
                                                    {t('table.team_size')}
                                                </span>
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden md:block">
                            <DataTable
                                columns={columns}
                                data={projects}
                                // Without this the table sorts with an empty
                                // filter set: it wiped the search and status,
                                // and could never toggle to descending.
                                filters={filters}
                                actions={(row: ProjectRow) => (
                                    <div className="flex items-center justify-end gap-3 whitespace-nowrap">
                                        <Link
                                            href={`/admin/projects/${row.id}/edit`}
                                            className="text-sm font-medium text-primary hover:underline"
                                        >
                                            {t('btn.edit')}
                                        </Link>
                                    </div>
                                )}
                            />
                        </div>
                    </>
                )}
            </div>

            <CreateProjectDialog
                open={showCreateDialog}
                onClose={() => setShowCreateDialog(false)}
                statusOptions={statusOptions}
            />
        </>
    );
}

/** A count as a pill, so a column of zeroes does not read as empty cells. */
function Count({ value, warn = false }: { value: number; warn?: boolean }) {
    return (
        <span
            className={cn(
                'inline-flex min-w-6 items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium tabular-nums',
                warn
                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                    : 'bg-muted',
            )}
        >
            {value}
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
