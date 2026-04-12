import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { SearchableSelect } from '@/components/SearchableSelect';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    ArrowLeft,
    CheckCircle,
    XCircle,
    Forward,
    Clock,
    User,
    MessageSquare,
} from 'lucide-react';

type Decision = {
    id: string;
    approver_id: string;
    decision: string;
    comments: string;
    delegated_from: string | null;
    decided_at: string;
    approver?: { id: string; name: string };
};

type RankingRow = {
    bid_id: string;
    vendor_name: string;
    technical_score: number;
    financial_score: number;
    final_score: number;
    rank: number;
};

type Props = {
    approval: {
        id: string;
        tender_id: string;
        approval_level: number;
        status: string;
        requested_at: string;
        deadline: string;
        tender?: {
            id: string;
            reference_number: string;
            title_en: string;
            estimated_value: string | null;
            currency: string;
            status: string;
        };
        report?: {
            id: string;
            summary: string;
            ranking_data: RankingRow[];
            recommended_bid_id: string | null;
        };
        decisions: Decision[];
    };
    projectUsers: Array<{ id: string; name: string }>;
};

function formatCurrency(value: string | null | undefined, currency: string): string {
    if (!value) return '—';
    const num = parseFloat(value);
    if (isNaN(num)) return '—';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(num);
}

function getStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'approved':
            return 'default';
        case 'rejected':
            return 'destructive';
        case 'delegated':
            return 'secondary';
        case 'pending':
            return 'outline';
        default:
            return 'outline';
    }
}

function getLevelVariant(level: number): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (level) {
        case 1:
            return 'secondary';
        case 2:
            return 'default';
        case 3:
            return 'destructive';
        default:
            return 'outline';
    }
}

function getDecisionIcon(decision: string) {
    switch (decision) {
        case 'approved':
            return <CheckCircle className="h-5 w-5 text-green-500" />;
        case 'rejected':
            return <XCircle className="h-5 w-5 text-red-500" />;
        case 'delegated':
            return <Forward className="h-5 w-5 text-blue-500" />;
        default:
            return <Clock className="text-muted-foreground h-5 w-5" />;
    }
}

