import { Head, Link, router } from '@inertiajs/react';
import {
    AlarmClock,
    AlertTriangle,
    ChevronRight,
    ClipboardCheck,
    Clock,
    Coins,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StatTile } from '@/components/dashboard/StatTile';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { formatDeadline } from '@/lib/datetime';
import { cn } from '@/lib/utils';

type ApprovalRow = {
    id: string;
    level: number;
    required_level: number;
    status: string;
    type: string;
    /** value_threshold — the award value frozen when the chain was raised, USD. */
    value: string | null;
    requested_at: string | null;
    deadline: string | null;
    requested_by: string | null;
    tender: {
        id: string | null;
        reference_number: string | null;
        title_en: string | null;
        title_ar: string | null;
    };
    recommended_vendor: string | null;
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
    status: string;
    sort: string;
    direction: string;
};

type Props = {
    approvals: PaginatedData<ApprovalRow>;
    filters: Filters;
    statusCounts: Record<string, number>;
    summary: {
        pending: number;
        overdue: number;
        due_soon: number;
        value: string;
    };
    /**
     * Served from ApprovalStatus rather than listed here. The page offered no
     * status control at all — the controller hardcoded
     * `where('status', 'pending')`, so an approval that escalated or expired
     * left the only screen that lists them and could not be looked up again.
     */
    statusOptions: Array<{ value: string; labelKey: string }>;
    /** The sentinel the controller reads as "every state". */
    anyStatus: string;
    /**
     * The instant the server took the summary counts at.
     *
     * Rendering reads this rather than the browser clock: it keeps the row
     * colouring identical to the tiles above it, and keeps the component a
     * pure function of its props, which calling Date.now() during render is
     * not.
     */
    now: string;
};

/** Under this many milliseconds left, a deadline is worth shouting about. */
const DUE_SOON_MS = 48 * 60 * 60 * 1000;

