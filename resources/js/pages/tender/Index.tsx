import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, ClipboardCheck, FileText, Gavel, Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StatTile } from '@/components/dashboard/StatTile';
import { DataTable } from '@/components/DataTable';
import { Pagination } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { formatDeadline } from '@/lib/datetime';
import { cn } from '@/lib/utils';

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type TenderRow = {
    id: string;
    reference_number: string;
    title_en: string;
    status: string;
    submission_deadline: string | null;
    created_at: string;
    bids_count: number;
    project?: { id: string; name: string; code: string };
    creator?: { id: string; name: string };
};

type Filters = {
    search: string | null;
    status: string | null;
    sort: string;
    direction: string;
};

type Props = {
    tenders: PaginatedData<TenderRow>;
    filters: Filters;
    statusCounts: Record<string, number>;
    summary: {
        total: number;
        open: number;
        closing_this_week: number;
        awaiting_evaluation: number;
    };
};

/** Pipeline order — the sequence is how the work actually moves. */
const STATUSES = [
    'draft',
    'published',
    'submission_closed',
    'under_evaluation',
    'awarded',
    'completed',
    'cancelled',
];

const DAY = 24 * 60 * 60 * 1000;

/**
 * How close a deadline is, for tenders still open.
 *
 * Returned as a token rather than a colour so the caller decides the treatment,
 * and so "urgent" is never conveyed by hue alone.
 */
function urgency(deadline: string | null, status: string): 'past' | 'today' | 'soon' | 'normal' {
    if (!deadline || status !== 'published') {
        return 'normal';
    }

    const remaining = new Date(deadline).getTime() - Date.now();

    if (remaining < 0) {
        return 'past';
    }

    if (remaining < DAY) {
        return 'today';
    }

    if (remaining < 7 * DAY) {
        return 'soon';
    }

    return 'normal';
}

const URGENCY_CLASS: Record<ReturnType<typeof urgency>, string> = {
    past: 'text-[#d03b3b] dark:text-[#e66767] font-medium',
    today: 'text-[#d03b3b] dark:text-[#e66767] font-medium',
    soon: 'text-[#a8792e] dark:text-[#fab219] font-medium',
    normal: 'text-muted-foreground',
};