export default function Show({ approval, projectUsers }: Props) {
    const { t } = useTranslation();
    const [approveOpen, setApproveOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);
    const [delegateOpen, setDelegateOpen] = useState(false);

    const approveForm = useForm({ comments: '' });
    const rejectForm = useForm({ comments: '' });
    const delegateForm = useForm({ delegatee_id: '', comments: '' });

    const isPending = approval.status === 'pending';
    const rankingData = approval.report?.ranking_data ?? [];
    const recommendedBidId = approval.report?.recommended_bid_id;

    function handleApprove() {
        approveForm.post(`/approvals/${approval.id}/approve`, {
            onSuccess: () => {
                setApproveOpen(false);
                approveForm.reset();
            },
        });
    }

    function handleReject() {
        rejectForm.post(`/approvals/${approval.id}/reject`, {
            onSuccess: () => {
                setRejectOpen(false);
                rejectForm.reset();
            },
        });
    }

    function handleDelegate() {
        delegateForm.post(`/approvals/${approval.id}/delegate`, {
            onSuccess: () => {
                setDelegateOpen(false);
                delegateForm.reset();
            },
        });
    }

    return (
        <>
            <Head title={`Approval — ${approval.tender?.reference_number ?? approval.id}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/approvals">
                                <ArrowLeft className="h-5 w-5" />
                            </Link>
                        </Button>
                        <div>
                            <Heading
                                title={
                                    approval.tender?.reference_number ?? 'Approval Review'
                                }
                            />
                            <p className="text-muted-foreground mt-1 text-sm">
                                {approval.tender?.title_en ?? ''}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={getStatusVariant(approval.status)}>
                            {approval.status.charAt(0).toUpperCase() + approval.status.slice(1)}
                        </Badge>
                        <Badge variant={getLevelVariant(approval.approval_level)}>
                            {t('approval.level')} {approval.approval_level}
                        </Badge>
                    </div>
                </div>

                {/* Tender Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('approval.tender_summary')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <p className="text-muted-foreground text-sm">{t('tender.estimated_value')}</p>
                                <p className="text-lg font-semibold">
                                    {formatCurrency(
                                        approval.tender?.estimated_value,
                                        approval.tender?.currency ?? 'USD',
                                    )}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">{t('form.currency')}</p>
                                <p className="text-lg font-semibold">
                                    {approval.tender?.currency ?? '—'}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">{t('approval.tender_status')}</p>
                                <Badge variant="outline" className="mt-1">
                                    {approval.tender?.status ?? '—'}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Evaluation Summary */}
                {approval.report && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('approval.evaluation_summary')}</CardTitle>
                            {approval.report.summary && (
                                <CardDescription className="whitespace-pre-line">
                                    {approval.report.summary}
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent>
                            {rankingData.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="px-3 py-2 text-left font-medium">
                                                    {t('table.rank')}
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    {t('table.vendor')}
                                                </th>
                                                <th className="px-3 py-2 text-right font-medium">
                                                    {t('table.technical_score')}
                                                </th>
                                                <th className="px-3 py-2 text-right font-medium">
                                                    {t('table.financial_score')}
                                                </th>
                                                <th className="px-3 py-2 text-right font-medium">
                                                    {t('table.final_score')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rankingData
                                                .sort((a, b) => a.rank - b.rank)
                                                .map((row) => {
                                                    const isRecommended =
                                                        row.bid_id === recommendedBidId;
                                                    return (
                                                        <tr
                                                            key={row.bid_id}
                                                            className={
                                                                isRecommended
                                                                    ? 'bg-green-50 font-medium dark:bg-green-950/30'
                                                                    : 'border-b'
                                                            }
                                                        >
                                                            <td className="px-3 py-2">
                                                                <span className="flex items-center gap-1">
                                                                    {row.rank}
                                                                    {isRecommended && (
                                                                        <CheckCircle className="h-4 w-4 text-green-600" />
                                                                    )}
                                                                </span>
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                {row.vendor_name}
                                                            </td>
                                                            <td className="px-3 py-2 text-right">
                                                                {row.technical_score.toFixed(2)}
                                                            </td>
                                                            <td className="px-3 py-2 text-right">
                                                                {row.financial_score.toFixed(2)}
                                                            </td>
                                                            <td className="px-3 py-2 text-right">
                                                                {row.final_score.toFixed(2)}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">
                                    {t('empty.no_ranking_data')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Approval History */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('approval.approval_history')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {approval.decisions.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                {t('empty.no_decisions')}
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {approval.decisions.map((decision) => (
                                    <div
                                        key={decision.id}
                                        className="flex gap-3 border-b pb-4 last:border-0 last:pb-0"
                                    >
                                        <div className="mt-0.5 shrink-0">
                                            {getDecisionIcon(decision.decision)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="flex items-center gap-1 font-medium">
                                                    <User className="h-3.5 w-3.5" />
                                                    {decision.approver?.name ?? 'Unknown'}
                                                </span>
                                                <Badge
                                                    variant={getStatusVariant(decision.decision)}
                                                >
                                                    {decision.decision.charAt(0).toUpperCase() +
                                                        decision.decision.slice(1)}
                                                </Badge>
                                                {decision.delegated_from && (
                                                    <span className="text-muted-foreground text-xs">
                                                        ({t('approval.delegated_from')} {decision.delegated_from})
                                                    </span>
                                                )}
                                            </div>
                                            {decision.comments && (
                                                <p className="text-muted-foreground mt-1 flex items-start gap-1 text-sm">
                                                    <MessageSquare className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                                    {decision.comments}
                                                </p>
                                            )}
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {new Date(decision.decided_at).toLocaleString()}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Action Buttons */}
                {isPending && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('approval.your_decision')}</CardTitle>
                            <CardDescription>
                                {t('approval.your_decision_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-3">
                                <Button
                                    variant="default"
                                    className="bg-green-600 hover:bg-green-700"
                                    onClick={() => setApproveOpen(true)}
                                >
                                    <CheckCircle className="mr-1.5 h-4 w-4" />
                                    {t('btn.approve')}
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() => setRejectOpen(true)}
                                >
                                    <XCircle className="mr-1.5 h-4 w-4" />
                                    {t('btn.reject')}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setDelegateOpen(true)}
                                >
                                    <Forward className="mr-1.5 h-4 w-4" />
                                    {t('btn.delegate')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Approve Dialog */}
            <Dialog open={approveOpen} onOpenChange={setApproveOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('approval.approve_request')}</DialogTitle>
                        <DialogDescription>
                            {t('approval.approve_description')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div className="space-y-2">
                            <Label htmlFor="approve-comments">{t('form.comments')}</Label>
                            <textarea className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                id="approve-comments"
                                placeholder={t('form.optional_comments')}
                                value={approveForm.data.comments}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                                    approveForm.setData('comments', e.target.value)
                                }
                                rows={4}
                            />
                            {approveForm.errors.comments && (
                                <p className="text-sm text-red-500">
                                    {approveForm.errors.comments}
                                </p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setApproveOpen(false)}
                            disabled={approveForm.processing}
                        >
                            {t('btn.cancel')}
                        </Button>
                        <Button
                            className="bg-green-600 hover:bg-green-700"
                            onClick={handleApprove}
                            disabled={approveForm.processing}
                        >
                            {approveForm.processing ? t('btn.submitting') : t('btn.confirm_approval')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('approval.reject_request')}</DialogTitle>
                        <DialogDescription>
                            {t('approval.reject_description')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div className="space-y-2">
                            <Label htmlFor="reject-comments">{t('form.comments')}</Label>
                            <textarea className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                id="reject-comments"
                                placeholder={t('form.rejection_reason')}
                                value={rejectForm.data.comments}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                                    rejectForm.setData('comments', e.target.value)
                                }
                                rows={4}
                            />
                            {rejectForm.errors.comments && (
                                <p className="text-sm text-red-500">
                                    {rejectForm.errors.comments}
                                </p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setRejectOpen(false)}
                            disabled={rejectForm.processing}
                        >
                            {t('btn.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={rejectForm.processing}
                        >
                            {rejectForm.processing ? t('btn.submitting') : t('btn.confirm_rejection')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delegate Dialog */}
            <Dialog open={delegateOpen} onOpenChange={setDelegateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('approval.delegate_approval')}</DialogTitle>
                        <DialogDescription>
                            {t('approval.delegate_description')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div className="space-y-2">
                            <Label htmlFor="delegate-user">{t('form.delegate_to')}</Label>
                            <SearchableSelect
                                options={projectUsers.map((u) => ({
                                    value: u.id,
                                    label: u.name,
                                }))}
                                value={delegateForm.data.delegatee_id}
                                onChange={(value) =>
                                    delegateForm.setData('delegatee_id', value)
                                }
                                placeholder={t('form.select_user')}
                            />
                            {delegateForm.errors.delegatee_id && (
                                <p className="text-sm text-red-500">
                                    {delegateForm.errors.delegatee_id}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="delegate-comments">{t('form.comments')}</Label>
                            <textarea className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                id="delegate-comments"
                                placeholder={t('form.optional_comments')}
                                value={delegateForm.data.comments}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                                    delegateForm.setData('comments', e.target.value)
                                }
                                rows={4}
                            />
                            {delegateForm.errors.comments && (
                                <p className="text-sm text-red-500">
                                    {delegateForm.errors.comments}
                                </p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setDelegateOpen(false)}
                            disabled={delegateForm.processing}
                        >
                            {t('btn.cancel')}
                        </Button>
                        <Button
                            onClick={handleDelegate}
                            disabled={delegateForm.processing || !delegateForm.data.delegatee_id}
                        >
                            {delegateForm.processing ? t('btn.submitting') : t('btn.confirm_delegation')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
