import { Head, Link, router } from '@inertiajs/react';
import { Eye, FileText, Inbox, Minus, Plus } from 'lucide-react';
import { DataTable } from '@/components/DataTable';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

type CategoryRequestRow = {
    id: string;
    created_at: string;
    status: 'pending' | 'under_review' | 'approved' | 'rejected' | 'withdrawn';
    adds_count: number;
    removes_count: number;
    evidence_count: number;
    reviewed_at: string | null;
    vendor: {
        id: string;
        company_name: string;
        company_name_ar: string | null;
        email: string;
    };
    reviewer: {
        id: string;
        name: string;
    } | null;
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
    filters: {
        status: string;
    };
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

export default function Index({ requests, filters }: Props) {
    const { t } = useTranslation();
    const currentStatus = filters.status ?? 'pending';
    const isEmpty = requests.data.length === 0;

    const handleStatusChange = (value: string) => {
        router.get(
            '/admin/vendor-category-requests',
            value === 'all' ? {} : { status: value },
            { preserveState: true, preserveScroll: true },
        );
    };

    const clearFilter = () => {
        router.get(
            '/admin/vendor-category-requests',
            { status: 'all' },
            { preserveState: true, preserveScroll: true },
        );
    };

    const columns = [
        {
            key: 'vendor',
            label: t('admin.category_requests.col_vendor'),
            render: (_v: unknown, row: CategoryRequestRow) => (
                <div className="min-w-0">
                    <div className="truncate text-sm font-medium">
                        {row.vendor.company_name}
                        {row.vendor.company_name_ar && (
                            <span className="ms-2 text-muted-foreground">
                                ({row.vendor.company_name_ar})
                            </span>
                        )}
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                        {row.vendor.email}
                    </div>
                </div>
            ),
        },
        {
            key: 'created_at',
            label: t('admin.category_requests.col_submitted'),
            sortable: true,
            render: (value: string) => (
                <span className="text-sm">{formatDate(value)}</span>
            ),
        },
        {
            key: 'summary',
            label: t('admin.category_requests.col_changes'),
            render: (_v: unknown, row: CategoryRequestRow) => (
                <ChangesSummary adds={row.adds_count} removes={row.removes_count} />
            ),
        },
        {
            key: 'evidence_count',
            label: t('admin.category_requests.col_evidence'),
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
            key: 'reviewer',
            label: t('admin.category_requests.col_reviewer'),
            render: (_v: unknown, row: CategoryRequestRow) =>
                row.reviewer ? (
                    <span className="text-sm">{row.reviewer.name}</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'reviewed_at',
            label: t('admin.category_requests.col_reviewed'),
            sortable: true,
            render: (value: string | null) => (
                <span className="text-sm text-muted-foreground">{formatDate(value)}</span>
            ),
        },
        {
            key: 'actions',
            label: t('table.actions'),
            render: (_v: unknown, row: CategoryRequestRow) => (
                <Button asChild variant="ghost" size="sm" aria-label={t('btn.view')}>
                    <Link href={`/admin/vendor-category-requests/${row.id}`}>
                        <Eye className="me-1 h-4 w-4" />
                        {t('btn.view')}
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title={t('admin.category_requests.page_title')} />

            <div className="space-y-6">
                <Heading
                    title={t('admin.category_requests.page_title')}
                    description={t('admin.category_requests.page_subtitle')}
                />

                <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-medium text-muted-foreground">
                            {t('admin.category_requests.filter_label')}
                        </label>
                        <Select value={currentStatus} onValueChange={handleStatusChange}>
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {t('admin.category_requests.filter_all')}
                                </SelectItem>
                                <SelectItem value="pending">{t('status.pending')}</SelectItem>
                                <SelectItem value="under_review">
                                    {t('status.under_review')}
                                </SelectItem>
                                <SelectItem value="approved">{t('status.approved')}</SelectItem>
                                <SelectItem value="rejected">{t('status.rejected')}</SelectItem>
                                <SelectItem value="withdrawn">{t('status.withdrawn')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {isEmpty ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-4 py-12 text-center">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                <Inbox className="h-6 w-6 text-muted-foreground" />
                            </div>
                            <p className="max-w-md text-sm text-muted-foreground">
                                {t('admin.category_requests.empty_filtered')}
                            </p>
                            {currentStatus !== 'all' && (
                                <Button variant="outline" size="sm" onClick={clearFilter}>
                                    {t('admin.category_requests.show_all')}
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <DataTable columns={columns} data={requests} />
                )}
            </div>
        </>
    );
}
