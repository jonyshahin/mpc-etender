import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { ScoringMatrix } from '@/components/ScoringMatrix';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card';
import { Save, CheckCircle } from 'lucide-react';

type Criterion = {
    id: string;
    name_en: string;
    weight_percentage: string;
    max_score: string;
    description: string | null;
};

type Props = {
    tender: { id: string; reference_number: string; title_en: string };
    bid: { id: string; bid_reference: string; vendor?: { id: string; company_name: string } };
    criteria: Criterion[];
    existingScores: Record<string, { criterion_id: string; score: string; justification: string | null }>;
};

export default function ScoreBid({ tender, bid, criteria, existingScores }: Props) {
    const { t } = useTranslation();
    const [confirmComplete, setConfirmComplete] = useState(false);

    const form = useForm<{
        scores: Array<{ criterion_id: string; score: number; justification: string | null }>;
        complete: boolean;
    }>({
        scores: criteria.map((c) => ({
            criterion_id: c.id,
            score: existingScores[c.id] ? parseFloat(existingScores[c.id].score) : 0,
            justification: existingScores[c.id]?.justification ?? null,
        })),
        complete: false,
    });

    const handleScoresChange = (
        scores: Array<{ criterion_id: string; score: number; justification: string | null }>,
    ) => {
        form.setData('scores', scores);
    };

    const handleSaveProgress = () => {
        form.transform((data) => ({ ...data, complete: false }));
        form.post(`/evaluations/${tender.id}/score/${bid.id}`, {
            preserveScroll: true,
        });
    };

    const handleCompleteScoring = () => {
        form.transform((data) => ({ ...data, complete: true }));
        form.post(`/evaluations/${tender.id}/score/${bid.id}`, {
            preserveScroll: true,
        });
        setConfirmComplete(false);
    };

    return (
        <>
            <Head title={`Score Bid - ${bid.bid_reference}`} />
            <Heading
                title={t('pages.eval.score_bid')}
                description={`${tender.reference_number} - ${bid.vendor?.company_name ?? bid.bid_reference}`}
            />

            <div className="mt-6 space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('eval.bid_details')}</CardTitle>
                        <CardDescription>
                            {t('table.vendor')}: {bid.vendor?.company_name ?? t('eval.unknown_vendor')} | {t('table.reference')}: {bid.bid_reference}
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('eval.evaluation_scoring')}</CardTitle>
                        <CardDescription>
                            {t('eval.scoring_instructions')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ScoringMatrix
                            criteria={criteria}
                            existingScores={existingScores}
                            onChange={handleScoresChange}
                        />

                        {form.errors.scores && (
                            <p className="mt-4 text-sm text-destructive">{form.errors.scores}</p>
                        )}
                    </CardContent>
                </Card>

                <div className="flex items-center justify-end gap-4">
                    <Button variant="outline" onClick={handleSaveProgress} disabled={form.processing}>
                        <Save className="mr-2 h-4 w-4" />
                        {t('btn.save_progress')}
                    </Button>
                    <Button onClick={() => setConfirmComplete(true)} disabled={form.processing}>
                        <CheckCircle className="mr-2 h-4 w-4" />
                        {t('btn.complete_scoring')}
                    </Button>
                </div>
            </div>

            <ConfirmDialog
                open={confirmComplete}
                onOpenChange={setConfirmComplete}
                onConfirm={handleCompleteScoring}
                title={t('eval.complete_scoring')}
                description={t('eval.complete_scoring_confirm')}
            />
        </>
    );
}
