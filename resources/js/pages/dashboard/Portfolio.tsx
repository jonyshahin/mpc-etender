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
import { useTranslation } from '@/hooks/use-translation';
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
    const { t } = useTranslation();
    const statusEntries = Object.entries(overview.tender_status_distribution);
    const statusTotal = statusEntries.reduce((sum, [, count]) => sum + count, 0);

    const maxMonthlySpend = Math.max(...overview.monthly_spend.map((m) => m.total), 1);

    const maxProjectSpend = Math.max(...overview.spend_by_project.map((p) => p.total), 1);

    return (
        <>
            <Head title="Portfolio Dashboard" />

            <div className="space-y-6">
                <Heading title={t('pages.portfolio.title')} />

                {/* KPI Row */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.avg_cycle_time')}
                            </CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{kpis.avg_cycle_time_days} {t('dashboard.days')}</div>
                            <CardDescription>{t('dashboard.from_publish_to_award')}</CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.avg_bids_per_tender')}
                            </CardTitle>
                            <BarChart3 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{kpis.avg_bids_per_tender.toFixed(1)}</div>
                            <CardDescription>{t('dashboard.competition_level')}</CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.savings_rate')}
                            </CardTitle>
                            <TrendingDown className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {kpis.savings_rate_percent.toFixed(1)}%
                            </div>
                            <CardDescription>
                                {formatCurrency(kpis.total_estimated)} {t('dashboard.est_vs')} {formatCurrency(kpis.total_awarded)}{' '}
                                {t('dashboard.awarded')}
                            </CardDescription>
                        </CardContent>
                    </Card>
                </div>

                {/* Summary Row */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.projects')}
                            </CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.total_projects}</div>
                            <CardDescription>{overview.active_projects} {t('status.active')}</CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.tenders')}
                            </CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.total_tenders}</div>
                            <CardDescription>
                                {overview.published_tenders} {t('status.published')}, {overview.awarded_tenders} {t('status.awarded')}
                            </CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.vendors')}
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.total_vendors}</div>
                            <CardDescription>{overview.qualified_vendors} {t('status.qualified')}</CardDescription>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.total_spend')}
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(overview.total_spend)}</div>
                            <CardDescription>{overview.total_bids} {t('dashboard.total_bids')}</CardDescription>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {/* Tender Status Distribution */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('dashboard.tender_status_distribution')}</CardTitle>
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
                                                    {t(`status.${status}`)}
                                                </span>
                                                <span className="font-medium ml-auto">{count}</span>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    {t('empty.no_tender_data')}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Monthly Spend */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('dashboard.monthly_spend')}</CardTitle>
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
                                    {t('empty.no_spend_data')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Spend by Project */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('dashboard.spend_by_project')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {overview.spend_by_project.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-2 font-medium text-muted-foreground">
                                                {t('table.project')}
                                            </th>
                                            <th className="text-left py-2 font-medium text-muted-foreground w-1/2">
                                                {t('table.spend')}
                                            </th>
                                            <th className="text-right py-2 font-medium text-muted-foreground">
                                                {t('table.total')}
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
                                {t('empty.no_project_spend_data')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Recent Tenders & Pending Approvals */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('dashboard.recent_tenders')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {recentTenders.length > 0 ? (
                                    <div className="space-y-3">
                                        {recentTenders.map((tender) => (
                                            <div
                                                key={tender.id}
                                                className="flex items-center justify-between gap-4 py-2 border-b last:border-0"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <span className="text-xs font-mono text-muted-foreground">
                                                            {tender.reference_number}
                                                        </span>
                                                        <Badge
                                                            variant="secondary"
                                                            className={`text-xs ${statusBadgeColors[tender.status] || ''}`}
                                                        >
                                                            {t(`status.${tender.status}`)}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm font-medium truncate">{tender.title_en}</p>
                                                    {tender.project && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {tender.project.code} - {tender.project.name}
                                                        </p>
                                                    )}
                                                </div>
                                                <span className="text-xs text-muted-foreground shrink-0">
                                                    {formatDate(tender.created_at)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground text-center py-4">
                                        {t('empty.no_recent_tenders')}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">{t('dashboard.pending_approvals')}</CardTitle>
                            <AlertCircle className="h-5 w-5 text-yellow-500" />
                        </CardHeader>
                        <CardContent className="flex flex-col items-center justify-center py-8">
                            <div className="text-4xl font-bold mb-2">{pendingApprovals}</div>
                            <p className="text-sm text-muted-foreground mb-4">
                                {pendingApprovals === 1 ? t('dashboard.item') : t('dashboard.items')} {t('dashboard.awaiting_review')}
                            </p>
                            <Button asChild variant="outline" className="gap-2">
                                <Link href="/approvals">
                                    {t('btn.view_approvals')}
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
