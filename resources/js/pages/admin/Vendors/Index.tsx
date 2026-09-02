import { Head, Link, router } from '@inertiajs/react';
import { Building2, CheckCircle2, FileText, Plus, Search, Users, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StatTile } from '@/components/dashboard/StatTile';
import { DataTable } from '@/components/DataTable';
import { Pagination } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { VendorFormDialog } from './Form';
import type { Category } from './Form';

type VendorRow = {
    id: string;
    company_name: string;
    company_name_ar: string | null;
    email: string;
    prequalification_status: string;
    qualified_at: string | null;
    city: string | null;
    country: string | null;
    created_at: string;
    documents_count: number;
    bids_count: number;
    categories?: Array<{ id: string; name_en: string; name_ar: string | null }>;
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
    category_id: string | null;
    sort: string;
    direction: string;
};

type Props = {
    vendors: PaginatedData<VendorRow>;
    filters: Filters;
    statusCounts: Record<string, number>;
    summary: {
        total: number;
        qualified: number;
        awaiting_review: number;
        documents_pending: number;
    };
    categories: Category[];
    canCreate: boolean;
};

/** Lifecycle order, so the tabs read as a progression rather than a set. */
const STATUSES = ['pending', 'under_review', 'qualified', 'rejected', 'suspended', 'blacklisted'];

/** Sentinel: Radix Select cannot take an empty string as an item value. */
const ANY_CATEGORY = 'all';

