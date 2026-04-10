import { Head, Link } from '@inertiajs/react';
import {
    Clock,
    Users,
    FileText,
    Building2,
    TrendingDown,
    BarChart3,
    AlertCircle,
    ArrowRight,
    DollarSign,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    overview: {
        total_projects: number;
        active_projects: number;
        total_tenders: number;
        published_tenders: number;
        awarded_tenders: number;
        total_vendors: number;
        qualified_vendors: number;
        total_bids: number;
        total_spend: number;
        spend_by_project: Array<{ name: string; total: number }>;
        tender_status_distribution: Record<string, number>;
        monthly_spend: Array<{ month: string; total: number }>;
    };
    kpis: {
        avg_cycle_time_days: number;
        avg_bids_per_tender: number;
        savings_rate_percent: number;
        total_estimated: number;
        total_awarded: number;
    };
    pendingApprovals: number;
    recentTenders: Array<{
        id: string;
        project_id: string;
        reference_number: string;
        title_en: string;
        status: string;
        created_at: string;
        project?: { id: string; name: string; code: string };
    }>;
};

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const statusColors: Record<string, string> = {
    draft: 'bg-gray-400',
    published: 'bg-blue-500',
    open: 'bg-green-500',
    closed: 'bg-yellow-500',
    under_evaluation: 'bg-purple-500',
    awarded: 'bg-emerald-600',
    cancelled: 'bg-red-500',
};

const statusBadgeColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    published: 'bg-blue-100 text-blue-800',
    open: 'bg-green-100 text-green-800',
    closed: 'bg-yellow-100 text-yellow-800',
    under_evaluation: 'bg-purple-100 text-purple-800',
    awarded: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-red-100 text-red-800',
};

