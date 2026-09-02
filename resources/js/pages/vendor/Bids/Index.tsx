import { Head, Link } from '@inertiajs/react';
import { CalendarClock, FileSignature, Gavel, PenLine, Search, ShieldCheck, X } from 'lucide-react';
import { StatTile } from '@/components/dashboard/StatTile';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedFilters } from '@/hooks/use-debounced-filters';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate, formatDeadline } from '@/lib/datetime';
import { DEADLINE_TONE_CLASS, deadlineStatus } from '@/lib/deadline';
import { localized } from '@/lib/locales';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type BidRow = {
    id: string;
    bid_reference: string;
    status: string;
    submitted_at: string | null;
    total_amount: string | null;
    currency: string | null;
    tender: {
        id: string;
        title_en: string;
        title_ar: string | null;
        reference_number: string;
        submission_deadline: string | null;
        currency: string | null;
    } | null;
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
    bids: PaginatedData<BidRow>;
    filters: Filters;
    statusCounts: Record<string, number>;
    summary: {
        total: number;
        drafts: number;
        submitted: number;
        awaiting_deadline: number;
    };
    /**
     * Sent by the server from the BidStatus enum rather than listed here.
     * The page previously compared bid.status against string literals in three
     * places, with nothing keeping them in step with the enum's cases.
     */
    statusOptions: Array<{ value: string; labelKey: string }>;
};

/**
 * A vendor's own bids: every draft they have started and every bid they have
 * sealed and sent.
 */
