import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Trophy, FileText, Download, Send, BarChart3 } from 'lucide-react';

type RankingRow = {
    bid_id: string;
    vendor_name: string;
    technical_score: number;
    financial_score: number;
    final_score: number;
    rank: number;
};

type Props = {
    tender: {
        id: string;
        reference_number: string;
        title_en: string;
        status: string;
        is_two_envelope: boolean;
        estimated_value: string | null;
        currency: string;
    };
    report: {
        id: string;
        summary: string;
        status: string;
        generated_at: string;
        recommended_bid_id: string | null;
    } | null;
    ranking: RankingRow[];
    criteria: Array<{
        id: string;
        name_en: string;
        envelope: string;
        weight_percentage: string;
        max_score: string;
    }>;
};

export default function Report({ tender, report, ranking, criteria }: Props) {
    const { t } = useTranslation();
    const [confirmApproval, setConfirmApproval] = useState(false);
    const generateForm = useForm({});
    const approvalForm = useForm({});

    const handleGenerateReport = () => {
        generateForm.post(`/tenders/${tender.id}/evaluation-report`, {
            preserveScroll: true,
        });
    };

    const handleSubmitForApproval = () => {
        approvalForm.post(`/tenders/${tender.id}/request-approval`, {
            preserveScroll: true,
        });
        setConfirmApproval(false);
    };

    const maxScore = ranking.length > 0 ? Math.max(...ranking.map((r) => r.final_score)) : 100;

    return (
        <>
            <Head title={`Evaluation Report - ${tender.reference_number}`} />
            <Heading title={t('pages.eval.evaluation_report')} description={`${tender.reference_number} - ${tender.title_en}`} />

            <div className="mt-6 space-y-6">
                {/* Ranking Table */}
                {ranking.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Trophy className="h-5 w-5" />
                                {t('eval.bid_ranking')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="px-4 py-3 text-center font-medium">{t('table.rank')}</th>
                                        <th className="px-4 py-3 text-left font-medium">{t('table.vendor')}</th>
                                        <th className="px-4 py-3 text-center font-medium">{t('table.technical_score')}</th>
                                        {tender.is_two_envelope && (
                                            <th className="px-4 py-3 text-center font-medium">{t('table.financial_score')}</th>
                                        )}
                                        <th className="px-4 py-3 text-center font-medium">{t('table.final_score')}</th>
                                        <th className="px-4 py-3 text-left font-medium">{t('table.score_distribution')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ranking.map((row) => (
                                        <tr
                                            key={row.bid_id}
                                            className={`border-b ${row.rank === 1 ? 'bg-green-50 dark:bg-green-950/20' : ''}`}
                                        >
                                            <td className="px-4 py-3 text-center">
                                                {row.rank === 1 ? (
                                                    <Badge className="bg-green-600 text-white">#{row.rank}</Badge>
                                                ) : (
                                                    <Badge variant="outline">#{row.rank}</Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-medium">
                                                {row.vendor_name}
                                                {report?.recommended_bid_id === row.bid_id && (
                                                    <Badge variant="default" className="ml-2 bg-green-600">
                                                        {t('eval.recommended')}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center">{row.technical_score.toFixed(2)}</td>
                                            {tender.is_two_envelope && (
                                                <td className="px-4 py-3 text-center">
                                                    {row.financial_score.toFixed(2)}
                                                </td>
                                            )}
                                            <td className="px-4 py-3 text-center font-semibold">
                                                {row.final_score.toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="flex-1">
                                                        <div className="flex h-6 overflow-hidden rounded-full bg-muted">
                                                            {tender.is_two_envelope ? (
                                                                <>
                                                                    <div
                                                                        className="bg-blue-500 transition-all"
                                                                        style={{
                                                                            width: `${maxScore > 0 ? (row.technical_score / maxScore) * 50 : 0}%`,
                                                                        }}
                                                                        title={`Technical: ${row.technical_score.toFixed(2)}`}
                                                                    />
                                                                    <div
                                                                        className="bg-emerald-500 transition-all"
                                                                        style={{
                                                                            width: `${maxScore > 0 ? (row.financial_score / maxScore) * 50 : 0}%`,
                                                                        }}
                                                                        title={`Financial: ${row.financial_score.toFixed(2)}`}
                                                                    />
                                                                </>
                                                            ) : (
                                                                <div
                                                                    className="bg-blue-500 transition-all"
                                                                    style={{
                                                                        width: `${maxScore > 0 ? (row.final_score / maxScore) * 100 : 0}%`,
                                                                    }}
                                                                />
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {tender.is_two_envelope && (
                                <div className="mt-4 flex items-center gap-6 text-xs text-muted-foreground">
                                    <div className="flex items-center gap-1">
                                        <div className="h-3 w-3 rounded bg-blue-500" />
                                        {t('table.technical_score')}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <div className="h-3 w-3 rounded bg-emerald-500" />
                                        {t('table.financial_score')}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Report Section */}
                {!report ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <BarChart3 className="h-12 w-12 text-muted-foreground" />
                            <p className="mt-4 text-lg font-medium">{t('empty.no_report')}</p>
                            <p className="mb-6 text-sm text-muted-foreground">
                                {t('empty.no_report_description')}
                            </p>
                            <Button onClick={handleGenerateReport} disabled={generateForm.processing}>
                                <FileText className="mr-2 h-4 w-4" />
                                {t('btn.generate_report')}
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    {t('pages.eval.evaluation_report')}
                                </CardTitle>
                                <Badge variant="outline">{report.status}</Badge>
                            </div>
                            <CardDescription>
                                Generated on {new Date(report.generated_at).toLocaleString()}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="rounded-lg bg-muted/50 p-4">
                                <h3 className="mb-2 font-medium">{t('eval.summary')}</h3>
                                <p className="text-sm text-muted-foreground whitespace-pre-wrap">{report.summary}</p>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button variant="outline" asChild>
                                    <a href={`/tenders/${tender.id}/evaluation-report/pdf`} target="_blank" rel="noopener noreferrer">
                                        <Download className="mr-2 h-4 w-4" />
                                        {t('btn.download_pdf')}
                                    </a>
                                </Button>
                                <Button
                                    onClick={() => setConfirmApproval(true)}
                                    disabled={approvalForm.processing}
                                >
                                    <Send className="mr-2 h-4 w-4" />
                                    {t('btn.submit_for_approval')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <ConfirmDialog
                open={confirmApproval}
                onOpenChange={setConfirmApproval}
                onConfirm={handleSubmitForApproval}
                title={t('eval.submit_for_approval')}
                description={t('eval.submit_for_approval_confirm')}
            />
        </>
    );
}
