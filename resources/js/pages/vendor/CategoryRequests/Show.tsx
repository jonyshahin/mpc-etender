import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Clock, Download, FileText, Minus, Plus, UserCheck, XCircle } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

type CategoryItem = {
    category_id: string;
    name_en: string | null;
    name_ar: string | null;
    parent_id: string | null;
};

type Evidence = {
    id: string;
    original_name: string;
    mime_type: string;
    size: number;
    created_at: string;
    download_url: string;
};

type Reviewer = {
    id: string;
    name: string;
};

type CategoryRequestDetail = {
    id: string;
    status: 'pending' | 'under_review' | 'approved' | 'rejected' | 'withdrawn';
    justification: string;
    reviewer_comments: string | null;
    withdraw_reason: string | null;
    reviewer: Reviewer | null;
    reviewed_at: string | null;
    created_at: string;
    updated_at: string;
    adds: CategoryItem[];
    removes: CategoryItem[];
    evidence: Evidence[];
};

type Props = {
    request: CategoryRequestDetail;
};

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function CategoryList({
    items,
    operation,
}: {
    items: CategoryItem[];
    operation: 'add' | 'remove';
}) {
    const isAdd = operation === 'add';
    const Icon = isAdd ? Plus : Minus;
    const iconClass = isAdd ? 'text-green-600' : 'text-red-600';

    return (
        <ul className="space-y-2">
            {items.map((cat) => (
                <li key={cat.category_id} className="flex items-start gap-2 text-sm">
                    <Icon className={`mt-0.5 h-3.5 w-3.5 shrink-0 ${iconClass}`} />
                    <span>
                        {cat.name_en ?? '—'}
                        {cat.name_ar && (
                            <span className="ms-2 text-muted-foreground">({cat.name_ar})</span>
                        )}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export default function Show({ request }: Props) {
    const { t } = useTranslation();

    const canWithdraw = request.status === 'pending' || request.status === 'under_review';
    const [withdrawOpen, setWithdrawOpen] = useState(false);
    const [withdrawReason, setWithdrawReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const confirmWithdraw = () => {
        setProcessing(true);
        router.delete(`/vendor/category-requests/${request.id}`, {
            data: { reason: withdrawReason.trim() || undefined },
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    const timelineSubmitted = t('vendor.category_requests.timeline_submitted', {
        date: formatDate(request.created_at),
    });
    const timelineReviewed = request.reviewed_at && request.reviewer
        ? t('vendor.category_requests.timeline_reviewed_by', {
              date: formatDate(request.reviewed_at),
              name: request.reviewer.name,
          })
        : null;
    const timelineWithdrawn = request.status === 'withdrawn'
        ? t('vendor.category_requests.timeline_withdrawn', {
              date: formatDate(request.updated_at),
          })
        : null;

    return (
        <>
            <Head title={t('vendor.category_requests.show_title')} />

            <div className="space-y-6">
                <Button asChild variant="ghost" size="sm" className="-ms-2">
                    <Link href="/vendor/category-requests">
                        <ArrowLeft className="me-2 h-4 w-4 rtl:rotate-180" />
                        {t('btn.back_to_requests')}
                    </Link>
                </Button>

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('vendor.category_requests.show_title')}
                        description={t('vendor.category_requests.timeline_submitted', {
                            date: formatDate(request.created_at),
                        })}
                    />
                    {canWithdraw && (
                        <Button
                            variant="destructive"
                            onClick={() => setWithdrawOpen(true)}
                        >
                            <XCircle className="me-2 h-4 w-4" />
                            {t('btn.withdraw_request')}
                        </Button>
                    )}
                </div>

                <div className="flex items-center gap-3">
                    <span className="text-sm font-medium text-muted-foreground">
                        {t('vendor.category_requests.status_label')}:
                    </span>
                    <StatusBadge status={request.status} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('vendor.category_requests.justification_label')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="whitespace-pre-wrap text-sm leading-relaxed">
                                    {request.justification}
                                </p>
                            </CardContent>
                        </Card>

                        {request.adds.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('vendor.category_requests.add_title')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CategoryList items={request.adds} operation="add" />
                                </CardContent>
                            </Card>
                        )}

                        {request.removes.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('vendor.category_requests.remove_title')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CategoryList items={request.removes} operation="remove" />
                                </CardContent>
                            </Card>
                        )}

                        {request.evidence.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('vendor.category_requests.evidence_label')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-2">
                                        {request.evidence.map((e) => (
                                            <li
                                                key={e.id}
                                                className="flex items-center justify-between gap-3 rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                                            >
                                                <div className="flex min-w-0 items-center gap-2">
                                                    <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                    <div className="min-w-0">
                                                        <div className="truncate text-sm font-medium">
                                                            {e.original_name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {formatFileSize(e.size)}
                                                        </div>
                                                    </div>
                                                </div>
                                                <a
                                                    href={e.download_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex shrink-0 items-center gap-1 text-sm text-primary hover:underline"
                                                >
                                                    <Download className="h-4 w-4" />
                                                    {t('btn.download')}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('vendor.category_requests.timeline_label')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-3 text-sm">
                                    <li className="flex items-start gap-2">
                                        <Clock className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span>{timelineSubmitted}</span>
                                    </li>
                                    {timelineReviewed && (
                                        <li className="flex items-start gap-2">
                                            <UserCheck className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span>{timelineReviewed}</span>
                                        </li>
                                    )}
                                    {timelineWithdrawn && (
                                        <li className="flex items-start gap-2">
                                            <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span>{timelineWithdrawn}</span>
                                        </li>
                                    )}
                                </ul>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('vendor.category_requests.reviewer_comments_label')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {request.reviewer_comments ? (
                                    <p className="whitespace-pre-wrap text-sm leading-relaxed">
                                        {request.reviewer_comments}
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t('vendor.category_requests.no_reviewer_comments')}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {request.status === 'withdrawn' && request.withdraw_reason && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('vendor.category_requests.withdraw_reason_label')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="whitespace-pre-wrap text-sm leading-relaxed">
                                        {request.withdraw_reason}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={withdrawOpen} onOpenChange={setWithdrawOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('vendor.category_requests.withdraw_confirm_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('vendor.category_requests.withdraw_confirm_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <textarea
                            value={withdrawReason}
                            onChange={(e) => setWithdrawReason(e.target.value)}
                            placeholder={t(
                                'vendor.category_requests.withdraw_reason_placeholder',
                            )}
                            rows={3}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            disabled={processing}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setWithdrawOpen(false)}
                            disabled={processing}
                        >
                            {t('btn.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmWithdraw}
                            disabled={processing}
                        >
                            {processing
                                ? t('ui.processing')
                                : t('btn.confirm_withdraw')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
