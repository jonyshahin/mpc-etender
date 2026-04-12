import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { StatusBadge } from '@/components/StatusBadge';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Check, Circle, ClipboardList } from 'lucide-react';

type Criterion = {
    id: string;
    name_en: string;
    envelope: string;
    weight_percentage: string;
    max_score: string;
    description: string | null;
};

type Bid = {
    id: string;
    bid_reference: string;
    status: string;
    vendor?: { id: string; company_name: string };
};

type Props = {
    tender: { id: string; reference_number: string; title_en: string; is_two_envelope: boolean };
    criteria: Criterion[];
    bids: Bid[];
    existingScores: Record<string, Array<{ criterion_id: string; score: string; justification: string | null }>>;
    envelope: string;
    hasCompleted: boolean;
};

export default function Scoring({ tender, criteria, bids, existingScores, envelope, hasCompleted }: Props) {
    const { t } = useTranslation();
    const isBidScored = (bidId: string) => {
        const scores = existingScores[bidId];
        return scores && scores.length === criteria.length;
    };

    const totalWeight = criteria.reduce((sum, c) => sum + parseFloat(c.weight_percentage), 0);

    return (
        <>
            <Head title={`Scoring - ${tender.reference_number}`} />
            <Heading title={t('pages.eval.bid_scoring')} description={`${tender.reference_number} - ${tender.title_en}`} />

            <div className="mt-6 space-y-6">
                <div className="flex items-center gap-4">
                    <Badge variant="outline" className="text-sm">
                        {t('eval.envelope')}: {envelope}
                    </Badge>
                    <Badge variant="outline" className="text-sm">
                        {criteria.length} criteria ({totalWeight}% total weight)
                    </Badge>
                    {hasCompleted && (
                        <Badge variant="default" className="bg-green-600 text-sm">
                            <Check className="mr-1 h-3 w-3" />
                            {t('eval.scoring_complete')}
                        </Badge>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ClipboardList className="h-5 w-5" />
                            {t('eval.bids_to_score')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {bids.length === 0 ? (
                            <p className="py-8 text-center text-muted-foreground">{t('empty.no_bids_for_scoring')}</p>
                        ) : (
                            <div className="space-y-3">
                                {bids.map((bid) => {
                                    const scored = isBidScored(bid.id);
                                    return (
                                        <Link
                                            key={bid.id}
                                            href={`/evaluations/${tender.id}/score/${bid.id}`}
                                            className="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-muted/50"
                                        >
                                            <div className="flex items-center gap-4">
                                                <div
                                                    className={`flex h-10 w-10 items-center justify-center rounded-full ${scored ? 'bg-green-100 text-green-600' : 'bg-muted text-muted-foreground'}`}
                                                >
                                                    {scored ? (
                                                        <Check className="h-5 w-5" />
                                                    ) : (
                                                        <Circle className="h-5 w-5" />
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="font-medium">
                                                        {bid.vendor?.company_name ?? t('eval.unknown_vendor')}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {bid.bid_reference}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <StatusBadge status={bid.status} />
                                                {scored ? (
                                                    <Badge variant="default" className="bg-green-600">
                                                        {t('status.scored')}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">{t('status.not_scored')}</Badge>
                                                )}
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {criteria.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('eval.evaluation_criteria')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="px-4 py-2 text-left">{t('table.criterion')}</th>
                                        <th className="px-4 py-2 text-center">{t('table.weight')}</th>
                                        <th className="px-4 py-2 text-center">{t('table.max_score')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {criteria.map((c) => (
                                        <tr key={c.id} className="border-b">
                                            <td className="px-4 py-2">
                                                <div>{c.name_en}</div>
                                                {c.description && (
                                                    <div className="text-xs text-muted-foreground">{c.description}</div>
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-center">
                                                <Badge variant="secondary">{c.weight_percentage}%</Badge>
                                            </td>
                                            <td className="px-4 py-2 text-center">{c.max_score}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
