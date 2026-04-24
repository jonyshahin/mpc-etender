import { Head, Link } from '@inertiajs/react';
import { Eye, FileText, Info, Minus, Plus } from 'lucide-react';
import { DataTable } from '@/components/DataTable';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

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
};

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function ChangesSummary({ adds, removes }: { adds: number; removes: number }) {
    if (adds === 0 && removes === 0) {
        return <span className="text-muted-foreground">—</span>;
    }
    return (
        <div className="flex gap-2">
            {adds > 0 && (
                <span className="inline-flex items-center gap-1 rounded bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-300">
                    <Plus className="h-3 w-3" />
                    {adds}
                </span>
            )}
            {removes > 0 && (
                <span className="inline-flex items-center gap-1 rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300">
                    <Minus className="h-3 w-3" />
                    {removes}
                </span>
            )}
        </div>
    );
}

export default function Index({ requests }: Props) {
    const { t } = useTranslation();

    const hasOpenRequest = requests.data.some(
        (r) => r.status === 'pending' || r.status === 'under_review',
    );
    const isEmpty = requests.data.length === 0;

    const columns = [
        {
            key: 'created_at',
            label: t('pages.vendor.category_requests.col_submitted'),
            sortable: true,
            render: (value: string) => <span className="text-sm">{formatDate(value)}</span>,
        },
        {
            key: 'summary',
            label: t('pages.vendor.category_requests.col_changes'),
            render: (_v: any, row: CategoryRequestRow) => (
                <ChangesSummary adds={row.adds_count} removes={row.removes_count} />
            ),
        },
        {
            key: 'evidence_count',
            label: t('pages.vendor.category_requests.col_evidence'),
            render: (value: number) => (
                <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                    <FileText className="h-3.5 w-3.5" />
                    {value}
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
                <span className="text-sm text-muted-foreground">{formatDate(value)}</span>
            ),
        },
        {
            key: 'actions',
            label: t('table.actions'),
            render: (_v: any, row: CategoryRequestRow) => (
                <Button asChild variant="ghost" size="sm" aria-label={t('btn.view')}>
                    <Link href={`/vendor/category-requests/${row.id}`}>
                        <Eye className="me-1 h-4 w-4" />
                        {t('btn.view')}
                    </Link>
                </Button>
            ),
        },
    ];

    const newRequestCta = hasOpenRequest ? (
        <Button
            variant="outline"
            disabled
            title={t('vendor.category_requests.open_request_exists')}
        >
            <Plus className="me-2 h-4 w-4" />
            {t('vendor.category_requests.new_request')}
        </Button>
    ) : (
        <Button asChild>
            <Link href="/vendor/category-requests/create">
                <Plus className="me-2 h-4 w-4" />
                {t('vendor.category_requests.new_request')}
            </Link>
        </Button>
    );

    return (
        <>
            <Head title={t('pages.vendor.category_requests.title')} />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('pages.vendor.category_requests.title')}
                        description={t('pages.vendor.category_requests.subtitle')}
                    />
                    {!isEmpty && newRequestCta}
                </div>

                {hasOpenRequest && (
                    <div className="flex items-start gap-2 rounded-md border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-900 dark:bg-yellow-950/40 dark:text-yellow-200">
                        <Info className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{t('vendor.category_requests.open_request_exists')}</span>
                    </div>
                )}

                {isEmpty ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-4 py-12 text-center">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                <Info className="h-6 w-6 text-muted-foreground" />
                            </div>
                            <p className="max-w-md text-sm text-muted-foreground">
                                {t('vendor.category_requests.empty')}
                            </p>
                            <Button asChild>
                                <Link href="/vendor/category-requests/create">
                                    <Plus className="me-2 h-4 w-4" />
                                    {t('vendor.category_requests.submit_first')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <DataTable columns={columns} data={requests} />
                )}
            </div>
        </>
    );
}
