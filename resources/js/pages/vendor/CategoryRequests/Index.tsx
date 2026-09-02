import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock, Eye, FileText, Info, ListChecks, Minus, Plus, XCircle } from 'lucide-react';
import { StatTile } from '@/components/dashboard/StatTile';
import { DataTable } from '@/components/DataTable';
import { Pagination } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/datetime';

type CategoryRequestRow = {
    id: string;
    created_at: string;
    updated_at: string;
    status: 'pending' | 'under_review' | 'approved' | 'rejected' | 'withdrawn';
    adds_count: number;
    removes_count: number;
    evidence_count: number;
    reviewer_comments: string | null;
    reviewed_at: string | null;
};

type PaginatedRequests = {
    data: CategoryRequestRow[];
    links?: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
    current_page?: number;
    last_page?: number;
    per_page?: number;
};

type Props = {
    requests: PaginatedRequests;
    /**
     * DataTable merges these into every sort request. It falls back to the
     * query string when they are missing, but passing them keeps the active
     * order visible to the toggle rather than inferred.
     */
    filters: { sort: string; direction: string };
    summary: { total: number; open: number; approved: number; rejected: number };
};

function ChangesSummary({ adds, removes }: { adds: number; removes: number }) {
    const { t } = useTranslation();

    if (adds === 0 && removes === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex gap-2">
            {adds > 0 && (
                <span
                    title={t('vendor.category_requests.add_title')}
                    className="inline-flex items-center gap-1 rounded bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-300"
                >
                    <Plus className="size-3" aria-hidden="true" />
                    <span className="tabular-nums">{adds}</span>
                </span>
            )}
            {removes > 0 && (
                <span
                    title={t('vendor.category_requests.remove_title')}
                    className="inline-flex items-center gap-1 rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300"
                >
                    <Minus className="size-3" aria-hidden="true" />
                    <span className="tabular-nums">{removes}</span>
                </span>
            )}
        </div>
    );
}

export default function Index({ requests, filters, summary }: Props) {
    const { t, locale } = useTranslation();

    const date = (value: string | null) => (value ? formatDate(value, locale) : '—');

    const hasOpenRequest = summary.open > 0;
    const isEmpty = requests.data.length === 0 && summary.total === 0;

    const columns = [
        {
            key: 'created_at',
            label: t('pages.vendor.category_requests.col_submitted'),
            sortable: true,
            render: (value: string) => (
                <span className="whitespace-nowrap text-sm">{date(value)}</span>
            ),
        },
        {
            key: 'summary',
            label: t('pages.vendor.category_requests.col_changes'),
            render: (_v: unknown, row: CategoryRequestRow) => (
                <ChangesSummary adds={row.adds_count} removes={row.removes_count} />
            ),
        },
        {
            key: 'evidence_count',
            label: t('pages.vendor.category_requests.col_evidence'),
            render: (value: number) => (
                <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                    <FileText className="size-3.5" aria-hidden="true" />
                    <span className="tabular-nums">{value}</span>
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
            key: 'reviewed_at',
            label: t('pages.vendor.category_requests.col_reviewed'),
            sortable: true,
            render: (value: string | null) => (
                <span className="whitespace-nowrap text-sm text-muted-foreground">
                    {date(value)}
                </span>
            ),
        },
        {
            key: 'actions',
            label: t('table.actions'),
            render: (_v: unknown, row: CategoryRequestRow) => (
                <Button asChild variant="ghost" size="sm">
                    <Link href={`/vendor/category-requests/${row.id}`}>
                        <Eye className="me-1 size-4" aria-hidden="true" />
                        {t('btn.view')}
                    </Link>
                </Button>
            ),
        },
    ];

    const newRequestCta = hasOpenRequest ? (
        <Button variant="outline" disabled title={t('vendor.category_requests.open_request_exists')}>
            <Plus className="me-2 size-4" aria-hidden="true" />
            {t('vendor.category_requests.new_request')}
        </Button>
    ) : (
        <Button asChild>
            <Link href="/vendor/category-requests/create">
                <Plus className="me-2 size-4" aria-hidden="true" />
                {t('vendor.category_requests.new_request')}
            </Link>
        </Button>
    );

    return (
        <>
            <Head title={t('pages.vendor.category_requests.title')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.vendor.category_requests.title')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('pages.vendor.category_requests.subtitle')}
                        </p>
                    </div>
                    {!isEmpty && newRequestCta}
                </div>

                {!isEmpty && (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatTile
                            label={t('vendor.category_requests.all_requests')}
                            value={String(summary.total)}
                            hint={t('vendor.category_requests.since_you_joined')}
                            icon={ListChecks}
                        />
                        <StatTile
                            label={t('vendor.category_requests.awaiting_mpc')}
                            value={String(summary.open)}
                            hint={t('vendor.category_requests.one_at_a_time')}
                            icon={Clock}
                        />
                        <StatTile
                            label={t('status.approved')}
                            value={String(summary.approved)}
                            hint={t('vendor.category_requests.applied_to_your_file')}
                            icon={CheckCircle2}
                        />
                        <StatTile
                            label={t('status.rejected')}
                            value={String(summary.rejected)}
                            hint={t('vendor.category_requests.see_reviewer_notes')}
                            icon={XCircle}
                        />
                    </div>
                )}

                {hasOpenRequest && (
                    <div className="flex items-start gap-2 rounded-xl border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-900 dark:bg-yellow-950/40 dark:text-yellow-200">
                        <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <span>{t('vendor.category_requests.open_request_exists')}</span>
                    </div>
                )}

                {isEmpty ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <ListChecks
                            className="mx-auto size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">
                            {t('vendor.category_requests.none_yet')}
                        </p>
                        <p className="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                            {t('vendor.category_requests.empty')}
                        </p>
                        <Button asChild className="mt-4">
                            <Link href="/vendor/category-requests/create">
                                <Plus className="me-2 size-4" aria-hidden="true" />
                                {t('vendor.category_requests.submit_first')}
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        {/* Six columns do not survive a phone; the same rows
                            render as cards below md. */}
                        <ul className="space-y-2 md:hidden">
                            {requests.data.map((row) => (
                                <li key={row.id}>
                                    <Link
                                        href={`/vendor/category-requests/${row.id}`}
                                        className="block rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="text-sm font-medium">
                                                {date(row.created_at)}
                                            </span>
                                            <StatusBadge status={row.status} />
                                        </div>
                                        <div className="mt-3 flex flex-wrap items-center gap-3">
                                            <ChangesSummary
                                                adds={row.adds_count}
                                                removes={row.removes_count}
                                            />
                                            <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                <FileText className="size-3.5" aria-hidden="true" />
                                                <span className="tabular-nums">
                                                    {row.evidence_count}
                                                </span>
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        {/* DataTable carries its own pager, but it is hidden at
                            this width — without this the card view of a vendor
                            with more than fifteen requests was stuck on page one. */}
                        <Pagination className="md:hidden" links={requests.links ?? []} />

                        <div className="hidden md:block">
                            <DataTable columns={columns} data={requests} filters={filters} />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
