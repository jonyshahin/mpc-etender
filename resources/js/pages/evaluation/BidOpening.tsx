import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Clock, Lock, Unlock, UserCheck } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { StatTile } from '@/components/dashboard/StatTile';
import { StatusBadge } from '@/components/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate, formatDeadline, formatDateTime } from '@/lib/datetime';

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
    currency?: string | null;
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

    /**
     * The clock, sampled after mount and kept moving.
     *
     * Held in state rather than read during render: Date.now() is impure, and
     * the old countdown computed once and never moved — so a screen left open
     * kept insisting on "3d 4h remaining" long after the moment had passed, and
     * an opening request looked live well past its 30-minute window.
     */
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        // Only from the callback: setting state in the effect body itself would
        // cascade a second render on every mount.
        const timer = setInterval(() => setNow(Date.now()), 30_000);

        return () => clearInterval(timer);
    }, []);

    const requestExpired =
        pendingRequest !== null && new Date(pendingRequest.expires_at).getTime() <= now;

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
        const diff = new Date(tender.opening_date).getTime() - now;

        if (diff <= 0) {
            return t('eval.opening_time_passed');
        }

        return t('eval.time_remaining', {
            days: Math.floor(diff / 86_400_000),
            hours: Math.floor((diff % 86_400_000) / 3_600_000),
            minutes: Math.floor((diff % 3_600_000) / 60_000),
        });
    }, [now, tender.opening_date, t]);

    const sealedCount = bids.filter((b) => b.is_sealed).length;
    const openedCount = bids.length - sealedCount;

    /**
     * Money, in the currency it was bid in.
     *
     * The old formatter passed `undefined` as the locale — falling through to
     * whatever the browser is set to rather than the app's — and printed a bare
     * number, leaving the reader to assume which currency 750,000 was.
     */
    const formatAmount = (amount?: string | null, currency?: string | null) => {
        if (!amount) {
            return '—';
        }

        const value = parseFloat(amount);

        if (!Number.isFinite(value)) {
            return '—';
        }

        return new Intl.NumberFormat(locale, {
            style: currency ? 'currency' : 'decimal',
            currency: currency ?? undefined,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    };

    // Cheapest first, but only among bids whose price is actually visible.
    // Sealed bids have no amount at all and sort to the end.
    const sortedBids = useMemo(
        () =>
            [...bids].sort((a, b) => {
                if (!a.total_amount) {
                    return 1;
                }

                if (!b.total_amount) {
                    return -1;
                }

                return parseFloat(a.total_amount) - parseFloat(b.total_amount);
            }),
        [bids],
    );

    return (
        <>
            <Head title={`${t('eval.bid_opening')} — ${tender.reference_number}`} />

            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <Link
                        href={`/tenders/${tender.id}`}
                        className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4 rtl:rotate-180" aria-hidden="true" />
                        {tender.reference_number}
                    </Link>
                    <h1 className="mt-2 text-2xl font-semibold tracking-tight">
                        {t('pages.eval.bid_opening')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">{tender.title_en}</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatTile
                        label={t('table.bids')}
                        value={String(bids.length)}
                        hint={t('eval.submitted_to_this_tender')}
                        icon={UserCheck}
                    />
                    <StatTile
                        label={t('eval.sealed_bids')}
                        value={String(sealedCount)}
                        hint={t('eval.not_yet_opened')}
                        icon={Lock}
                    />
                    <StatTile
                        label={t('eval.opened_bids')}
                        value={String(openedCount)}
                        hint={t('eval.prices_visible')}
                        icon={Unlock}
                    />
                </div>

                {!isOpened && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Lock className="size-5" aria-hidden="true" />
                                {t('eval.sealed_bids')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap items-center gap-3 rounded-lg bg-muted p-4">
                                <Lock className="size-8 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div className="min-w-0">
                                    <p className="text-lg font-semibold">
                                        {t('eval.bids_sealed_count', { count: sealedCount })}
                                    </p>
                                    <p className="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
                                        <Clock className="size-4" aria-hidden="true" />
                                        {/* The absolute moment, with its zone, beside the
                                            relative one. A relative figure alone goes stale
                                            and says nothing about which clock it is on. */}
                                        <span>{formatDeadline(tender.opening_date, locale)}</span>
                                        {countdown && <span>· {countdown}</span>}
                                    </p>
                                </div>
                            </div>

                            {pendingRequest ? (
                                <div className="space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
                                    <p className="flex items-center gap-2 font-medium">
                                        <UserCheck className="size-4" aria-hidden="true" />
                                        {requestExpired
                                            ? t('eval.request_has_expired')
                                            : t('eval.awaiting_confirmation')}
                                    </p>

                                    <p className="text-sm text-muted-foreground">
                                        {requestExpired
                                            ? t('eval.request_expired_ask_again')
                                            : pendingRequest.viewer_is_authorizer
                                              ? t('eval.you_must_confirm', {
                                                    requester: pendingRequest.requested_by_name ?? '',
                                                })
                                              : t('eval.waiting_on_authorizer', {
                                                    requester: pendingRequest.requested_by_name ?? '',
                                                    authorizer: pendingRequest.authorizer_name ?? '',
                                                })}
                                    </p>

                                    {!requestExpired && (
                                        <p className="text-xs text-muted-foreground">
                                            {t('eval.request_expires', {
                                                time: formatDateTime(pendingRequest.expires_at, locale),
                                            })}
                                        </p>
                                    )}

                                    <div className="flex flex-wrap gap-2">
                                        {pendingRequest.viewer_is_authorizer && (
                                            <Button
                                                onClick={() => setConfirmOpen(true)}
                                                // The window has passed, so the server would
                                                // refuse this. Saying so beats a toast after
                                                // the click.
                                                disabled={confirmForm.processing || requestExpired}
                                            >
                                                <Unlock className="me-2 size-4" aria-hidden="true" />
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
                                <div className="space-y-3">
                                    <p className="text-sm text-muted-foreground">
                                        {t('eval.two_step_note')}
                                    </p>
                                    <div className="flex flex-wrap items-end gap-4">
                                        <div className="w-full space-y-2 sm:w-64">
                                            <Label htmlFor="authorizer">
                                                {t('eval.second_authorizer')}
                                            </Label>
                                            <Select
                                                value={form.data.authorizer_id}
                                                onValueChange={(value) =>
                                                    form.setData('authorizer_id', value)
                                                }
                                            >
                                                <SelectTrigger id="authorizer">
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
                                            {authorizers.length === 0 && (
                                                <p className="text-sm text-muted-foreground">
                                                    {t('eval.no_eligible_authorizer')}
                                                </p>
                                            )}
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
                                            <UserCheck className="me-2 size-4" aria-hidden="true" />
                                            {t('btn.request_opening')}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2">
                            {isOpened ? (
                                <Unlock className="size-5" aria-hidden="true" />
                            ) : (
                                <Lock className="size-5" aria-hidden="true" />
                            )}
                            {isOpened ? t('eval.opened_bids') : t('table.bids')}
                            <Badge variant="outline">{t('eval.bid_count', { count: bids.length })}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {bids.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                {t('empty.no_results')}
                            </p>
                        ) : (
                            <>
                                {/* Cards below md. A five-column table with a currency
                                    column does not survive a phone. */}
                                <ul className="space-y-2 md:hidden">
                                    {sortedBids.map((bid) => (
                                        <li key={bid.id} className="rounded-lg border p-4">
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <span className="min-w-0">
                                                    <span className="block truncate font-medium">
                                                        {bid.vendor?.company_name ?? '—'}
                                                    </span>
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {bid.bid_reference}
                                                    </span>
                                                </span>
                                                <StatusBadge status={bid.status} />
                                            </div>
                                            <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                                                <span className="tabular-nums">
                                                    {formatAmount(bid.total_amount, bid.currency)}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatDate(bid.submitted_at, locale)}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>

                                {/* A plain table, not DataTable: the rows are already in
                                    hand rather than paginated, and every column was
                                    declared sortable — so a header click fired a
                                    navigation this screen has no server-side sort for. */}
                                <div className="hidden overflow-x-auto md:block">
                                    <table className="w-full min-w-2xl text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="px-4 py-2 text-start font-medium">
                                                    {t('table.vendor')}
                                                </th>
                                                <th className="px-4 py-2 text-start font-medium">
                                                    {t('table.reference')}
                                                </th>
                                                <th className="px-4 py-2 text-end font-medium">
                                                    {t('table.total_amount')}
                                                </th>
                                                <th className="px-4 py-2 text-start font-medium">
                                                    {t('table.status')}
                                                </th>
                                                <th className="px-4 py-2 text-start font-medium">
                                                    {t('table.submitted')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {sortedBids.map((bid) => (
                                                <tr key={bid.id} className="border-b last:border-0">
                                                    <td className="px-4 py-3">
                                                        {bid.vendor?.company_name ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3">{bid.bid_reference}</td>
                                                    <td className="px-4 py-3 text-end tabular-nums">
                                                        {formatAmount(bid.total_amount, bid.currency)}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <StatusBadge status={bid.status} />
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                        {formatDate(bid.submitted_at, locale)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                onConfirm={confirmOpening}
                loading={confirmForm.processing}
                title={t('eval.open_bids')}
                description={t('eval.open_bids_confirm')}
            />
        </>
    );
}
