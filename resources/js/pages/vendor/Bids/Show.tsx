import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

type Props = {
    bid: {
        id: string;
        bid_reference: string;
        status: string;
        total_amount: string | null;
        technical_notes: string | null;
        submitted_at: string | null;
        is_sealed: boolean;
        tender?: { id: string; title_en: string; reference_number: string };
        boq_prices?: Array<{
            id: string;
            boq_item_id: string;
            unit_price: string;
            total_price: string;
            boq_item?: {
                item_code: string;
                description_en: string;
                unit: string;
                quantity: string;
            };
        }>;
    };
    canWithdraw: boolean;
};

export default function Show({ bid, canWithdraw }: Props) {
    const [showWithdraw, setShowWithdraw] = useState(false);
    const [withdrawReason, setWithdrawReason] = useState('');
    const [withdrawing, setWithdrawing] = useState(false);

    function handleWithdraw() {
        setWithdrawing(true);
        router.post(
            `/vendor/bids/${bid.id}/withdraw`,
            { reason: withdrawReason },
            {
                onFinish: () => {
                    setWithdrawing(false);
                    setShowWithdraw(false);
                },
            }
        );
    }

    const grandTotal = bid.boq_prices
        ? bid.boq_prices.reduce((sum, p) => sum + Number(p.total_price), 0)
        : null;

    return (
        <>
            <Head title={`Bid ${bid.bid_reference}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/vendor/bids">
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to My Bids
                        </Link>
                    </Button>
                </div>

                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <p className="font-mono text-sm text-muted-foreground">{bid.bid_reference}</p>
                        <Heading title={bid.tender ? bid.tender.title_en : 'Bid Details'} />
                        <div className="flex items-center gap-2">
                            <StatusBadge status={bid.status} />
                            {bid.tender && (
                                <span className="font-mono text-xs text-muted-foreground">
                                    {bid.tender.reference_number}
                                </span>
                            )}
                        </div>
                    </div>

                    {canWithdraw && (
                        <Button
                            variant="destructive"
                            onClick={() => setShowWithdraw(true)}
                        >
                            <AlertTriangle className="mr-1 h-4 w-4" />
                            Withdraw Bid
                        </Button>
                    )}
                </div>

                {/* Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Status</dt>
                                <dd className="mt-1">
                                    <StatusBadge status={bid.status} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Submitted</dt>
                                <dd className="mt-1 text-sm">
                                    {bid.submitted_at
                                        ? new Date(bid.submitted_at).toLocaleString()
                                        : 'Not submitted'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Total Amount</dt>
                                <dd className="mt-1 text-sm font-semibold">
                                    {bid.total_amount
                                        ? Number(bid.total_amount).toLocaleString(undefined, {
                                              minimumFractionDigits: 2,
                                          })
                                        : bid.is_sealed
                                          ? 'Sealed'
                                          : '\u2014'}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* BOQ Pricing */}
                {bid.boq_prices && bid.boq_prices.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>BOQ Pricing</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="px-3 py-2">Code</th>
                                            <th className="px-3 py-2">Description</th>
                                            <th className="px-3 py-2">Unit</th>
                                            <th className="px-3 py-2 text-right">Qty</th>
                                            <th className="px-3 py-2 text-right">Unit Price</th>
                                            <th className="px-3 py-2 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {bid.boq_prices.map((price) => (
                                            <tr key={price.id} className="border-b">
                                                <td className="px-3 py-2 font-mono text-xs">
                                                    {price.boq_item?.item_code ?? '\u2014'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {price.boq_item?.description_en ?? '\u2014'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {price.boq_item?.unit ?? '\u2014'}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {price.boq_item
                                                        ? Number(price.boq_item.quantity).toLocaleString()
                                                        : '\u2014'}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {Number(price.unit_price).toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                    })}
                                                </td>
                                                <td className="px-3 py-2 text-right font-medium">
                                                    {Number(price.total_price).toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                    })}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    {grandTotal !== null && (
                                        <tfoot>
                                            <tr className="font-semibold">
                                                <td colSpan={5} className="px-3 py-2 text-right">
                                                    Grand Total
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {grandTotal.toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                    })}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    )}
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Technical Notes */}
                {bid.technical_notes && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Technical Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-line">{bid.technical_notes}</p>
                        </CardContent>
                    </Card>
                )}

                {/* Withdraw Dialog */}
                <Dialog open={showWithdraw} onOpenChange={setShowWithdraw}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Withdraw Bid</DialogTitle>
                            <DialogDescription>This action cannot be undone. Please provide a reason for withdrawal.</DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2 py-2">
                            <Label htmlFor="withdraw-reason">Reason for Withdrawal</Label>
                            <textarea
                                id="withdraw-reason"
                                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                placeholder="Please explain why you are withdrawing this bid..."
                                value={withdrawReason}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setWithdrawReason(e.target.value)}
                                required
                            />
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setShowWithdraw(false)}>Cancel</Button>
                            <Button variant="destructive" onClick={handleWithdraw} disabled={withdrawing || !withdrawReason.trim()}>
                                {withdrawing ? 'Withdrawing...' : 'Withdraw'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