export default function Portfolio({ overview, kpis, pendingApprovals, recentTenders }: Props) {
    const statusEntries = Object.entries(overview.tender_status_distribution);
    const statusTotal = statusEntries.reduce((sum, [, count]) => sum + count, 0);

    const maxMonthlySpend = Math.max(...overview.monthly_spend.map((m) => m.total), 1);

    const maxProjectSpend = Math.max(...overview.spend_by_project.map((p) => p.total), 1);

    return (
        <>
            <Head title="Portfolio Dashboard" />

            <div className="space-y-6">
                <Heading title="Portfolio Dashboard" />

                {/* KPI Row */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Avg Cycle Time
                            </CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{kpis.avg_cycle_time_days} days</div>
                            <CardDescription>From publish to award</CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Avg Bids / Tender
                            </CardTitle>
                            <BarChart3 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{kpis.avg_bids_per_tender.toFixed(1)}</div>
                            <CardDescription>Competition level</CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Savings Rate
                            </CardTitle>
                            <TrendingDown className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {kpis.savings_rate_percent.toFixed(1)}%
                            </div>
                            <CardDescription>
                                {formatCurrency(kpis.total_estimated)} est. vs {formatCurrency(kpis.total_awarded)}{' '}
                                awarded
                            </CardDescription>
                        </CardContent>
                    </Card>
                </div>

                {/* Summary Row */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Projects
                            </CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.total_projects}</div>
                            <CardDescription>{overview.active_projects} active</CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Tenders
                            </CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.total_tenders}</div>
                            <CardDescription>
                                {overview.published_tenders} published, {overview.awarded_tenders} awarded
                            </CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Vendors
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.total_vendors}</div>
                            <CardDescription>{overview.qualified_vendors} qualified</CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Spend
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(overview.total_spend)}</div>
                            <CardDescription>{overview.total_bids} total bids</CardDescription>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {/* Tender Status Distribution */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Tender Status Distribution</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {statusTotal > 0 ? (
                                <>
                                    <div className="flex h-6 rounded-full overflow-hidden mb-4">
                                        {statusEntries.map(([status, count]) => (
                                            <div
                                                key={status}
                                                className={`${statusColors[status] || 'bg-gray-400'} transition-all`}
                                                style={{ width: `${(count / statusTotal) * 100}%` }}
                                                title={`${status}: ${count}`}
                                            />
                                        ))}
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        {statusEntries.map(([status, count]) => (
                                            <div key={status} className="flex items-center gap-2 text-sm">
                                                <div
                                                    className={`h-3 w-3 rounded-full ${statusColors[status] || 'bg-gray-400'}`}
                                                />
                                                <span className="capitalize text-muted-foreground">
                                                    {status.replace(/_/g, ' ')}
                                                </span>
                                                <span className="font-medium ml-auto">{count}</span>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    No tender data available
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Monthly Spend */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Monthly Spend</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {overview.monthly_spend.length > 0 ? (
                                <div className="space-y-3">
                                    {overview.monthly_spend.map((m) => (
                                        <div key={m.month} className="flex items-center gap-3">
                                            <span className="text-sm text-muted-foreground w-16 shrink-0">
                                                {m.month}
                                            </span>
                                            <div className="flex-1 h-5 bg-muted rounded-full overflow-hidden">
                                                <div
                                                    className="h-full bg-blue-500 rounded-full transition-all"
                                                    style={{
                                                        width: `${(m.total / maxMonthlySpend) * 100}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-sm font-medium w-24 text-right shrink-0">
                                                {formatCurrency(m.total)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    No spend data available
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Spend by Project */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Spend by Project</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {overview.spend_by_project.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-2 font-medium text-muted-foreground">
                                                Project
                                            </th>
                                            <th className="text-left py-2 font-medium text-muted-foreground w-1/2">
                                                Spend
                                            </th>
                                            <th className="text-right py-2 font-medium text-muted-foreground">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {overview.spend_by_project.map((p) => (
                                            <tr key={p.name} className="border-b last:border-0">
                                                <td className="py-2 font-medium">{p.name}</td>
                                                <td className="py-2">
                                                    <div className="h-4 bg-muted rounded-full overflow-hidden">
                                                        <div
                                                            className="h-full bg-emerald-500 rounded-full transition-all"
                                                            style={{
                                                                width: `${(p.total / maxProjectSpend) * 100}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </td>
                                                <td className="py-2 text-right font-medium">
                                                    {formatCurrency(p.total)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground text-center py-4">
                                No project spend data available
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Recent Tenders & Pending Approvals */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Recent Tenders</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {recentTenders.length > 0 ? (
                                    <div className="space-y-3">
                                        {recentTenders.map((t) => (
                                            <div
                                                key={t.id}
                                                className="flex items-center justify-between gap-4 py-2 border-b last:border-0"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <span className="text-xs font-mono text-muted-foreground">
                                                            {t.reference_number}
                                                        </span>
                                                        <Badge
                                                            variant="secondary"
                                                            className={`text-xs ${statusBadgeColors[t.status] || ''}`}
                                                        >
                                                            {t.status.replace(/_/g, ' ')}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm font-medium truncate">{t.title_en}</p>
                                                    {t.project && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {t.project.code} - {t.project.name}
                                                        </p>
                                                    )}
                                                </div>
                                                <span className="text-xs text-muted-foreground shrink-0">
                                                    {formatDate(t.created_at)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground text-center py-4">
                                        No recent tenders
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">Pending Approvals</CardTitle>
                            <AlertCircle className="h-5 w-5 text-yellow-500" />
                        </CardHeader>
                        <CardContent className="flex flex-col items-center justify-center py-8">
                            <div className="text-4xl font-bold mb-2">{pendingApprovals}</div>
                            <p className="text-sm text-muted-foreground mb-4">
                                {pendingApprovals === 1 ? 'item' : 'items'} awaiting your review
                            </p>
                            <Button asChild variant="outline" className="gap-2">
                                <Link href="/approvals">
                                    View Approvals
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