export default function Index({ bids, filters, statusCounts, summary, statusOptions }: Props) {
    const { t, locale } = useTranslation();
    // One shared hook rather than a copy per page: every navigation has to
    // carry the whole filter set, and typing has to search on its own after
    // a pause. Three pages were getting both right independently.
    const { search, setSearch, navigate } = useDebouncedFilters('/vendor/bids', filters);

    const tenderTitle = (row: BidRow) =>
        row.tender ? localized(locale, row.tender.title_en, row.tender.title_ar) : '—';

    // The bid's own currency, falling back to the tender's. A draft is stamped
    // with the tender currency at creation, so the two agree in practice; the
    // fallback covers a row written before that was the case.
    const rowCurrency = (row: BidRow) => row.currency ?? row.tender?.currency ?? null;

    const columns = [
        {
            key: 'bid_reference',
            label: t('table.bid_reference'),
            sortable: true,
            render: (value: string) => <span className="font-mono text-sm">{value}</span>,
        },
        {
            key: 'tender',
            label: t('table.tender'),
            render: (_value: unknown, row: BidRow) =>
                row.tender ? (
                    <Link
                        href={`/vendor/tenders/${row.tender.id}`}
                        className="block hover:underline"
                    >
                        <span className="block font-medium">{tenderTitle(row)}</span>
                        <span className="mt-0.5 block font-mono text-xs text-muted-foreground">
                            {row.tender.reference_number}
                        </span>
                    </Link>
                ) : (
                    <span className="text-muted-foreground">&mdash;</span>
                ),
        },
        {
            key: 'status',
            label: t('table.status'),
            sortable: true,
            render: (value: string) => <StatusBadge status={value} />,
        },
        {
            key: 'total_amount',
            label: t('table.total_amount'),
            sortable: true,
            render: (value: string | null, row: BidRow) =>
                value ? (
                    <span className="font-medium tabular-nums">
                        {formatMoney(value, rowCurrency(row), locale)}
                    </span>
                ) : (
                    // A draft has no total until it is sealed; saying so beats
                    // an em dash that reads like missing data.
                    <span className="text-sm text-muted-foreground">{t('bid.not_priced_yet')}</span>
                ),
        },
        {
            key: 'submitted_at',
            label: t('table.submitted'),
            sortable: true,
            render: (value: string | null) =>
                value ? (
                    <span className="whitespace-nowrap text-sm">{formatDate(value, locale)}</span>
                ) : (
                    <span className="text-sm text-muted-foreground">{t('status.not_submitted')}</span>
                ),
        },
        {
            key: 'deadline',
            label: t('table.deadline'),
            render: (_value: unknown, row: BidRow) => <DeadlineCell deadline={row.tender?.submission_deadline} />,
        },
    ];

    const hasFilters = Boolean(filters.search || filters.status);

    const clearAll = () => {
        setSearch('');
        navigate({ search: undefined, status: undefined });
    };

    return (
        <>
            <Head title={t('pages.vendor.my_bids')} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.vendor.my_bids')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('pages.vendor.my_bids_description')}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/vendor/tenders">
                            <Search className="me-2 size-4" />
                            {t('btn.browse_tenders')}
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('pages.vendor.my_bids')}
                        value={String(summary.total)}
                        hint={t('bid.on_the_books')}
                        icon={Gavel}
                    />
                    <StatTile
                        label={t('status.draft')}
                        value={String(summary.drafts)}
                        hint={t('bid.still_a_draft')}
                        icon={PenLine}
                    />
                    <StatTile
                        label={t('status.submitted')}
                        value={String(summary.submitted)}
                        hint={t('bid.sealed_and_in')}
                        icon={ShieldCheck}
                    />
                    {/* Worth its own tile: a draft on a tender still open is the
                        only thing on this page the vendor can lose by not
                        acting on it. */}
                    <StatTile
                        label={t('bid.awaiting_deadline')}
                        value={String(summary.awaiting_deadline)}
                        hint={t('bid.drafts_still_open')}
                        icon={CalendarClock}
                    />
                </div>

                <div className="space-y-3">
                    <div className="relative max-w-sm">
                        <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder={t('form.search_bids')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="ps-9"
                            aria-label={t('form.search_bids')}
                        />
                    </div>

                    <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                        <FilterTab
                            label={t('form.all_statuses')}
                            count={summary.total}
                            active={!filters.status}
                            onSelect={() => navigate({ status: undefined })}
                        />
                        {statusOptions
                            // Eight cases with six of them at zero is a wall of
                            // noise; a status the vendor has never reached is
                            // not a filter they need.
                            .filter((option) => (statusCounts[option.value] ?? 0) > 0)
                            .map((option) => (
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

                {bids.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <Gavel className="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
                        <p className="mt-3 font-medium">
                            {hasFilters ? t('bid.no_matches') : t('bid.none_yet')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {hasFilters ? t('bid.try_clearing') : t('bid.browse_to_start')}
                        </p>
                        {hasFilters ? (
                            <Button variant="outline" className="mt-4" onClick={clearAll}>
                                <X className="me-2 size-4" />
                                {t('tender.clear_filters')}
                            </Button>
                        ) : (
                            <Button asChild className="mt-4">
                                <Link href="/vendor/tenders">{t('btn.browse_tenders')}</Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        {/* Six columns do not survive a phone; the same rows
                            render as cards below md. */}
                        <ul className="space-y-2 md:hidden">
                            {bids.data.map((bid) => (
                                <li key={bid.id}>
                                    <Link
                                        href={`/vendor/bids/${bid.id}`}
                                        className="block rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">
                                                    {tenderTitle(bid)}
                                                </span>
                                                <span className="mt-0.5 block truncate font-mono text-xs text-muted-foreground">
                                                    {bid.bid_reference}
                                                </span>
                                            </span>
                                            <span className="shrink-0 whitespace-nowrap">
                                                <StatusBadge status={bid.status} />
                                            </span>
                                        </div>
                                        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            <span className="inline-flex items-center gap-1">
                                                <FileSignature className="size-3" aria-hidden="true" />
                                                <span className="tabular-nums">
                                                    {bid.total_amount
                                                        ? formatMoney(bid.total_amount, rowCurrency(bid), locale)
                                                        : t('bid.not_priced_yet')}
                                                </span>
                                            </span>
                                            <DeadlineCell deadline={bid.tender?.submission_deadline} compact />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden md:block">
                            <DataTable
                                columns={columns}
                                data={bids}
                                // Without this the table sorts with an empty
                                // filter set: it wiped the search and status,
                                // and could never toggle to descending.
                                filters={filters}
                                actions={(row: BidRow) => (
                                    <div className="flex items-center justify-end gap-3 whitespace-nowrap">
                                        <Link
                                            href={`/vendor/bids/${row.id}`}
                                            className="text-sm font-medium text-primary hover:underline"
                                        >
                                            {t('btn.view')}
                                        </Link>
                                    </div>
                                )}
                            />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

/**
 * The tender's deadline and how far off it is.
 *
 * `formatDeadline` rather than `formatDate`: this is the moment a bid can be
 * rejected for missing, so it names the zone it is on.
 */
function DeadlineCell({ deadline, compact = false }: { deadline?: string | null; compact?: boolean }) {
    const { t, locale } = useTranslation();
    const status = deadlineStatus(deadline);

    if (!deadline || !status) {
        return <span className="text-sm text-muted-foreground">&mdash;</span>;
    }

    const label = t(status.labelKey, { count: status.days });

    if (compact) {
        return (
            <span className={cn('inline-flex items-center gap-1', DEADLINE_TONE_CLASS[status.tone])}>
                <CalendarClock className="size-3" aria-hidden="true" />
                {label}
            </span>
        );
    }

    return (
        <span className="block whitespace-nowrap">
            <span className="block text-sm">{formatDeadline(deadline, locale)}</span>
            <span className={cn('mt-0.5 block text-xs', DEADLINE_TONE_CLASS[status.tone])}>{label}</span>
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
