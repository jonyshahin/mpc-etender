import { useState, useMemo } from 'react';
import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatDateTime } from '@/lib/datetime';
import { Clock, Lock, Unlock, UserCheck } from 'lucide-react';

type Bid = {
    id: string;
    vendor_id: string;
    bid_reference: string;
    status: string;
    /**
     * Absent, not null, while the bid is still sealed — the server omits the
     * key entirely rather than sending a price nobody is entitled to yet.
     */
    total_amount?: string | null;
    is_sealed: boolean;
    submitted_at: string;
    opened_at: string | null;
    vendor?: { id: string; company_name: string };
};

type Props = {
    tender: {
        id: string;
        reference_number: string;
        title_en: string;
        status: string;
        opening_date: string;
        submission_deadline: string;
    };
    bids: Bid[];
    authorizers: Array<{ id: string; name: string }>;
    canOpen: boolean;
    isOpened: boolean;
    /**
     * An opening awaiting its second signature, if there is one.
     *
     * Opening is deliberately two steps: one person requests it, a different
     * person confirms from their own session. Whoever is looking needs to know
     * which half they are.
     */
    pendingRequest: {
        id: string;
        requested_by_name: string | null;
        authorizer_name: string | null;
        requested_at: string;
        expires_at: string;
        viewer_is_authorizer: boolean;
        viewer_is_requester: boolean;
    } | null;
};