export default function Index({ tenders, filters, statusCounts, summary }: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    // Every navigation carries the whole filter set. Dropping sort when the
    // search changes — or filters when a column header is clicked — silently
    // threw away what the user had set up.
    const navigate = (next: Partial<Filters>) => {
        router.get(
            '/tenders',
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

    // Debounced search: typing filters the list without a button, and the
    // 350ms wait keeps it to one request per pause rather than one per key.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => {
            navigate({ search: search || undefined });
        }, 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const columns = [
        { key: 'reference_number', label: t('table.reference'), sortable: true },
        {
            key: 'title_en',
            label: t('table.title'),
            sortable: true,
            render: (value: string, row: TenderRow) => (
                <span className="block max-w-[28rem] truncate font-medium">
                    {value}
                    {row.project && (
                        <span className="ms-2 text-xs font-normal text-muted-foreground">
                            {row.project.code}
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
            key: 'submission_deadline',
            label: t('table.deadline'),
            sortable: true,
            render: (value: string | null, row: TenderRow) => (
                <span className={cn('text-sm whitespace-nowrap', URGENCY_CLASS[urgency(value, row.status)])}>
                    {/* Named zone: a deadline a bid can be rejected for missing
                        must not depend on the reader's own clock. */}
                    {value ? formatDeadline(value, locale) : '—'}
                </span>
            ),
        },
        {
            key: 'bids_count',
            label: t('table.bids'),
            sortable: true,
            render: (value: number) => (
                <span className="inline-flex min-w-6 items-center justify-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums">
                    {value}
                </span>
            ),
        },
    ];

    const hasFilters = Boolean(filters.search || filters.status);

    return (
        <>
            <Head title={t('pages.tenders.title')} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.tenders.title')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('tender.index_subtitle')}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/tenders/create">
                            <Plus className="me-2 size-4" />
                            {t('btn.create_tender')}
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('dashboard.tenders')}
                        value={String(summary.total)}
                        hint={t('tender.in_your_projects')}
                        icon={FileText}
                    />
                    <StatTile
                        label={t('dashboard.active_tenders')}
                        value={String(summary.open)}
                        hint={t('dashboard.open_for_bidding')}
                        icon={Gavel}
                    />
                    <StatTile
                        label={t('dashboard.closing_soon')}
                        value={String(summary.closing_this_week)}
                        hint={t('dashboard.next_seven_days')}
                        icon={CalendarClock}
                    />
                    <StatTile
                        label={t('tender.awaiting_evaluation')}
                        value={String(summary.awaiting_evaluation)}
                        hint={t('tender.closed_or_scoring')}
                        icon={ClipboardCheck}
                    />
                </div>

                <div className="space-y-3">
                    <div className="relative max-w-sm">
                        <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder={t('tender.search_placeholder')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="ps-9"
                            aria-label={t('tender.search_placeholder')}
                        />
                    </div>

                    {/* Scrolls rather than wrapping: seven tabs plus counts do
                        not fit a phone, and a wrapped row pushes the table down. */}
                    <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                        <FilterTab
                            label={t('btn.filter_all')}
                            count={summary.total}
                            active={!filters.status}
                            onSelect={() => navigate({ status: undefined })}
                        />
                        {STATUSES.map((status) => (
                            <FilterTab
                                key={status}
                                label={t(`status.${status}`)}
                                count={statusCounts[status] ?? 0}
                                active={filters.status === status}
                                onSelect={() => navigate({ status })}
                            />
                        ))}
                    </div>
                </div>

                {tenders.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <FileText className="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
                        <p className="mt-3 font-medium">
                            {hasFilters ? t('tender.no_matches') : t('tender.none_yet')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {hasFilters ? t('tender.try_clearing') : t('tender.create_first')}
                        </p>
                        {hasFilters && (
                            <Button
                                variant="outline"
                                className="mt-4"
                                onClick={() => {
                                    setSearch('');
                                    navigate({ search: undefined, status: undefined });
                                }}
                            >
                                <X className="me-2 size-4" />
                                {t('tender.clear_filters')}
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        {/* A six-column table does not survive a phone. Below md
                            the same rows render as cards; above it, the table. */}
                        <ul className="space-y-2 md:hidden">
                            {tenders.data.map((tender) => (
                                <li key={tender.id}>
                                    <Link
                                        href={`/tenders/${tender.id}`}
                                        className="block rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">
                                                    {tender.title_en}
                                                </span>
                                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                                    {tender.reference_number}
                                                    {tender.project && ` · ${tender.project.code}`}
                                                </span>
                                            </span>
                                            {/* A two-word status wrapped inside the
                                                badge and threw the card off balance. */}
                                            <span className="shrink-0 whitespace-nowrap">
                                                <StatusBadge status={tender.status} />
                                            </span>
                                        </div>
                                        <div className="mt-3 flex items-center justify-between gap-3 text-xs">
                                            <span
                                                className={cn(
                                                    URGENCY_CLASS[
                                                        urgency(tender.submission_deadline, tender.status)
                                                    ],
                                                )}
                                            >
                                                {tender.submission_deadline
                                                    ? formatDeadline(tender.submission_deadline, locale)
                                                    : '—'}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {t('dashboard.bids_count', { count: tender.bids_count })}
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        {/* DataTable carries its own pager, but it is hidden at this
                            width — without this the card view of a list longer than
                            one page was stranded on the first fifteen rows. */}
                        <Pagination className="md:hidden" links={tenders.links ?? []} />

                        <div className="hidden md:block">
                            <DataTable
                                columns={columns}
                                data={tenders}
                                // Without this the table sorts with an empty
                                // filter set: it wiped the search and status,
                                // and could never toggle to descending.
                                filters={filters}
                                actions={(row: TenderRow) => (
                                    <Button asChild variant="ghost" size="sm">
                                        <Link href={`/tenders/${row.id}`}>{t('btn.view')}</Link>
                                    </Button>
                                )}
                            />
                        </div>
                    </>
                )}
            </div>
        </>
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
