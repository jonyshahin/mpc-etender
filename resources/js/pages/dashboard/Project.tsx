import { Head, Link } from '@inertiajs/react';
import { FileText, BarChart3, Trophy, Inbox, DollarSign, Eye } from 'lucide-react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    project: { id: string; name: string; code: string; status: string };
    overview: {
        total_tenders: number;
        active_tenders: number;
        awarded_tenders: number;
        total_bids: number;
        total_award_value: number;
        tender_pipeline: Record<string, number>;
    };
    tenders: Array<{
        id: string;
        reference_number: string;
        title_en: string;
        status: string;
        submission_deadline: string | null;
        created_at: string;
        bids_count: number;
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

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const pipelineColors: Record<string, string> = {
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

export default function Project({ project, overview, tenders }: Props) {
    const { t } = useTranslation();
    const pipelineEntries = Object.entries(overview.tender_pipeline);
    const pipelineTotal = pipelineEntries.reduce((sum, [, count]) => sum + count, 0);

    return (
        <>
            <Head title={`${project.name} Dashboard`} />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <Heading title={project.name} />
                    <Badge variant="secondary" className="text-xs">
                        {project.code}
                    </Badge>
                    <Badge variant="outline" className="text-xs capitalize">
                        {project.status}
                    </Badge>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.total_tenders')}
                            </CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.total_tenders}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.active_tenders')}
                            </CardTitle>
                            <BarChart3 className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">{overview.active_tenders}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('status.awarded')}
                            </CardTitle>
                            <Trophy className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-600">
                                {overview.awarded_tenders}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('dashboard.total_bids_label')}
                            </CardTitle>
                            <Inbox className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.total_bids}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Award Value */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            {t('dashboard.total_award_value')}
                        </CardTitle>
                        <DollarSign className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold">{formatCurrency(overview.total_award_value)}</div>
                    </CardContent>
                </Card>

                {/* Tender Pipeline */}
                {pipelineTotal > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('dashboard.tender_pipeline')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-8 rounded-full overflow-hidden mb-4">
                                {pipelineEntries.map(([status, count]) => (
                                    <div
                                        key={status}
                                        className={`${pipelineColors[status] || 'bg-gray-400'} flex items-center justify-center text-xs text-white font-medium transition-all`}
                                        style={{ width: `${(count / pipelineTotal) * 100}%` }}
                                        title={`${status}: ${count}`}
                                    >
                                        {count > 0 && (count / pipelineTotal) * 100 > 8 ? count : ''}
                                    </div>
                                ))}
                            </div>
                            <div className="flex flex-wrap gap-4">
                                {pipelineEntries.map(([status, count]) => (
                                    <div key={status} className="flex items-center gap-2 text-sm">
                                        <div
                                            className={`h-3 w-3 rounded-full ${pipelineColors[status] || 'bg-gray-400'}`}
                                        />
                                        <span className="capitalize text-muted-foreground">
                                            {t(`status.${status}`)}
                                        </span>
                                        <span className="font-medium">{count}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Tenders Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('dashboard.tenders')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tenders.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-2 font-medium text-muted-foreground">
                                                {t('table.reference')}
                                            </th>
                                            <th className="text-left py-2 font-medium text-muted-foreground">
                                                {t('table.title')}
                                            </th>
                                            <th className="text-left py-2 font-medium text-muted-foreground">
                                                {t('table.status')}
                                            </th>
                                            <th className="text-left py-2 font-medium text-muted-foreground">
                                                {t('table.deadline')}
                                            </th>
                                            <th className="text-center py-2 font-medium text-muted-foreground">
                                                {t('table.bids')}
                                            </th>
                                            <th className="text-right py-2 font-medium text-muted-foreground">
                                                {t('table.actions')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tenders.map((tender) => (
                                            <tr key={tender.id} className="border-b last:border-0 hover:bg-muted/50">
                                                <td className="py-3 font-mono text-xs">{tender.reference_number}</td>
                                                <td className="py-3 font-medium max-w-xs truncate">
                                                    {tender.title_en}
                                                </td>
                                                <td className="py-3">
                                                    <Badge
                                                        variant="secondary"
                                                        className={`text-xs ${statusBadgeColors[tender.status] || ''}`}
                                                    >
                                                        {t(`status.${tender.status}`)}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 text-muted-foreground">
                                                    {formatDate(tender.submission_deadline)}
                                                </td>
                                                <td className="py-3 text-center">{tender.bids_count}</td>
                                                <td className="py-3 text-right">
                                                    <Button asChild variant="ghost" size="sm" className="gap-1">
                                                        <Link
                                                            href={`/projects/${project.id}/tenders/${tender.id}`}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                            {t('btn.view')}
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground text-center py-8">
                                {t('empty.no_tenders_for_project')}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
