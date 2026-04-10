import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Clock, AlertTriangle, ChevronRight, User, DollarSign } from 'lucide-react';

type ApprovalItem = {
    id: string;
    tender_id: string;
    approval_level: number;
    status: string;
    requested_at: string;
    deadline: string;
    value_threshold: string | null;
    tender?: {
        id: string;
        reference_number: string;
        title_en: string;
        estimated_value: string | null;
        currency: string;
    };
    report?: {
        id: string;
        recommended_bid_id: string | null;
        recommended_bid?: {
            id: string;
            vendor?: {
                id: string;
                company_name: string;
            };
        };
    };
};

type Props = {
    approvals: ApprovalItem[];
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

function getDaysUntilDeadline(deadline: string): number {
    const now = new Date();
    const dl = new Date(deadline);
    const diff = dl.getTime() - now.getTime();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

export default function Index({ approvals }: Props) {
    return (
        <>
            <Head title="Pending Approvals" />

            <div className="space-y-6">
                <Heading title="Pending Approvals" />

                {approvals.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Clock className="text-muted-foreground mb-4 h-12 w-12" />
                            <p className="text-muted-foreground text-lg">
                                No pending approvals at this time.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {approvals.map((approval) => {
                            const daysLeft = getDaysUntilDeadline(approval.deadline);
                            const isUrgent = daysLeft < 2;
                            const vendorName =
                                approval.report?.recommended_bid?.vendor?.company_name;

                            return (
                                <Card key={approval.id} className="flex flex-col">
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <CardTitle className="truncate text-base">
                                                    {approval.tender?.reference_number ?? '—'}
                                                </CardTitle>
                                                <CardDescription className="mt-1 line-clamp-2">
                                                    {approval.tender?.title_en ?? 'Untitled Tender'}
                                                </CardDescription>
                                            </div>
                                            <Badge variant={getLevelVariant(approval.approval_level)}>
                                                Level {approval.approval_level}
                                            </Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="flex flex-1 flex-col justify-between gap-4">
                                        <div className="space-y-3">
                                            {vendorName && (
                                                <div className="flex items-center gap-2 text-sm">
                                                    <User className="text-muted-foreground h-4 w-4 shrink-0" />
                                                    <span className="truncate">
                                                        <span className="text-muted-foreground">
                                                            Recommended:{' '}
                                                        </span>
                                                        <span className="font-medium">
                                                            {vendorName}
                                                        </span>
                                                    </span>
                                                </div>
                                            )}

                                            <div className="flex items-center gap-2 text-sm">
                                                <DollarSign className="text-muted-foreground h-4 w-4 shrink-0" />
                                                <span>
                                                    <span className="text-muted-foreground">
                                                        Est. Value:{' '}
                                                    </span>
                                                    <span className="font-medium">
                                                        {formatCurrency(
                                                            approval.tender?.estimated_value,
                                                            approval.tender?.currency ?? 'USD',
                                                        )}
                                                    </span>
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-2 text-sm">
                                                <Clock className="text-muted-foreground h-4 w-4 shrink-0" />
                                                <span className="text-muted-foreground">
                                                    Requested:{' '}
                                                    {new Date(
                                                        approval.requested_at,
                                                    ).toLocaleDateString()}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-2 text-sm">
                                                {isUrgent ? (
                                                    <AlertTriangle className="h-4 w-4 shrink-0 text-red-500" />
                                                ) : (
                                                    <Clock className="text-muted-foreground h-4 w-4 shrink-0" />
                                                )}
                                                <span
                                                    className={
                                                        isUrgent
                                                            ? 'font-semibold text-red-600'
                                                            : 'text-muted-foreground'
                                                    }
                                                >
                                                    Deadline:{' '}
                                                    {new Date(
                                                        approval.deadline,
                                                    ).toLocaleDateString()}
                                                    {isUrgent && daysLeft <= 0
                                                        ? ' (Overdue)'
                                                        : isUrgent
                                                          ? ` (${daysLeft}d left)`
                                                          : ''}
                                                </span>
                                            </div>
                                        </div>

                                        <Button asChild className="w-full">
                                            <Link href={`/approvals/${approval.id}`}>
                                                Review
                                                <ChevronRight className="ml-1 h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}