export default function BidOpening({
    tender,
    bids,
    authorizers,
    canOpen,
    isOpened,
    pendingRequest,
}: Props) {
    const { t, locale } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const form = useForm({ authorizer_id: '' });
    const confirmForm = useForm({});

    // Requesting is reversible — either party can cancel — so it needs no
    // confirmation step. Confirming unseals every bid and cannot be undone,
    // which is where the friction belongs.
    const requestOpening = () => {
        form.post(`/tenders/${tender.id}/open-bids`, { preserveScroll: true });
    };

    const confirmOpening = () => {
        if (pendingRequest) {
            confirmForm.post(`/tenders/${tender.id}/open-bids/${pendingRequest.id}/confirm`, {
                preserveScroll: true,
            });
        }

        setConfirmOpen(false);
    };

    const cancelRequest = () => {
        if (pendingRequest) {
            confirmForm.delete(`/tenders/${tender.id}/open-bids/${pendingRequest.id}`, {
                preserveScroll: true,
            });
        }
    };

    const countdown = useMemo(() => {
        const opening = new Date(tender.opening_date).getTime();
        const now = Date.now();
        const diff = opening - now;
        if (diff <= 0) return t('eval.opening_time_passed');
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        return t('eval.time_remaining', { days, hours, minutes });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tender.opening_date]);

    const sealedCount = bids.filter((b) => b.is_sealed).length;

    const formatCurrency = (amount: string | null) => {
        if (!amount) return '-';
        return parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const sortedBids = useMemo(() => {
        return [...bids].sort((a, b) => {
            if (!a.total_amount) return 1;
            if (!b.total_amount) return -1;
            return parseFloat(a.total_amount) - parseFloat(b.total_amount);
        });
    }, [bids]);

    const columns = [
        {
            key: 'vendor',
            label: t('table.vendor'),
            sortable: true,
            render: (_: any, row: Bid) => row.vendor?.company_name ?? '-',
        },
        { key: 'bid_reference', label: t('table.reference'), sortable: true },
        {
            key: 'total_amount',
            label: t('table.total_amount'),
            sortable: true,
            render: (value: string | null) => formatCurrency(value),
        },
        {
            key: 'status',
            label: t('table.status'),
            sortable: true,
            render: (value: string) => <StatusBadge status={value} />,
        },
        {
            key: 'submitted_at',
            label: t('table.submitted'),
            sortable: true,
            render: (value: string) => formatDate(value),
        },
    ];

    return (
        <>
            <Head title={`${t('eval.bid_opening')} - ${tender.reference_number}`} />
            <Heading title={t('pages.eval.bid_opening')} description={`${tender.reference_number} - ${tender.title_en}`} />

            <div className="mt-6 space-y-6">
                {!isOpened ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Lock className="h-5 w-5" />
                                {t('eval.sealed_bids')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3 rounded-lg bg-muted p-4">
                                <Lock className="h-8 w-8 text-muted-foreground" />
                                <div>
                                    <p className="text-lg font-semibold">
                                        {t('eval.bids_sealed_count', { count: sealedCount })}
                                    </p>
                                    <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                        <Clock className="h-4 w-4" />
                                        {countdown}
                                    </p>
                                </div>
                            </div>

                            {pendingRequest ? (
                                <div className="space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
                                    <p className="flex items-center gap-2 font-medium">
                                        <UserCheck className="size-4" aria-hidden="true" />
                                        {t('eval.awaiting_confirmation')}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {pendingRequest.viewer_is_authorizer
                                            ? t('eval.you_must_confirm', {
                                                  requester: pendingRequest.requested_by_name ?? '',
                                              })
                                            : t('eval.waiting_on_authorizer', {
                                                  requester: pendingRequest.requested_by_name ?? '',
                                                  authorizer: pendingRequest.authorizer_name ?? '',
                                              })}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {t('eval.request_expires', {
                                            time: formatDateTime(pendingRequest.expires_at, locale),
                                        })}
                                    </p>

                                    <div className="flex flex-wrap gap-2">
                                        {pendingRequest.viewer_is_authorizer && (
                                            <Button
                                                onClick={() => setConfirmOpen(true)}
                                                disabled={confirmForm.processing}
                                            >
                                                <Unlock className="me-2 size-4" />
                                                {t('btn.confirm_opening')}
                                            </Button>
                                        )}
                                        {(pendingRequest.viewer_is_authorizer ||
                                            pendingRequest.viewer_is_requester) && (
                                            <Button
                                                variant="outline"
                                                onClick={cancelRequest}
                                                disabled={confirmForm.processing}
                                            >
                                                {t('btn.cancel_request')}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ) : !canOpen ? (
                                /* Neither a pending request nor an openable tender left
                                   this whole area blank, with nothing to say why. */
                                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                    {t('eval.nothing_to_do_yet')}
                                </p>
                            ) : (
                                (
                                    <div className="space-y-3">
                                        <p className="text-sm text-muted-foreground">
                                            {t('eval.two_step_note')}
                                        </p>
                                        <div className="flex flex-wrap items-end gap-4">
                                            <div className="w-64 space-y-2">
                                                <label className="text-sm font-medium">
                                                    {t('eval.second_authorizer')}
                                                </label>
                                                <Select
                                                    value={form.data.authorizer_id}
                                                    onValueChange={(value) =>
                                                        form.setData('authorizer_id', value)
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue
                                                            placeholder={t('form.select_authorizer')}
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {authorizers.map((auth) => (
                                                            <SelectItem key={auth.id} value={auth.id}>
                                                                {auth.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {form.errors.authorizer_id && (
                                                    <p className="text-sm text-destructive">
                                                        {form.errors.authorizer_id}
                                                    </p>
                                                )}
                                            </div>
                                            <Button
                                                onClick={requestOpening}
                                                disabled={!form.data.authorizer_id || form.processing}
                                            >
                                                <UserCheck className="me-2 size-4" />
                                                {t('btn.request_opening')}
                                            </Button>
                                        </div>
                                    </div>
                                )
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Unlock className="h-5 w-5" />
                                {t('eval.opened_bids')}
                                <Badge variant="outline" className="ml-2">
                                    {t('eval.bid_count', { count: bids.length })}
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DataTable columns={columns} data={{ data: sortedBids }} />
                        </CardContent>
                    </Card>
                )}
            </div>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                onConfirm={confirmOpening}
                title={t('eval.open_bids')}
                description={t('eval.open_bids_confirm')}
            />
        </>
    );
}
