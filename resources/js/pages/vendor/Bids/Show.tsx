import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarClock,
    Save,
    SendHorizonal,
    ShieldCheck,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { FileUpload  } from '@/components/FileUpload';
import type {ExistingDoc} from '@/components/FileUpload';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formatDeadline } from '@/lib/datetime';
import { DEADLINE_TONE_CLASS, deadlineStatus } from '@/lib/deadline';
import { localized } from '@/lib/locales';
import { formatMoney, formatQuantity } from '@/lib/money';
import { cn } from '@/lib/utils';

type BoqItem = {
    id: string;
    item_code: string;
    description_en: string;
    description_ar: string | null;
    unit: string;
    quantity: string;
};

type BoqSection = {
    id: string;
    title: string;
    title_ar: string | null;
    items: BoqItem[];
};

type BoqPriceEntry = {
    unit_price: string | number;
    total_price: string | number;
};

type Props = {
    bid: {
        id: string;
        bid_reference: string;
        status: string;
        status_label_key: string;
        total_amount: string | null;
        currency: string;
        technical_notes: string | null;
        submitted_at: string | null;
        is_sealed: boolean;
        withdrawal_reason: string | null;
        /** Server-derived from BidStatus rather than compared as a literal here. */
        is_withdrawn: boolean;
        is_rejected: boolean;
    };
    tender: {
        id: string;
        reference_number: string;
        title_en: string;
        title_ar: string | null;
        currency: string;
        status: string;
        submission_deadline: string | null;
        opening_date: string | null;
        is_two_envelope: boolean;
        boq_sections: BoqSection[];
    };
    boqPrices: Record<string, BoqPriceEntry>;
    documents: {
        single: ExistingDoc[];
        technical: ExistingDoc[];
        financial: ExistingDoc[];
    };
    canEdit: boolean;
    canSubmit: boolean;
    canManageDocuments: boolean;
    canWithdraw: boolean;
    /**
     * Built from BidDocType on the server. The page used to keep three
     * hardcoded copies of these lists, which validation could not track.
     */
    docTypes: {
        single: Array<{ value: string; labelKey: string }>;
        technical: Array<{ value: string; labelKey: string }>;
        financial: Array<{ value: string; labelKey: string }>;
    };
};

