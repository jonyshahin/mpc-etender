import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save, SendHorizonal } from 'lucide-react';
import { useState, useMemo, useCallback } from 'react';
import Heading from '@/components/heading';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type BoqItem = {
    id: string;
    item_code: string;
    description_en: string;
    unit: string;
    quantity: string;
};

type BoqSection = {
    id: string;
    title: string;
    items: BoqItem[];
};

type Props = {
    tender: {
        id: string;
        reference_number: string;
        title_en: string;
        currency: string;
        is_two_envelope: boolean;
    };
    bid: {
        id: string;
        status: string;
        technical_notes: string | null;
    };
    boqSections: BoqSection[];
};

type PriceEntry = {
    unit_price: number;
    total_price: number;
};

export default function Create({ tender, bid, boqSections }: Props) {
    const { t } = useTranslation();
    const [prices, setPrices] = useState<Record<string, PriceEntry>>(() => {
        const initial: Record<string, PriceEntry> = {};
        boqSections.forEach((section) => {
            section.items.forEach((item) => {
                initial[item.id] = { unit_price: 0, total_price: 0 };
            });
        });
        return initial;
    });

    const [technicalNotes, setTechnicalNotes] = useState(bid.technical_notes ?? '');
    const [saving, setSaving] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [showSubmitConfirm, setShowSubmitConfirm] = useState(false);

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
        boqSections.forEach((section) => {
            totals[section.id] = section.items.reduce(
                (sum, item) => sum + (prices[item.id]?.total_price ?? 0),
                0
            );
        });
        return totals;
    }, [prices, boqSections]);

    const grandTotal = useMemo(
        () => Object.values(sectionTotals).reduce((sum, val) => sum + val, 0),
        [sectionTotals]
    );

    function buildPayload() {
        const boqPrices = Object.entries(prices).map(([boq_item_id, entry]) => ({
            boq_item_id,
            unit_price: entry.unit_price,
            total_price: entry.total_price,
        }));
        return { boq_prices: boqPrices, technical_notes: technicalNotes };
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

    return (
        <>
            <Head title={`Bid - ${tender.reference_number}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={`/vendor/tenders/${tender.id}`}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            {t('btn.back_to_tender')}
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="font-mono text-sm text-muted-foreground">{tender.reference_number}</p>
                        <Heading title={tender.title_en} />
                    </div>
                    <p className="text-sm text-muted-foreground">{t('tender.currency')}: {tender.currency}</p>
                </div>

                {/* BOQ Pricing Sections */}
                {boqSections.map((section) => (
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
                                        {section.items.map((item) => (
                                            <tr key={item.id} className="border-b">
                                                <td className="px-3 py-2 font-mono text-xs">{item.item_code}</td>
                                                <td className="px-3 py-2">{item.description_en}</td>
                                                <td className="px-3 py-2">{item.unit}</td>
                                                <td className="px-3 py-2 text-right">
                                                    {Number(item.quantity).toLocaleString()}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        className="ml-auto w-32 text-right"
                                                        value={prices[item.id]?.unit_price || ''}
                                                        onChange={(e) =>
                                                            handlePriceChange(item.id, item.quantity, e.target.value)
                                                        }
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-right font-medium">
                                                    {(prices[item.id]?.total_price ?? 0).toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2,
                                                    })}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="font-semibold">
                                            <td colSpan={5} className="px-3 py-2 text-right">
                                                {t('tender.section_subtotal')}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {(sectionTotals[section.id] ?? 0).toLocaleString(undefined, {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
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
                            {grandTotal.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })}{' '}
                            {tender.currency}
                        </span>
                    </CardContent>
                </Card>

                {/* Technical Notes */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('tender.technical_notes')}</CardTitle>
                    </CardHeader>
                    <CardContent>
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
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex flex-wrap items-center gap-3">
                    <Button asChild variant="outline">
                        <Link href={`/vendor/tenders/${tender.id}`}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            {t('btn.back')}
                        </Link>
                    </Button>

                    <Button variant="secondary" onClick={saveDraft} disabled={saving}>
                        <Save className="mr-1 h-4 w-4" />
                        {saving ? t('btn.saving') : t('btn.save_draft')}
                    </Button>

                    <Button onClick={() => setShowSubmitConfirm(true)} disabled={submitting}>
                        <SendHorizonal className="mr-1 h-4 w-4" />
                        {submitting ? t('btn.submitting') : t('btn.submit_bid')}
                    </Button>
                </div>

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
            </div>
        </>
    );
}
