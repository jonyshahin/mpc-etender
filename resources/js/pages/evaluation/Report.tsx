import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BarChart3, Download, FileText, Send, Trophy } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/datetime';
import { cn } from '@/lib/utils';

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
};

export default function Report({ tender, report, ranking }: Props) {
    const { t, locale } = useTranslation();
    const [confirmApproval, setConfirmApproval] = useState(false);
    const generateForm = useForm({});
    const approvalForm = useForm({});

    const handleGenerateReport = () => {
        generateForm.post(`/tenders/${tender.id}/evaluation-report`, { preserveScroll: true });
    };

    const handleSubmitForApproval = () => {
        approvalForm.post(`/tenders/${tender.id}/request-approval`, { preserveScroll: true });
        setConfirmApproval(false);
    };

    const ordered = [...ranking].sort((a, b) => a.rank - b.rank);
    const leader = ordered[0];
    const maxScore = ordered.reduce((max, row) => Math.max(max, row.final_score), 0);

    /**
     * A single bidder gets the number, not a chart.
     *
     * One bar encodes nothing — there is nothing to compare it against — and a
     * lone bar reads as a chart that failed to load.
     */
    const showChart = ordered.length > 1;

    const barWidth = (value: number) => (maxScore > 0 ? (value / maxScore) * 100 : 0);

    const score = (value: number) => value.toFixed(2);

    return (
        <>
            <Head title={`${t('pages.eval.evaluation_report')} — ${tender.reference_number}`} />

            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <Link
                        href={`/tenders/${tender.id}`}
                        className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4 rtl:rotate-180" aria-hidden="true" />
                        {tender.reference_number}
                    </Link>
                    <h1 className="mt-2 text-2xl font-semibold tracking-tight">
                        {t('pages.eval.evaluation_report')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">{tender.title_en}</p>
                </div>

                {ordered.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-14 text-center">
                            <BarChart3 className="size-10 text-muted-foreground" aria-hidden="true" />
                            <p className="mt-4 text-lg font-medium">{t('empty.no_ranking')}</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t('empty.no_ranking_description')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {/* The headline the award is read off. A hero number, not a
                            chart: one value with no comparison IS the visualisation. */}
                        <Card>
                            <CardContent className="flex flex-wrap items-end justify-between gap-4 p-6">
                                <div className="min-w-0">
                                    <p className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        <Trophy className="size-4" aria-hidden="true" />
                                        {t('eval.highest_ranked')}
                                    </p>
                                    <p className="mt-2 truncate text-2xl font-semibold tracking-tight">
                                        {leader.vendor_name}
                                    </p>
                                    {report?.recommended_bid_id === leader.bid_id && (
                                        <Badge className="mt-2 border-transparent bg-emerald-600 text-white">
                                            {t('eval.recommended')}
                                        </Badge>
                                    )}
                                </div>
                                <div className="text-end">
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {t('table.final_score')}
                                    </p>
                                    <p className="text-3xl font-semibold tabular-nums tracking-tight">
                                        {score(leader.final_score)}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {showChart && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('eval.bid_ranking')}</CardTitle>
                                    <CardDescription>{t('eval.ranked_by_final_score')}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {/* One measure, so one hue and no legend — the title
                                        names the series. Identity is carried by the row
                                        label and the rank, never by colour.

                                        #2a78d6 / #3987e5 are the validated slot-1 steps:
                                        both clear the lightness band, chroma floor and
                                        3:1 contrast against their own surface. */}
                                    <ul className="space-y-4">
                                        {ordered.map((row) => {
                                            const isLeader = row.rank === 1;

                                            return (
                                                <li key={row.bid_id}>
                                                    <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                                        <span className="flex min-w-0 items-baseline gap-2">
                                                            <span className="text-sm tabular-nums text-muted-foreground">
                                                                #{row.rank}
                                                            </span>
                                                            <span
                                                                className={cn(
                                                                    'truncate',
                                                                    isLeader ? 'font-semibold' : 'font-medium',
                                                                )}
                                                            >
                                                                {row.vendor_name}
                                                            </span>
                                                            {report?.recommended_bid_id === row.bid_id && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="shrink-0 font-normal"
                                                                >
                                                                    {t('eval.recommended')}
                                                                </Badge>
                                                            )}
                                                        </span>
                                                        {/* Values wear text ink, not the series
                                                            colour. */}
                                                        <span className="text-sm font-medium tabular-nums">
                                                            {score(row.final_score)}
                                                        </span>
                                                    </div>

                                                    <div
                                                        className="mt-1.5 h-5 w-full overflow-hidden rounded-[2px] bg-muted/50"
                                                        role="img"
                                                        aria-label={`${row.vendor_name}: ${score(row.final_score)}`}
                                                    >
                                                        {/* Square at the baseline, 4px rounded at
                                                            the data end — logical, so it flips
                                                            under rtl. The split rides in the
                                                            tooltip: it is available, not
                                                            competing with the ranking. */}
                                                        <div
                                                            className="h-full rounded-e-[4px] bg-[#2a78d6] transition-[width] dark:bg-[#3987e5]"
                                                            style={{ width: `${barWidth(row.final_score)}%` }}
                                                            title={
                                                                tender.is_two_envelope
                                                                    ? `${t('table.technical_score')}: ${score(row.technical_score)} · ${t('table.financial_score')}: ${score(row.financial_score)}`
                                                                    : `${t('table.final_score')}: ${score(row.final_score)}`
                                                            }
                                                        />
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}

                        {/* The table is the accessible counterpart to the chart, and
                            the only place the technical/financial split is spelled
                            out rather than hovered for. */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('eval.score_breakdown')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-lg text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="px-4 py-3 text-center font-medium">
                                                    {t('table.rank')}
                                                </th>
                                                <th className="px-4 py-3 text-start font-medium">
                                                    {t('table.vendor')}
                                                </th>
                                                <th className="px-4 py-3 text-end font-medium">
                                                    {t('table.technical_score')}
                                                </th>
                                                {tender.is_two_envelope && (
                                                    <th className="px-4 py-3 text-end font-medium">
                                                        {t('table.financial_score')}
                                                    </th>
                                                )}
                                                <th className="px-4 py-3 text-end font-medium">
                                                    {t('table.final_score')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {ordered.map((row) => (
                                                <tr key={row.bid_id} className="border-b last:border-0">
                                                    <td className="px-4 py-3 text-center tabular-nums">
                                                        {row.rank}
                                                    </td>
                                                    <td className="px-4 py-3 font-medium">
                                                        {row.vendor_name}
                                                    </td>
                                                    <td className="px-4 py-3 text-end tabular-nums">
                                                        {score(row.technical_score)}
                                                    </td>
                                                    {tender.is_two_envelope && (
                                                        <td className="px-4 py-3 text-end tabular-nums">
                                                            {score(row.financial_score)}
                                                        </td>
                                                    )}
                                                    <td className="px-4 py-3 text-end font-medium tabular-nums">
                                                        {score(row.final_score)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}

                {report === null ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-14 text-center">
                            <FileText className="size-10 text-muted-foreground" aria-hidden="true" />
                            <p className="mt-4 text-lg font-medium">{t('empty.no_report')}</p>
                            <p className="mt-1 mb-6 text-sm text-muted-foreground">
                                {t('empty.no_report_description')}
                            </p>
                            <Button onClick={handleGenerateReport} disabled={generateForm.processing}>
                                <FileText className="me-2 size-4" aria-hidden="true" />
                                {t('btn.generate_report')}
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="size-5" aria-hidden="true" />
                                    {t('pages.eval.evaluation_report')}
                                </CardTitle>
                                <Badge variant="outline">{t(`status.${report.status}`)}</Badge>
                            </div>
                            <CardDescription>
                                {t('eval.generated_on', {
                                    time: formatDateTime(report.generated_at, locale),
                                })}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="rounded-lg bg-muted/50 p-4">
                                <h2 className="mb-2 font-medium">{t('eval.summary')}</h2>
                                <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                                    {report.summary}
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button variant="outline" asChild>
                                    <a
                                        href={`/tenders/${tender.id}/evaluation-report/pdf`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Download className="me-2 size-4" aria-hidden="true" />
                                        {t('btn.download_pdf')}
                                    </a>
                                </Button>
                                <Button
                                    onClick={() => setConfirmApproval(true)}
                                    disabled={approvalForm.processing}
                                >
                                    <Send className="me-2 size-4" aria-hidden="true" />
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
                loading={approvalForm.processing}
                title={t('eval.submit_for_approval')}
                description={t('eval.submit_for_approval_confirm')}
            />
        </>
    );
}
