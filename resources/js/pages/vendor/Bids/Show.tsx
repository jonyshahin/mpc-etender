import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Save, SendHorizonal } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { StatusBadge } from '@/components/StatusBadge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

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
        total_amount: string | null;
        currency: string;
        technical_notes: string | null;
        submitted_at: string | null;
        is_sealed: boolean;
        withdrawal_reason: string | null;
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
        boq_sections: BoqSection[];
    };
    boqPrices: Record<string, BoqPriceEntry>;
    canEdit: boolean;
    canSubmit: boolean;
    canWithdraw: boolean;
};

export default function Show({ bid, tender, boqPrices, canEdit, canSubmit, canWithdraw }: Props) {
    const { t } = useTranslation();

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
            <Head title={`Bid ${bid.bid_reference}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/vendor/bids">
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            {t('btn.back_to_my_bids')}
                        </Link>
                    </Button>
                </div>

                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <p className="font-mono text-sm text-muted-foreground">{bid.bid_reference}</p>
                        <Heading title={tender.title_en} />
                        <div className="flex items-center gap-2">
                            <StatusBadge status={bid.status} />
                            <span className="font-mono text-xs text-muted-foreground">{tender.reference_number}</span>
                        </div>
                    </div>

                    {canWithdraw && (
                        <Button variant="destructive" onClick={() => setShowWithdrawDialog(true)}>
                            <AlertTriangle className="mr-1 h-4 w-4" />
                            {t('btn.withdraw_bid')}
                        </Button>
                    )}
                </div>

                {/* Withdrawn / rejected banner */}
                {(bid.status === 'withdrawn' || bid.status === 'rejected') && (
                    <Card className="border-destructive/50 bg-destructive/5">
                        <CardContent className="py-4">
                            <div className="flex items-start gap-3">
                                <AlertTriangle className="h-5 w-5 text-destructive" />
                                <div className="space-y-1">
                                    <p className="font-medium text-destructive">
                                        {bid.status === 'withdrawn' ? t('status.withdrawn') : t('status.rejected')}
                                    </p>
                                    {bid.withdrawal_reason && (
                                        <p className="text-sm text-muted-foreground">{bid.withdrawal_reason}</p>
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
                                        ? new Date(bid.submitted_at).toLocaleString()
                                        : t('status.not_submitted')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">{t('table.total_amount')}</dt>
                                <dd className="mt-1 text-sm font-semibold">
                                    {bid.total_amount
                                        ? `${Number(bid.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })} ${tender.currency}`
                                        : canEdit
                                          ? `${grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })} ${tender.currency}`
                                          : '—'}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* BOQ pricing — editable cells when canEdit, read-only display otherwise */}
                {tender.boq_sections.map((section) => (
                    <Card key={section.id}>
                        <CardHeader>
                            <CardTitle>{section.title}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="px-3 py-2">{t('table.code')}</th>
                                            <th className="px-3 py-2">{t('table.description')}</th>
                                            <th className="px-3 py-2">{t('table.unit')}</th>
                                            <th className="px-3 py-2 text-right">{t('table.qty')}</th>
                                            <th className="px-3 py-2 text-right">{t('table.unit_price')}</th>
                                            <th className="px-3 py-2 text-right">{t('table.total')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {section.items.map((item) => {
                                            const editEntry = prices[item.id] ?? { unit_price: 0, total_price: 0 };
                                            const readEntry = boqPrices[item.id];
                                            const displayUnit = canEdit
                                                ? editEntry.unit_price
                                                : Number(readEntry?.unit_price ?? 0);
                                            const displayTotal = canEdit
                                                ? editEntry.total_price
                                                : Number(readEntry?.total_price ?? 0);

                                            return (
                                                <tr key={item.id} className="border-b">
                                                    <td className="px-3 py-2 font-mono text-xs">{item.item_code}</td>
                                                    <td className="px-3 py-2">{item.description_en}</td>
                                                    <td className="px-3 py-2">{item.unit}</td>
                                                    <td className="px-3 py-2 text-right">
                                                        {Number(item.quantity).toLocaleString()}
                                                    </td>
                                                    <td className="px-3 py-2 text-right">
                                                        {canEdit ? (
                                                            <Input
                                                                type="number"
                                                                min="0"
                                                                step="0.01"
                                                                className="ml-auto w-32 text-right"
                                                                value={editEntry.unit_price || ''}
                                                                onChange={(e) =>
                                                                    handlePriceChange(item.id, item.quantity, e.target.value)
                                                                }
                                                            />
                                                        ) : (
                                                            displayUnit.toLocaleString(undefined, { minimumFractionDigits: 2 })
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-medium">
                                                        {displayTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                    <tfoot>
                                        <tr className="font-semibold">
                                            <td colSpan={5} className="px-3 py-2 text-right">
                                                {t('tender.section_subtotal')}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {(sectionTotals[section.id] ?? 0).toLocaleString(undefined, {
                                                    minimumFractionDigits: 2,
                                                })}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                ))}

                {/* Grand Total */}
                <Card>
                    <CardContent className="flex items-center justify-between py-4">
                        <span className="text-lg font-semibold">{t('tender.grand_total')}</span>
                        <span className="text-lg font-bold">
                            {grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })} {tender.currency}
                        </span>
                    </CardContent>
                </Card>

                {/* Technical Notes */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('tender.technical_notes')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {canEdit ? (
                            <>
                                <Label htmlFor="technical_notes" className="sr-only">
                                    {t('tender.technical_notes')}
                                </Label>
                                <textarea
                                    id="technical_notes"
                                    className="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    placeholder={t('tender.technical_notes_placeholder')}
                                    value={technicalNotes}
                                    onChange={(e) => setTechnicalNotes(e.target.value)}
                                />
                            </>
                        ) : bid.technical_notes ? (
                            <p className="text-sm whitespace-pre-line">{bid.technical_notes}</p>
                        ) : (
                            <p className="text-sm text-muted-foreground">—</p>
                        )}
                    </CardContent>
                </Card>

                {/* Editable actions */}
                {canEdit && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Button variant="secondary" onClick={saveDraft} disabled={saving}>
                            <Save className="mr-1 h-4 w-4" />
                            {saving ? t('btn.saving') : t('btn.save_draft')}
                        </Button>

                        {canSubmit && (
                            <Button onClick={() => setShowSubmitConfirm(true)} disabled={submitting}>
                                <SendHorizonal className="mr-1 h-4 w-4" />
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
