import { Head, Link } from '@inertiajs/react';
import { Check, ChevronDown, ChevronRight, Info, Plus, Search, Tags, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CategoryName } from '@/components/CategoryName';
import { StatTile } from '@/components/dashboard/StatTile';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Category = {
    id: string;
    name_en: string;
    name_ar: string | null;
    parent_id: string | null;
    children?: Category[];
};

type Props = {
    categories: Category[];
    selectedCategoryIds: string[];
    hasOpenRequest: boolean;
    latestRequestId?: string | null;
};

export default function Categories({
    categories,
    selectedCategoryIds,
    hasOpenRequest,
    latestRequestId,
}: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const approvedSet = useMemo(() => new Set(selectedCategoryIds), [selectedCategoryIds]);

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(search.trim().toLowerCase()), 250);

        return () => clearTimeout(timer);
    }, [search]);

    const matches = (category: Category) =>
        !debounced ||
        category.name_en.toLowerCase().includes(debounced) ||
        (category.name_ar ?? '').toLowerCase().includes(debounced);

    // A parent survives the filter if it matches, or if any of its children do —
    // otherwise searching for a leaf hides the branch it lives on.
    const visible = useMemo(
        () =>
            categories
                .map((parent) => ({
                    ...parent,
                    children: (parent.children ?? []).filter(
                        (child) => matches(parent) || matches(child),
                    ),
                }))
                .filter(
                    (parent) => matches(parent) || (parent.children?.length ?? 0) > 0,
                ),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [categories, debounced],
    );

    const totalCategories = useMemo(
        () => categories.reduce((sum, parent) => sum + 1 + (parent.children?.length ?? 0), 0),
        [categories],
    );

    return (
        <>
            <Head title={t('pages.vendor.business_categories')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.vendor.business_categories')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('vendor.categories_readonly_description')}
                        </p>
                    </div>

                    {hasOpenRequest && latestRequestId ? (
                        <Button variant="outline" asChild>
                            <Link href={`/vendor/category-requests/${latestRequestId}`}>
                                {t('btn.view_open_request')}
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href="/vendor/category-requests/create">
                                <Plus className="me-2 size-4" aria-hidden="true" />
                                {t('btn.request_category_change')}
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatTile
                        label={t('vendor.approved_categories')}
                        value={String(selectedCategoryIds.length)}
                        hint={t('vendor.you_may_bid_in_these')}
                        icon={Check}
                        emphasis
                    />
                    <StatTile
                        label={t('vendor.all_categories')}
                        value={String(totalCategories)}
                        hint={t('vendor.offered_by_mpc')}
                        icon={Tags}
                    />
                </div>

                <Alert>
                    <Info className="size-4" aria-hidden="true" />
                    <AlertDescription>{t('vendor.categories_mpc_controlled')}</AlertDescription>
                </Alert>

                <div className="relative max-w-sm">
                    <Search
                        className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('form.search_categories')}
                        aria-label={t('form.search_categories')}
                        className="ps-9"
                    />
                </div>

                {visible.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <Tags className="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
                        <p className="mt-3 font-medium">
                            {debounced ? t('empty.no_matches') : t('empty.no_categories')}
                        </p>
                        <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                            {debounced
                                ? t('empty.try_clearing')
                                : t('vendor.categories_none_configured')}
                        </p>
                        {debounced && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="mt-4"
                                onClick={() => setSearch('')}
                            >
                                <X className="me-2 size-4" aria-hidden="true" />
                                {t('tender.clear_filters')}
                            </Button>
                        )}
                    </div>
                ) : (
                    <ul className="space-y-2">
                        {visible.map((parent) => {
                            const children = parent.children ?? [];
                            const hasChildren = children.length > 0;
                            // Default open while filtering: a match buried in a
                            // collapsed branch reads as "no results".
                            const isExpanded = expanded[parent.id] ?? true;
                            const approvedChildren = children.filter((child) =>
                                approvedSet.has(child.id),
                            ).length;

                            return (
                                <li key={parent.id} className="rounded-xl border bg-card p-4">
                                    <div className="flex items-center gap-3">
                                        {hasChildren ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setExpanded((prev) => ({
                                                        ...prev,
                                                        [parent.id]: !isExpanded,
                                                    }))
                                                }
                                                aria-expanded={isExpanded}
                                                aria-label={t('btn.toggle')}
                                                className="rounded p-1 hover:bg-muted"
                                            >
                                                {isExpanded ? (
                                                    <ChevronDown className="size-4" />
                                                ) : (
                                                    <ChevronRight className="size-4 rtl:rotate-180" />
                                                )}
                                            </button>
                                        ) : (
                                            <span className="w-6" />
                                        )}

                                        <ApprovalMark approved={approvedSet.has(parent.id)} />
                                        <CategoryName name_en={parent.name_en} name_ar={parent.name_ar} />

                                        {hasChildren && (
                                            <span className="ms-auto shrink-0 text-xs text-muted-foreground">
                                                {/* <bdi>: "2 / 5" is digits around
                                                    a neutral slash, which RTL
                                                    reorders to "5 / 2". */}
                                                <bdi>
                                                    {approvedChildren} / {children.length}
                                                </bdi>
                                            </span>
                                        )}
                                    </div>

                                    {hasChildren && isExpanded && (
                                        <ul className="ms-9 mt-3 space-y-2 border-s-2 border-muted ps-4">
                                            {children.map((child) => (
                                                <li
                                                    key={child.id}
                                                    className="flex items-center gap-3"
                                                >
                                                    <ApprovalMark
                                                        approved={approvedSet.has(child.id)}
                                                    />
                                                    <CategoryName name_en={child.name_en} name_ar={child.name_ar} />
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </>
    );
}

function ApprovalMark({ approved }: { approved: boolean }) {
    const { t } = useTranslation();

    return (
        <span
            title={approved ? t('vendor.approved_category') : t('vendor.not_approved_category')}
            className={cn(
                'inline-flex size-5 shrink-0 items-center justify-center rounded-full',
                approved
                    ? 'bg-primary text-primary-foreground'
                    : 'border border-muted-foreground/30',
            )}
        >
            {approved && <Check className="size-3" aria-hidden="true" />}
            <span className="sr-only">
                {approved ? t('vendor.approved_category') : t('vendor.not_approved_category')}
            </span>
        </span>
    );
}
