import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Check, ChevronDown, ChevronRight, Info, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

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
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const approvedSet = new Set(selectedCategoryIds);

    const toggleExpand = (id: string) => {
        setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    return (
        <>
            <Head title="Business Categories" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('pages.vendor.business_categories')}
                        description={t('vendor.categories_readonly_description')}
                    />

                    {hasOpenRequest && latestRequestId ? (
                        <Button variant="outline" asChild>
                            <Link href={`/vendor/category-requests/${latestRequestId}`}>
                                {t('btn.view_open_request')}
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href="/vendor/category-requests/create">
                                <Plus className="me-2 h-4 w-4" />
                                {t('btn.request_category_change')}
                            </Link>
                        </Button>
                    )}
                </div>

                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertDescription>{t('vendor.categories_mpc_controlled')}</AlertDescription>
                </Alert>

                <div className="space-y-3">
                    {categories.map((parent) => {
                        const hasChildren = (parent.children?.length ?? 0) > 0;
                        const isExpanded = expanded[parent.id] ?? true;
                        const parentApproved = approvedSet.has(parent.id);

                        return (
                            <Card key={parent.id}>
                                <CardContent className="pt-6">
                                    <div className="flex items-center gap-3">
                                        {hasChildren ? (
                                            <button
                                                type="button"
                                                onClick={() => toggleExpand(parent.id)}
                                                className="rounded p-1 hover:bg-muted"
                                                aria-label={t('btn.toggle')}
                                            >
                                                {isExpanded ? (
                                                    <ChevronDown className="h-4 w-4" />
                                                ) : (
                                                    <ChevronRight className="h-4 w-4" />
                                                )}
                                            </button>
                                        ) : (
                                            <div className="w-6" />
                                        )}

                                        <CategoryApprovalIcon approved={parentApproved} />

                                        <span
                                            className={
                                                parentApproved
                                                    ? 'font-medium'
                                                    : 'font-medium text-muted-foreground'
                                            }
                                        >
                                            {parent.name_en}
                                            {parent.name_ar && (
                                                <span className="ms-2 text-muted-foreground">
                                                    ({parent.name_ar})
                                                </span>
                                            )}
                                        </span>
                                    </div>

                                    {hasChildren && isExpanded && (
                                        <div className="ms-12 mt-3 space-y-2 border-s-2 border-muted ps-4">
                                            {parent.children!.map((child) => {
                                                const childApproved = approvedSet.has(child.id);
                                                return (
                                                    <div
                                                        key={child.id}
                                                        className="flex items-center gap-3"
                                                    >
                                                        <CategoryApprovalIcon approved={childApproved} />
                                                        <span
                                                            className={
                                                                childApproved
                                                                    ? ''
                                                                    : 'text-muted-foreground'
                                                            }
                                                        >
                                                            {child.name_en}
                                                            {child.name_ar && (
                                                                <span className="ms-2 text-muted-foreground">
                                                                    ({child.name_ar})
                                                                </span>
                                                            )}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

function CategoryApprovalIcon({ approved }: { approved: boolean }) {
    if (approved) {
        return (
            <span className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground">
                <Check className="h-3 w-3" />
            </span>
        );
    }
    return (
        <span className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-muted-foreground/30" />
    );
}
