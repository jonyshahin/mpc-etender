import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    Clock,
    Download,
    FileText,
    Minus,
    Plus,
    UserCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { Textarea } from '@/components/ui/textarea';
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

type Vendor = {
    id: string;
    company_name: string;
    company_name_ar: string | null;
    email: string;
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
    vendor: Vendor;
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

    const isPending = request.status === 'pending' || request.status === 'under_review';
    const isDecided = request.status === 'approved' || request.status === 'rejected';
    const isWithdrawn = request.status === 'withdrawn';

    const [approveOpen, setApproveOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);
    const [comments, setComments] = useState('');
    const [processing, setProcessing] = useState(false);

    // Reset comments when either dialog closes so switching from approve → reject
    // (or vice versa) doesn't leak typed text across.
    const handleApproveOpenChange = (open: boolean) => {
        setApproveOpen(open);
        if (!open) setComments('');
    };

    const handleRejectOpenChange = (open: boolean) => {
        setRejectOpen(open);
        if (!open) setComments('');
    };

    const confirmApprove = () => {
        setProcessing(true);
        router.post(
            `/admin/vendor-category-requests/${request.id}/approve`,
            { action: 'approve', comments: comments.trim() || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setApproveOpen(false);
                    setComments('');
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const confirmReject = () => {
        if (comments.trim().length === 0) {
            toast.error(t('admin.category_requests.reject_comments_required'));
            return;
        }
        setProcessing(true);
        router.post(
            `/admin/vendor-category-requests/${request.id}/reject`,
            { action: 'reject', comments: comments.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRejectOpen(false);
                    setComments('');
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title={t('admin.category_requests.show_title')} />

            <div className="space-y-6">
                <Button asChild variant="ghost" size="sm" className="-ms-2">
                    <Link href="/admin/vendor-category-requests">
                        <ArrowLeft className="me-2 h-4 w-4 rtl:rotate-180" />
                        {t('admin.category_requests.back_to_queue')}
                    </Link>
                </Button>

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={t('admin.category_requests.show_title')} />
                </div>

                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                    <span className="font-medium text-foreground">
                        {request.vendor.company_name}
                    </span>
                    {request.vendor.company_name_ar && (
                        <span>({request.vendor.company_name_ar})</span>
                    )}
                    <span>·</span>
                    <a
                        href={`mailto:${request.vendor.email}`}
                        className="hover:text-foreground hover:underline"
                    >
                        {request.vendor.email}
                    </a>
                    <span>·</span>
                    <span className="inline-flex items-center gap-1">
                        <Clock className="h-3 w-3" />
                        {t('vendor.category_requests.timeline_submitted', {
                            date: formatDate(request.created_at),
                        })}
                    </span>
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
                                    {t('admin.category_requests.actions_label')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {isPending && (
                                    <div className="flex flex-col gap-2">
                                        <Button
                                            onClick={() => setApproveOpen(true)}
                                            className="w-full"
                                        >
                                            <CheckCircle className="me-2 h-4 w-4" />
                                            {t('btn.approve_request')}
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            onClick={() => setRejectOpen(true)}
                                            className="w-full"
                                        >
                                            <XCircle className="me-2 h-4 w-4" />
                                            {t('btn.reject_request')}
                                        </Button>
                                    </div>
                                )}

                                {isDecided && (
                                    <div className="space-y-4">
                                        <div>
                                            <div className="text-xs font-medium uppercase text-muted-foreground">
                                                {t('admin.category_requests.decision_by')}
                                            </div>
                                            <div className="mt-1 flex items-center gap-2 text-sm">
                                                <UserCheck className="h-4 w-4 text-muted-foreground" />
                                                <span>{request.reviewer?.name ?? '—'}</span>
                                            </div>
                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                {formatDate(request.reviewed_at)}
                                            </div>
                                        </div>

                                        {request.reviewer_comments && (
                                            <div>
                                                <div className="text-xs font-medium uppercase text-muted-foreground">
                                                    {t(
                                                        'vendor.category_requests.reviewer_comments_label',
                                                    )}
                                                </div>
                                                <div className="mt-1 whitespace-pre-wrap text-sm">
                                                    {request.reviewer_comments}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {isWithdrawn && (
                                    <Alert>
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertDescription>
                                            <div className="font-medium text-foreground">
                                                {t(
                                                    'admin.category_requests.withdrawn_by_vendor',
                                                )}
                                            </div>
                                            {request.withdraw_reason && (
                                                <div className="mt-1 text-sm text-muted-foreground">
                                                    {request.withdraw_reason}
                                                </div>
                                            )}
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <Dialog open={approveOpen} onOpenChange={handleApproveOpenChange}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('admin.category_requests.approve_confirm_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('admin.category_requests.approve_confirm_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Textarea
                            value={comments}
                            onChange={(e) => setComments(e.target.value)}
                            placeholder={t(
                                'admin.category_requests.reviewer_comments_placeholder',
                            )}
                            rows={3}
                            disabled={processing}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => handleApproveOpenChange(false)}
                            disabled={processing}
                        >
                            {t('btn.cancel')}
                        </Button>
                        <Button onClick={confirmApprove} disabled={processing}>
                            {processing ? t('ui.processing') : t('btn.confirm_approval')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={rejectOpen} onOpenChange={handleRejectOpenChange}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('admin.category_requests.reject_confirm_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('admin.category_requests.reject_confirm_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Textarea
                            value={comments}
                            onChange={(e) => setComments(e.target.value)}
                            placeholder={t(
                                'admin.category_requests.reviewer_comments_placeholder',
                            )}
                            rows={4}
                            disabled={processing}
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            <span className="text-destructive">*</span>{' '}
                            {t('admin.category_requests.reviewer_comments_required')}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => handleRejectOpenChange(false)}
                            disabled={processing}
                        >
                            {t('btn.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmReject}
                            disabled={processing || comments.trim().length === 0}
                        >
                            {processing ? t('ui.processing') : t('btn.confirm_rejection')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