export default function Show({
    bid,
    tender,
    boqPrices,
    documents,
    canEdit,
    canSubmit,
    canManageDocuments,
    canWithdraw,
    docTypes,
}: Props) {
    const { t, locale } = useTranslation();

    // The bid's own currency, stamped from the tender at draft time. The
    // tender's is the fallback for a row written before that was the case.
    const currency = bid.currency ?? tender.currency;
    const deadline = deadlineStatus(tender.submission_deadline);

    // Envelope pickers, from the server's BidDocType rather than three
    // hardcoded copies here. Labels resolve at render so a locale switch does
    // not need a round-trip.
    const TECHNICAL_DOC_TYPES = useMemo(
        () => docTypes.technical.map((o) => ({ value: o.value, label: t(o.labelKey) })),
        [docTypes.technical, t],
    );
    const FINANCIAL_DOC_TYPES = useMemo(
        () => docTypes.financial.map((o) => ({ value: o.value, label: t(o.labelKey) })),
        [docTypes.financial, t],
    );
    const ALL_DOC_TYPES = useMemo(
        () => docTypes.single.map((o) => ({ value: o.value, label: t(o.labelKey) })),
        [docTypes.single, t],
    );

    // Editable price state — initialized from server-truth, then owned by the form.
    // Rendered as `<Input>` cells when canEdit, otherwise the boqPrices prop is
    // read directly for the read-only display.
    const [prices, setPrices] = useState<Record<string, { unit_price: number; total_price: number }>>(() => {
        const initial: Record<string, { unit_price: number; total_price: number }> = {};
        tender.boq_sections.forEach((section) => {
            section.items.forEach((item) => {
                const existing = boqPrices[item.id];
                initial[item.id] = {
                    unit_price: existing ? Number(existing.unit_price) : 0,
                    total_price: existing ? Number(existing.total_price) : 0,
                };
            });
        });

        return initial;
    });

    const [technicalNotes, setTechnicalNotes] = useState(bid.technical_notes ?? '');
    const [saving, setSaving] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [withdrawing, setWithdrawing] = useState(false);
    const [showSubmitConfirm, setShowSubmitConfirm] = useState(false);
    const [showWithdrawDialog, setShowWithdrawDialog] = useState(false);
    const [withdrawReason, setWithdrawReason] = useState('');

    const handlePriceChange = useCallback((itemId: string, quantity: string, value: string) => {
        const unitPrice = parseFloat(value) || 0;
        const qty = parseFloat(quantity) || 0;
        setPrices((prev) => ({
            ...prev,
            [itemId]: {
                unit_price: unitPrice,
                total_price: unitPrice * qty,
            },
        }));
    }, []);

    const sectionTotals = useMemo(() => {
        const totals: Record<string, number> = {};
        tender.boq_sections.forEach((section) => {
            totals[section.id] = section.items.reduce((sum, item) => {
                const price = canEdit
                    ? prices[item.id]?.total_price ?? 0
                    : Number(boqPrices[item.id]?.total_price ?? 0);

                return sum + price;
            }, 0);
        });

        return totals;
    }, [prices, boqPrices, tender.boq_sections, canEdit]);

    // Whether the vendor has entered anything at all. Distinguishes a bid
    // genuinely priced at zero — which submission rejects — from one not yet
    // started, so neither renders as the other.
    const hasAnyPrice = useMemo(
        () =>
            canEdit
                ? Object.values(prices).some((entry) => entry.unit_price > 0)
                : Object.keys(boqPrices).length > 0,
        [prices, boqPrices, canEdit],
    );

    const grandTotal = useMemo(
        () => Object.values(sectionTotals).reduce((sum, v) => sum + v, 0),
        [sectionTotals],
    );

    function buildPayload() {
        const boq_prices = Object.entries(prices)
            .filter(([, entry]) => entry.unit_price > 0)
            .map(([boq_item_id, entry]) => ({
                boq_item_id,
                unit_price: entry.unit_price,
                total_price: entry.total_price,
            }));

        return { boq_prices, technical_notes: technicalNotes };
    }

    function saveDraft() {
        setSaving(true);
        router.put(`/vendor/bids/${bid.id}`, buildPayload(), {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    }

    function submitBid() {
        setSubmitting(true);
        // Two-step on purpose: PUT to persist final prices, then POST submit to seal.
        // Splitting the requests means the seal step has nothing to validate beyond
        // the policy check, and a failure on save doesn't leave the bid half-sealed.
        router.put(`/vendor/bids/${bid.id}`, buildPayload(), {
            preserveScroll: true,
            onSuccess: () => {
                router.post(`/vendor/bids/${bid.id}/submit`, {}, {
                    onFinish: () => setSubmitting(false),
                });
            },
            onError: () => setSubmitting(false),
        });
    }

    function withdraw() {
        setWithdrawing(true);
        router.post(
            `/vendor/bids/${bid.id}/withdraw`,
            { reason: withdrawReason },
            {
                onFinish: () => {
                    setWithdrawing(false);
                    setShowWithdrawDialog(false);
                },
            },
        );
    }

    return (
        <>
            <Head title={bid.bid_reference} />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/vendor/bids">
                            <ArrowLeft className="me-1 size-4 rtl:rotate-180" />
                            {t('btn.back_to_my_bids')}
                        </Link>
                    </Button>
                </div>

                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <p className="font-mono text-sm text-muted-foreground">{bid.bid_reference}</p>
                        <Heading title={localized(locale, tender.title_en, tender.title_ar)} />
                        <div className="flex items-center gap-2">
                            <StatusBadge status={bid.status} />
                            <span className="font-mono text-xs text-muted-foreground">{tender.reference_number}</span>
                        </div>
                    </div>

                    {canWithdraw && (
                        <Button variant="destructive" onClick={() => setShowWithdrawDialog(true)}>
                            <AlertTriangle className="me-1 size-4" />
                            {t('btn.withdraw_bid')}
                        </Button>
                    )}
                </div>

                {/* The page never said when submission closes — the one fact a
                    bid can be rejected for getting wrong. formatDeadline names
                    the zone, since the vendor is not necessarily in it. */}
                {tender.submission_deadline && (
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg border bg-muted/40 px-4 py-3 text-sm">
                        <CalendarClock
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span className="font-medium">{t('tender.submission_deadline')}:</span>
                        <span>{formatDeadline(tender.submission_deadline, locale)}</span>
                        {deadline && (
                            <span className={cn('text-xs', DEADLINE_TONE_CLASS[deadline.tone])}>
                                {t(deadline.labelKey, { count: deadline.days })}
                            </span>
                        )}
                    </div>
                )}

                {/* A draft on a live tender counts for nothing until it is sent;
                    a sealed one is done and should say so rather than looking
                    merely read-only. */}
                {canEdit && (
                    <div className="rounded-lg border border-amber-500/40 bg-amber-500/5 px-4 py-3 text-sm">
                        {t('bid.draft_deadline_warning')}
                    </div>
                )}
                {bid.is_sealed && (
                    <div className="flex items-start gap-2 rounded-lg border px-4 py-3 text-sm text-muted-foreground">
                        <ShieldCheck className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        {t('bid.sealed_notice')}
                    </div>
                )}
                {!canEdit && !bid.is_sealed && !bid.is_withdrawn && !bid.is_rejected && (
                    <div className="rounded-lg border px-4 py-3 text-sm text-muted-foreground">
                        {t('bid.tender_closed_banner')}
                    </div>
                )}

                {/* Withdrawn / rejected banner */}
                {(bid.is_withdrawn || bid.is_rejected) && (
                    <Card className="border-destructive/50 bg-destructive/5">
                        <CardContent className="py-4">
                            <div className="flex items-start gap-3">
                                <AlertTriangle className="size-5 shrink-0 text-destructive" aria-hidden="true" />
                                <div className="space-y-1">
                                    <p className="font-medium text-destructive">
                                        {t(bid.status_label_key)}
                                    </p>
                                    {bid.withdrawal_reason && (
                                        <p className="text-sm text-muted-foreground">
                                            <span className="font-medium">
                                                {t('bid.withdrawal_reason_label')}:{' '}
                                            </span>
                                            {bid.withdrawal_reason}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('vendor.summary')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">{t('table.status')}</dt>
                                <dd className="mt-1">
                                    <StatusBadge status={bid.status} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">{t('table.submitted')}</dt>
                                <dd className="mt-1 text-sm">
                                    {bid.submitted_at
                                        ? formatDeadline(bid.submitted_at, locale)
                                        : t('status.not_submitted')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">{t('table.total_amount')}</dt>
                                <dd className="mt-1 text-sm font-semibold">
                                    {/* A draft with nothing entered has no
                                        total; rendering formatMoney(0) asserted
                                        a bid of zero, which is a different
                                        claim from "not priced yet". */}
                                    {bid.total_amount
                                        ? formatMoney(bid.total_amount, currency, locale)
                                        : canEdit && hasAnyPrice
                                          ? formatMoney(grandTotal, currency, locale)
                                          : t('bid.not_priced_yet')}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* Render BOQ + Grand Total + Technical Notes either inside envelope
                    cards (two-envelope) or as their own top-level cards (single).
                    Helpers below avoid duplicating the per-section table markup. */}
                {(() => {
                    // `withHeading` because the single-envelope path already
                    // puts the section title on the card it wraps this in, and
                    // rendered it twice, one line under the other.
                    const renderBoqSectionTable = (section: BoqSection, withHeading = true) => (
                        <div key={section.id} className="space-y-2">
                            {withHeading && (
                                <h3 className="text-base font-semibold">
                                    {localized(locale, section.title, section.title_ar)}
                                </h3>
                            )}
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-start">
                                            <th className="px-3 py-2">{t('table.code')}</th>
                                            <th className="px-3 py-2">{t('table.description')}</th>
                                            <th className="px-3 py-2">{t('table.unit')}</th>
                                            <th className="px-3 py-2 text-end">{t('table.qty')}</th>
                                            <th className="px-3 py-2 text-end">{t('table.unit_price')}</th>
                                            <th className="px-3 py-2 text-end">{t('table.total')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {section.items.map((item) => {
                                            const editEntry = prices[item.id] ?? { unit_price: 0, total_price: 0 };
                                            const readEntry = boqPrices[item.id];
                                            // null, not 0: an item the vendor
                                            // never priced was rendering as
                                            // "0.00", which reads as a bid of
                                            // zero on that line rather than a
                                            // gap. formatMoney renders null as
                                            // an em dash.
                                            const displayUnit = canEdit
                                                ? editEntry.unit_price
                                                : readEntry
                                                  ? Number(readEntry.unit_price)
                                                  : null;
                                            const displayTotal = canEdit
                                                ? editEntry.total_price
                                                : readEntry
                                                  ? Number(readEntry.total_price)
                                                  : null;

                                            return (
                                                <tr key={item.id} className="border-b">
                                                    <td className="px-3 py-2 font-mono text-xs">{item.item_code}</td>
                                                    <td className="px-3 py-2">
                                                        {localized(
                                                            locale,
                                                            item.description_en,
                                                            item.description_ar,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2">{item.unit}</td>
                                                    <td className="px-3 py-2 text-end tabular-nums">
                                                        {formatQuantity(item.quantity, locale)}
                                                    </td>
                                                    <td className="px-3 py-2 text-end">
                                                        {canEdit ? (
                                                            <Input
                                                                type="number"
                                                                min="0"
                                                                step="0.01"
                                                                className="ms-auto w-32 text-right"
                                                                value={editEntry.unit_price || ''}
                                                                onChange={(e) =>
                                                                    handlePriceChange(item.id, item.quantity, e.target.value)
                                                                }
                                                            />
                                                        ) : (
                                                            formatMoney(displayUnit, currency, locale)
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-end font-medium tabular-nums">
                                                        {formatMoney(displayTotal, currency, locale)}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                    <tfoot>
                                        <tr className="font-semibold">
                                            <td colSpan={5} className="px-3 py-2 text-end">
                                                {t('tender.section_subtotal')}
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums">
                                                {formatMoney(sectionTotals[section.id] ?? 0, currency, locale)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    );

                    const grandTotalRow = (
                        <div className="flex items-center justify-between rounded-md bg-muted/50 px-4 py-3">
                            <span className="text-lg font-semibold">{t('tender.grand_total')}</span>
                            <span className="text-lg font-bold tabular-nums">
                                {formatMoney(grandTotal, currency, locale)}
                            </span>
                        </div>
                    );

                    const technicalNotesField = canEdit ? (
                        <div className="space-y-2">
                            <Label htmlFor="technical_notes">{t('tender.technical_notes')}</Label>
                            <textarea
                                id="technical_notes"
                                className="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                placeholder={t('tender.technical_notes_placeholder')}
                                value={technicalNotes}
                                onChange={(e) => setTechnicalNotes(e.target.value)}
                            />
                        </div>
                    ) : bid.technical_notes ? (
                        <div className="space-y-2">
                            <p className="text-sm font-medium">{t('tender.technical_notes')}</p>
                            <p className="text-sm whitespace-pre-line">{bid.technical_notes}</p>
                        </div>
                    ) : null;

                    if (tender.is_two_envelope) {
                        return (
                            <>
                                {/* Technical Envelope. Border colors are hardcoded — project
                                    has no semantic info/success tokens (--color-info etc.) yet.
                                    TODO: migrate to semantic tokens when a design pass adds them. */}
                                <Card className="border-s-4 border-s-blue-500">
                                    <CardHeader>
                                        <CardTitle>{t('bid.envelope.technical_title')}</CardTitle>
                                        <p className="text-sm text-muted-foreground">
                                            {t('bid.envelope.technical_description')}
                                        </p>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <FileUpload
                                            bidId={bid.id}
                                            envelope="technical"
                                            existingFiles={documents.technical}
                                            allowedDocTypes={TECHNICAL_DOC_TYPES}
                                            defaultDocType="technical_proposal"
                                            canEdit={canManageDocuments}
                                            emptyMessage={t('bid.documents.empty_technical')}
                                        />
                                        {technicalNotesField}
                                    </CardContent>
                                </Card>

                                {/* Financial Envelope */}
                                <Card className="border-s-4 border-s-emerald-500">
                                    <CardHeader>
                                        <CardTitle>{t('bid.envelope.financial_title')}</CardTitle>
                                        <p className="text-sm text-muted-foreground">
                                            {t('bid.envelope.financial_description')}
                                        </p>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        {tender.boq_sections.map((section) =>
                                            renderBoqSectionTable(section),
                                        )}
                                        {grandTotalRow}
                                        <FileUpload
                                            bidId={bid.id}
                                            envelope="financial"
                                            existingFiles={documents.financial}
                                            allowedDocTypes={FINANCIAL_DOC_TYPES}
                                            defaultDocType="financial_schedule"
                                            canEdit={canManageDocuments}
                                            emptyMessage={t('bid.documents.empty_financial')}
                                        />
                                    </CardContent>
                                </Card>
                            </>
                        );
                    }

                    // Single-envelope path: BOQ cards (one per section) + Grand Total +
                    // Technical Notes + Documents card. Documents always rendered so the
                    // affordance is visible even when empty.
                    return (
                        <>
                            {tender.boq_sections.map((section) => (
                                <Card key={section.id}>
                                    <CardHeader>
                                        <CardTitle>
                                            {localized(locale, section.title, section.title_ar)}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {renderBoqSectionTable(section, false)}
                                    </CardContent>
                                </Card>
                            ))}
                            <Card>
                                <CardContent className="py-4">{grandTotalRow}</CardContent>
                            </Card>
                            {technicalNotesField && (
                                <Card>
                                    <CardContent className="py-4">{technicalNotesField}</CardContent>
                                </Card>
                            )}
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('bid.documents.section_title')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <FileUpload
                                        bidId={bid.id}
                                        envelope="single"
                                        existingFiles={documents.single}
                                        allowedDocTypes={ALL_DOC_TYPES}
                                        canEdit={canManageDocuments}
                                        emptyMessage={t('bid.documents.empty_single')}
                                    />
                                </CardContent>
                            </Card>
                        </>
                    );
                })()}

                {/* Editable actions */}
                {canEdit && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Button variant="secondary" onClick={saveDraft} disabled={saving}>
                            <Save className="me-1 size-4" />
                            {saving ? t('btn.saving') : t('btn.save_draft')}
                        </Button>

                        {canSubmit && (
                            <Button onClick={() => setShowSubmitConfirm(true)} disabled={submitting}>
                                <SendHorizonal className="me-1 size-4" />
                                {submitting ? t('btn.submitting') : t('btn.submit_bid')}
                            </Button>
                        )}
                    </div>
                )}

                {/* Submit confirm */}
                <ConfirmDialog
                    open={showSubmitConfirm}
                    onOpenChange={setShowSubmitConfirm}
                    title={t('tender.submit_bid_title')}
                    description={t('tender.submit_bid_confirm')}
                    confirmLabel={t('btn.submit')}
                    onConfirm={() => {
                        setShowSubmitConfirm(false);
                        submitBid();
                    }}
                />

                {/* Withdraw dialog (needs a free-text reason — can't reuse ConfirmDialog) */}
                <Dialog open={showWithdrawDialog} onOpenChange={setShowWithdrawDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('vendor.withdraw_bid_title')}</DialogTitle>
                            <DialogDescription>{t('vendor.withdraw_bid_description')}</DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2 py-2">
                            <Label htmlFor="withdraw-reason">{t('form.reason_for_withdrawal')}</Label>
                            <textarea
                                id="withdraw-reason"
                                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                placeholder={t('vendor.withdraw_reason_placeholder')}
                                value={withdrawReason}
                                onChange={(e) => setWithdrawReason(e.target.value)}
                                required
                            />
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setShowWithdrawDialog(false)}>
                                {t('btn.cancel')}
                            </Button>
                            <Button variant="destructive" onClick={withdraw} disabled={withdrawing || !withdrawReason.trim()}>
                                {withdrawing ? t('btn.withdrawing') : t('btn.withdraw')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