export default function Index({
    vendors,
    filters,
    statusCounts,
    summary,
    categories,
    canCreate,
}: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const firstRender = useRef(true);

    const name = (c: { name_en: string; name_ar: string | null }) =>
        locale === 'ar' ? (c.name_ar ?? c.name_en) : c.name_en;

    // Children are qualified by their parent so two "Electrical" leaves under
    // different trades stay tellable apart.
    const categoryOptions = categories.flatMap((parent) => [
        { value: parent.id, label: name(parent) },
        ...(parent.children ?? []).map((child) => ({
            value: child.id,
            label: `${name(parent)} › ${name(child)}`,
        })),
    ]);

    // Every navigation carries the whole filter set. Rebuilding the query from
    // two keys silently dropped the category, the sort and the direction.
    const navigate = (next: Partial<Filters>) => {
        router.get(
            '/admin/vendors',
            {
                search: search || undefined,
                status: filters.status || undefined,
                category_id: filters.category_id || undefined,
                sort: filters.sort,
                direction: filters.direction,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

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
            key: 'company_name',
            label: t('table.company_name'),
            sortable: true,
            render: (value: string, row: VendorRow) => (
                <span className="block">
                    <span className="block font-medium">{value}</span>
                    {/* Stacked, not inline: ms-2 resolves against the span's own
                        dir, so inside dir="rtl" the gap landed on the far side
                        and the two names ran together. */}
                    {row.company_name_ar && (
                        <span className="block text-xs text-muted-foreground" dir="rtl">
                            {row.company_name_ar}
                        </span>
                    )}
                </span>
            ),
        },
        { key: 'email', label: t('table.email'), sortable: true },
        {
            key: 'prequalification_status',
            label: t('table.status'),
            sortable: true,
            render: (value: string) => <StatusBadge status={value} />,
        },
        {
            key: 'categories',
            label: t('table.categories'),
            render: (_value: unknown, row: VendorRow) => <CategoryChips vendor={row} name={name} />,
        },
        {
            key: 'documents_count',
            label: t('vendor.documents_on_file'),
            render: (value: number) => (
                <span className="inline-flex min-w-6 items-center justify-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums">
                    {value}
                </span>
            ),
        },
        {
            key: 'created_at',
            label: t('table.registered'),
            sortable: true,
            render: (value: string) => (
                <span className="whitespace-nowrap text-sm text-muted-foreground">
                    {formatDate(value, locale)}
                </span>
            ),
        },
    ];

    const hasFilters = Boolean(filters.search || filters.status || filters.category_id);

    const clearAll = () => {
        setSearch('');
        navigate({ search: undefined, status: undefined, category_id: undefined });
    };

    return (
        <>
            <Head title={t('pages.admin.vendors')} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.admin.vendors')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('pages.admin.vendors_description')}
                        </p>
                    </div>
                    {canCreate && (
                        <Button onClick={() => setShowCreateDialog(true)}>
                            <Plus className="me-2 size-4" />
                            {t('btn.add_vendor')}
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('pages.admin.vendors')}
                        value={String(summary.total)}
                        hint={t('vendor.on_the_register')}
                        icon={Users}
                    />
                    <StatTile
                        label={t('status.qualified')}
                        value={String(summary.qualified)}
                        hint={t('vendor.eligible_to_bid')}
                        icon={CheckCircle2}
                    />
                    <StatTile
                        label={t('vendor.awaiting_review')}
                        value={String(summary.awaiting_review)}
                        hint={t('vendor.pending_or_under_review')}
                        icon={Building2}
                    />
                    <StatTile
                        label={t('vendor.with_documents_pending')}
                        value={String(summary.documents_pending)}
                        hint={t('vendor.have_unreviewed_files')}
                        icon={FileText}
                    />
                </div>

                <div className="space-y-3">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="relative max-w-sm flex-1">
                            <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                            <Input
                                type="search"
                                placeholder={t('form.search_vendors')}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="ps-9"
                                aria-label={t('form.search_vendors')}
                            />
                        </div>

                        {/* The backend has supported this filter all along; the
                            page simply never offered it. */}
                        <Select
                            value={filters.category_id ?? ANY_CATEGORY}
                            onValueChange={(value) =>
                                navigate({
                                    category_id: value === ANY_CATEGORY ? undefined : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-full sm:w-64">
                                <SelectValue placeholder={t('vendor.all_categories')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY_CATEGORY}>
                                    {t('vendor.all_categories')}
                                </SelectItem>
                                {categoryOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                        <FilterTab
                            label={t('form.all_statuses')}
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

                {vendors.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <Users className="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
                        <p className="mt-3 font-medium">
                            {hasFilters ? t('vendor.no_matches') : t('vendor.none_yet')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {hasFilters ? t('vendor.try_clearing') : t('vendor.add_first')}
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
                        {/* Six columns do not survive a phone; the same rows
                            render as cards below md. */}
                        <ul className="space-y-2 md:hidden">
                            {vendors.data.map((vendor) => (
                                <li key={vendor.id}>
                                    <Link
                                        href={`/admin/vendors/${vendor.id}`}
                                        className="block rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">
                                                    {vendor.company_name}
                                                </span>
                                                <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                                                    {vendor.email}
                                                </span>
                                            </span>
                                            <span className="shrink-0 whitespace-nowrap">
                                                <StatusBadge status={vendor.prequalification_status} />
                                            </span>
                                        </div>
                                        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            {vendor.city && <span>{vendor.city}</span>}
                                            {/* Icon plus number rather than
                                                ":count documents": the t() helper does
                                                plain substitution with no plural forms,
                                                so that read "1 documents" — and Arabic
                                                has six forms, which a two-key English
                                                workaround would not cover. */}
                                            <span
                                                className="inline-flex items-center gap-1"
                                                title={t('vendor.documents_on_file')}
                                            >
                                                <FileText className="size-3" aria-hidden="true" />
                                                <span className="tabular-nums">{vendor.documents_count}</span>
                                                <span className="sr-only">{t('vendor.documents_on_file')}</span>
                                            </span>
                                            <span>{formatDate(vendor.created_at, locale)}</span>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        {/* DataTable carries its own pager, but it is hidden at this
                            width — without this the card view of a list longer than
                            one page was stranded on the first fifteen rows. */}
                        <Pagination className="md:hidden" links={vendors.links ?? []} />

                        <div className="hidden md:block">
                            <DataTable
                                columns={columns}
                                data={vendors}
                                // Without this the table sorts with an empty
                                // filter set: it wiped the search, status and
                                // category, and could never toggle to descending.
                                filters={filters}
                                actions={(row: VendorRow) => (
                                    <div className="flex items-center justify-end gap-3 whitespace-nowrap">
                                        <Link
                                            href={`/admin/vendors/${row.id}`}
                                            className="text-sm font-medium text-primary hover:underline"
                                        >
                                            {t('btn.view')}
                                        </Link>
                                        <Link
                                            href={`/admin/vendors/${row.id}/confirmation`}
                                            className="text-sm font-medium text-primary hover:underline"
                                        >
                                            {t('btn.confirmation')}
                                        </Link>
                                    </div>
                                )}
                            />
                        </div>
                    </>
                )}
            </div>

            {showCreateDialog && (
                <VendorFormDialog
                    categories={categories}
                    open={showCreateDialog}
                    onClose={() => setShowCreateDialog(false)}
                />
            )}
        </>
    );
}

/** At most three chips, then a count — a vendor with twelve categories otherwise owns the row. */
function CategoryChips({
    vendor,
    name,
}: {
    vendor: VendorRow;
    name: (c: { name_en: string; name_ar: string | null }) => string;
}) {
    const categories = vendor.categories ?? [];

    if (categories.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex flex-wrap items-center gap-1">
            {categories.slice(0, 3).map((category) => (
                <Badge key={category.id} variant="secondary" className="font-normal">
                    {name(category)}
                </Badge>
            ))}
            {categories.length > 3 && (
                <span className="text-xs text-muted-foreground">+{categories.length - 3}</span>
            )}
        </div>
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