export default function Index({
    approvals,
    filters,
    statusCounts,
    summary,
    statusOptions,
    anyStatus,
    now,
}: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    // Every navigation carries the whole filter set — rebuilding the query from
    // the one key that changed drops the sort along with the status.
    const navigate = (next: Partial<Record<keyof Filters, string | undefined>>) => {
        router.get(
            '/approvals',
            {
                search: search || undefined,
                status: filters.status,
                sort: filters.sort,
                direction: filters.direction,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Debounced. The page had no search at all, so a queue of any size could
    // only be read by eye.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => navigate({ search: search || undefined }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const nowMs = new Date(now).getTime();

    const money = (value: string | null) => formatMoney(value, locale);

    const tenderTitle = (row: ApprovalRow) => {
        const title =
            locale === 'ar' ? (row.tender.title_ar ?? row.tender.title_en) : row.tender.title_en;

        // The card hardcoded title_en, so an Arabic reader got English titles
        // in the middle of an otherwise Arabic page — and a literal, untranslated
        // "Untitled Tender" when the join came back empty.
        return title ?? t('approval.untitled_tender');
    };

    const columns = [
        {
            key: 'deadline',
            label: t('approval.deadline'),
            sortable: true,
            render: (value: string | null) => <DeadlineCell deadline={value} nowMs={nowMs} />,
        },
        {
            key: 'tender.reference_number',
            label: t('table.tender'),
            render: (_value: string | null, row: ApprovalRow) => (
                <span className="block max-w-72">
                    <span className="block font-mono text-xs text-muted-foreground">
                        {row.tender.reference_number ?? '—'}
                    </span>
                    <span className="block truncate font-medium">{tenderTitle(row)}</span>
                </span>
            ),
        },
        {
            key: 'approval_level',
            label: t('approval.level'),
            sortable: true,
            render: (_value: number, row: ApprovalRow) => (
                <LevelBadge level={row.level} required={row.required_level} />
            ),
        },
        {
            key: 'recommended_vendor',
            label: t('approval.recommended'),
            render: (value: string | null) => (
                // Never rendered before: index() eager-loaded `report` but not
                // report.recommendedBid.vendor, so this was always undefined
                // and the whole block was skipped.
                <span className="block max-w-48 truncate text-sm">{value ?? '—'}</span>
            ),
        },
        {
            key: 'value_threshold',
            label: t('approval.award_value'),
            sortable: true,
            render: (_value: string, row: ApprovalRow) => (
                <span className="whitespace-nowrap text-sm tabular-nums">{money(row.value)}</span>
            ),
        },
        {
            key: 'status',
            label: t('table.status'),
            sortable: true,
            render: (value: string) => <StatusBadge status={value} />,
        },
        {
            key: 'id',
            label: t('table.actions'),
            render: (_value: string, row: ApprovalRow) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/approvals/${row.id}`}>
                        {t('btn.review')}
                        <ChevronRight className="ms-1 size-4 rtl:rotate-180" />
                    </Link>
                </Button>
            ),
        },
    ];

    const hasFilters = Boolean(filters.search) || filters.status !== 'pending';

    const clearAll = () => {
        setSearch('');
        navigate({ search: undefined, status: 'pending' });
    };

    const totalAcrossStatuses = Object.values(statusCounts).reduce((sum, n) => sum + n, 0);

    return (
        <>
            <Head title={t('pages.approvals.title')} />

            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('pages.approvals.title')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('pages.approvals.description')}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('status.pending')}
                        value={String(summary.pending)}
                        hint={t('approval.awaiting_your_decision')}
                        icon={ClipboardCheck}
                    />
                    {/* The row that actually needs someone: past its deadline
                        and still unsigned. Nothing on the page said so. */}
                    <StatTile
                        label={t('approval.overdue')}
                        value={String(summary.overdue)}
                        hint={t('approval.past_the_deadline')}
                        icon={AlertTriangle}
                    />
                    <StatTile
                        label={t('approval.due_soon')}
                        value={String(summary.due_soon)}
                        hint={t('approval.within_48_hours')}
                        icon={AlarmClock}
                    />
                    <StatTile
                        label={t('approval.award_value')}
                        value={formatMoney(summary.value, locale, true)}
                        hint={t('approval.pending_total')}
                        icon={Coins}
                    />
                </div>

                <div className="space-y-3">
                    <div className="relative max-w-sm">
                        <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder={t('approval.search_placeholder')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="ps-9"
                            aria-label={t('approval.search_placeholder')}
                        />
                    </div>

                    <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                        {statusOptions.map((option) => (
                            <FilterTab
                                key={option.value}
                                label={t(option.labelKey)}
                                count={statusCounts[option.value] ?? 0}
                                active={filters.status === option.value}
                                onSelect={() => navigate({ status: option.value })}
                            />
                        ))}
                        <FilterTab
                            label={t('approval.all_states')}
                            count={totalAcrossStatuses}
                            active={filters.status === anyStatus}
                            onSelect={() => navigate({ status: anyStatus })}
                        />
                    </div>
                </div>

                {approvals.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <ClipboardCheck
                            className="mx-auto size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">
                            {hasFilters ? t('approval.no_matches') : t('empty.no_pending_approvals')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {hasFilters ? t('approval.try_clearing') : t('approval.nothing_waiting')}
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
                            {approvals.data.map((row) => (
                                <li key={row.id}>
                                    <Link
                                        href={`/approvals/${row.id}`}
                                        className="block rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="min-w-0">
                                                <span className="block truncate font-mono text-xs text-muted-foreground">
                                                    {row.tender.reference_number ?? '—'}
                                                </span>
                                                <span className="block truncate font-medium">
                                                    {tenderTitle(row)}
                                                </span>
                                            </span>
                                            <span className="shrink-0 whitespace-nowrap">
                                                <LevelBadge
                                                    level={row.level}
                                                    required={row.required_level}
                                                />
                                            </span>
                                        </div>
                                        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            <DeadlineCell deadline={row.deadline} nowMs={nowMs} compact />
                                            <span className="tabular-nums">{money(row.value)}</span>
                                            {row.recommended_vendor && (
                                                <span className="truncate">
                                                    {row.recommended_vendor}
                                                </span>
                                            )}
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        {/* The card grid had no pager at all, so past the first
                            15 the queue was simply unreachable. */}
                        {approvals.last_page > 1 && (
                            <nav
                                className="flex items-center justify-between gap-3 md:hidden"
                                aria-label={t('approval.pagination')}
                            >
                                <PagerLink
                                    href={approvals.prev_page_url}
                                    label={t('btn.previous')}
                                />
                                {/* bdi: under dir="rtl" the bidi algorithm
                                    reorders the numbers either side of the
                                    slash, so "1 / 7" renders as "7 / 1". */}
                                <span className="text-sm tabular-nums text-muted-foreground">
                                    <bdi>
                                        {approvals.current_page} / {approvals.last_page}
                                    </bdi>
                                </span>
                                <PagerLink href={approvals.next_page_url} label={t('btn.next')} />
                            </nav>
                        )}

                        <div className="hidden md:block">
                            <DataTable
                                columns={columns}
                                data={approvals}
                                // Without this the table sorts with an empty
                                // filter set: it wipes the active search and
                                // status, and descending is unreachable.
                                filters={filters}
                            />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

/**
 * A money figure in the reader's locale.
 *
 * The page formatted with a hardcoded 'en-US', so an Arabic reader got Western
 * digits and grouping in the middle of an Arabic page. Always USD:
 * value_threshold is denominated in ApprovalService::THRESHOLD_CURRENCY, not in
 * the tender's own currency.
 */
function formatMoney(value: string | null, locale: string, compact = false): string {
    const amount = Number(value ?? Number.NaN);

    if (!Number.isFinite(amount)) {
        return '—';
    }

    return new Intl.NumberFormat(locale === 'ar' ? 'ar' : 'en', {
        style: 'currency',
        currency: 'USD',
        notation: compact && Math.abs(amount) >= 100_000 ? 'compact' : 'standard',
        maximumFractionDigits: 0,
    }).format(amount);
}

/**
 * When it is due, and how loudly.
 *
 * The old cell mixed three faults: it rendered the instant with formatDate,
 * which uses the browser's zone rather than the project's, so two people
 * reading the same deadline could see different days; it showed the remaining
 * time only when the item was already urgent, so a deadline four days out said
 * nothing at all; and it concatenated the number straight onto a label reading
 * "Days Left", producing "1Days Left".
 *
 * Time remaining is a duration, so it needs no zone — only the rendered date
 * does, and formatDeadline names the zone it used.
 */
function DeadlineCell({
    deadline,
    nowMs,
    compact = false,
}: {
    deadline: string | null;
    nowMs: number;
    compact?: boolean;
}) {
    const { t, locale } = useTranslation();

    if (!deadline) {
        return <span className="text-sm text-muted-foreground">{t('approval.no_deadline')}</span>;
    }

    const msLeft = new Date(deadline).getTime() - nowMs;
    const overdue = msLeft < 0;
    const dueSoon = !overdue && msLeft < DUE_SOON_MS;

    const hours = Math.max(0, Math.round(Math.abs(msLeft) / 3_600_000));
    // Hours under two days, days beyond — and a bare unit suffix rather than a
    // word, because t() does plain substitution with no plural forms and Arabic
    // has six of them.
    const remaining =
        Math.abs(msLeft) < DUE_SOON_MS
            ? `${hours}${t('approval.hours_short')}`
            : `${Math.round(hours / 24)}${t('approval.days_short')}`;

    return (
        <span className={cn('block', compact ? 'text-xs' : 'text-sm')}>
            <span
                className={cn(
                    'inline-flex items-center gap-1 whitespace-nowrap',
                    overdue && 'font-semibold text-red-600 dark:text-red-400',
                    dueSoon && 'font-medium text-amber-600 dark:text-amber-500',
                    !overdue && !dueSoon && 'text-muted-foreground',
                )}
            >
                {overdue ? (
                    <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
                ) : (
                    <Clock className="size-3.5 shrink-0" aria-hidden="true" />
                )}
                {overdue ? t('approval.overdue') : <bdi>{remaining}</bdi>}
            </span>
            {!compact && (
                <span className="mt-0.5 block whitespace-nowrap text-xs tabular-nums text-muted-foreground">
                    {formatDeadline(deadline, locale)}
                </span>
            )}
        </span>
    );
}

/**
 * Which rung of the chain this is, and how many there are.
 *
 * The old badge coloured level 3 "destructive", which reads as something being
 * wrong rather than as the request needing a more senior signature.
 */
function LevelBadge({ level, required }: { level: number; required: number }) {
    const { t } = useTranslation();

    return (
        <span
            className="inline-flex items-center whitespace-nowrap rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium"
            title={t('approval.level')}
        >
            {t('approval.level_short')}
            <span className="ms-1 tabular-nums">
                <bdi>
                    {level}
                    {required > 0 ? ` / ${required}` : ''}
                </bdi>
            </span>
        </span>
    );
}

function PagerLink({ href, label }: { href: string | null; label: string }) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                {label}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveState preserveScroll>
                {label}
            </Link>
        </Button>
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
