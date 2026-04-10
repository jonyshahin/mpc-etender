import { useState, useMemo } from 'react';
import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Lock, Unlock, Clock } from 'lucide-react';

type Bid = {
    id: string;
    vendor_id: string;
    bid_reference: string;
    status: string;
    total_amount: string | null;
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
};

export default function BidOpening({ tender, bids, authorizers, canOpen, isOpened }: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const form = useForm({ authorizer_id: '' });

    const handleOpenBids = () => {
        form.post(`/tenders/${tender.id}/open-bids`, {
            preserveScroll: true,
        });
        setConfirmOpen(false);
    };

    const countdown = useMemo(() => {
        const opening = new Date(tender.opening_date).getTime();
        const now = Date.now();
        const diff = opening - now;
        if (diff <= 0) return 'Opening time has passed';
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        return `${days}d ${hours}h ${minutes}m remaining`;
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
            label: 'Vendor',
            sortable: true,
            render: (_: any, row: Bid) => row.vendor?.company_name ?? '-',
        },
        { key: 'bid_reference', label: 'Reference', sortable: true },
        {
            key: 'total_amount',
            label: 'Total Amount',
            sortable: true,
            render: (value: string | null) => formatCurrency(value),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (value: string) => <StatusBadge status={value} />,
        },
        {
            key: 'submitted_at',
            label: 'Submitted',
            sortable: true,
            render: (value: string) => new Date(value).toLocaleDateString(),
        },
    ];

    return (
        <>
            <Head title={`Bid Opening - ${tender.reference_number}`} />
            <Heading title="Bid Opening" description={`${tender.reference_number} - ${tender.title_en}`} />

            <div className="mt-6 space-y-6">
                {!isOpened ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Lock className="h-5 w-5" />
                                Sealed Bids
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3 rounded-lg bg-muted p-4">
                                <Lock className="h-8 w-8 text-muted-foreground" />
                                <div>
                                    <p className="text-lg font-semibold">
                                        {sealedCount} bid{sealedCount !== 1 ? 's are' : ' is'} sealed
                                    </p>
                                    <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                        <Clock className="h-4 w-4" />
                                        {countdown}
                                    </p>
                                </div>
                            </div>

                            {canOpen && (
                                <div className="flex items-end gap-4">
                                    <div className="w-64 space-y-2">
                                        <label className="text-sm font-medium">Second Authorizer</label>
                                        <Select
                                            value={form.data.authorizer_id}
                                            onValueChange={(value) => form.setData('authorizer_id', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select authorizer" />
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
                                            <p className="text-sm text-destructive">{form.errors.authorizer_id}</p>
                                        )}
                                    </div>
                                    <Button
                                        onClick={() => setConfirmOpen(true)}
                                        disabled={!form.data.authorizer_id || form.processing}
                                    >
                                        <Unlock className="mr-2 h-4 w-4" />
                                        Open Bids
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Unlock className="h-5 w-5" />
                                Opened Bids
                                <Badge variant="outline" className="ml-2">
                                    {bids.length} bid{bids.length !== 1 ? 's' : ''}
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
                onConfirm={handleOpenBids}
                title="Open Bids"
                description="This action is irreversible and will be logged. All sealed bids will be opened and their contents revealed. Are you sure you want to proceed?"
            />
        </>
    );
}
