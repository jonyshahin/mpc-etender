import { Head, Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import Heading from '@/components/heading';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type BidRow = {
    id: string;
    bid_reference: string;
    status: string;
    submitted_at: string | null;
    total_amount: string | null;
    tender?: { id: string; title_en: string; reference_number: string };
};

type Props = {
    bids: { data: Array<BidRow> };
};

export default function Index({ bids }: Props) {
    const { t } = useTranslation();

    const columns = [
        {
            key: 'bid_reference',
            label: t('table.bid_reference'),
            sortable: true,
            render: (value: string) => <span className="font-mono text-sm">{value}</span>,
        },
        {
            key: 'tender',
            label: t('table.tender'),
            sortable: true,
            render: (_value: any, row: BidRow) =>
                row.tender ? (
                    <div>
                        <p className="text-sm font-medium">{row.tender.title_en}</p>
                        <p className="font-mono text-xs text-muted-foreground">{row.tender.reference_number}</p>
                    </div>
                ) : (
                    <span className="text-muted-foreground">&mdash;</span>
                ),
        },
        {
            key: 'status',
            label: t('table.status'),
            sortable: true,
            render: (value: string) => <StatusBadge status={value} />,
        },
        {
            key: 'total_amount',
            label: t('table.total_amount'),
            sortable: true,
            render: (value: string | null) =>
                value ? (
                    <span className="font-medium">{Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                ) : (
                    <span className="text-muted-foreground">&mdash;</span>
                ),
        },
        {
            key: 'submitted_at',
            label: t('table.submitted'),
            sortable: true,
            render: (value: string | null) =>
                value ? (
                    <span className="text-sm">{new Date(value).toLocaleDateString()}</span>
                ) : (
                    <span className="text-muted-foreground">&mdash;</span>
                ),
        },
        {
            key: 'actions',
            label: '',
            render: (_value: any, row: BidRow) => (
                <Button asChild variant="ghost" size="sm">
                    <Link href={`/vendor/bids/${row.id}`}>
                        <Eye className="mr-1 h-4 w-4" />
                        {t('btn.view')}
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="My Bids" />

            <div className="space-y-6">
                <Heading title={t('pages.vendor.my_bids')} />

                <DataTable columns={columns} data={bids} />
            </div>
        </>
    );
}
