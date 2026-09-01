import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, Save } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import type { ScoreEntry } from '@/components/ScoringMatrix';
import { ScoringMatrix } from '@/components/ScoringMatrix';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

type Criterion = {
    id: string;
    name_en: string;
    name_ar: string | null;
    weight_percentage: string;
    max_score: string;
    description: string | null;
};

type Props = {
    tender: { id: string; reference_number: string; title_en: string };
    bid: { id: string; bid_reference: string; vendor?: { id: string; company_name: string } };
    criteria: Criterion[];
    existingScores: Record<
        string,
        { criterion_id: string; score: string; justification: string | null }
    >;
};

export default function ScoreBid({ tender, bid, criteria, existingScores }: Props) {
    const { t } = useTranslation();
    const [confirmComplete, setConfirmComplete] = useState(false);
    const [entries, setEntries] = useState<ScoreEntry[]>(() =>
        criteria.map((c) => ({
            criterion_id: c.id,
            score: existingScores[c.id] ? parseFloat(existingScores[c.id].score) : null,
            justification: existingScores[c.id]?.justification ?? null,
        })),
    );

    const form = useForm<{
        scores: Array<{ criterion_id: string; score: number; justification: string | null }>;
        complete: boolean;
    }>({ scores: [], complete: false });

    /**
     * Only criteria the evaluator has actually scored.
     *
     * Untouched rows used to be seeded to 0 and submitted with the rest, so
     * opening a bid and pressing "Save progress" wrote a zero against every
     * criterion nobody had looked at — which counted as scored and fed those
     * zeros into the ranking.
     */
    const scored = entries.filter(
        (e): e is ScoreEntry & { score: number } => e.score !== null,
    );

    const submit = (complete: boolean) => {
        form.transform(() => ({ scores: scored, complete }));
        form.post(`/evaluations/${tender.id}/score/${bid.id}`, { preserveScroll: true });
    };

    // Everything the server said about the scores, top-level and per-item.
    // StoreScoresRequest produces per-item keys — scores.0.score,
    // scores.2.justification — and only the top-level one was rendered, so a
    // rejected row gave the evaluator nothing to go on.
    const scoreErrors = Object.entries(form.errors)
        .filter(([key]) => key === 'scores' || key.startsWith('scores.'))
        .map(([, message]) => message)
        .filter((message): message is string => Boolean(message));

    const everythingScored = criteria.length > 0 && scored.length === criteria.length;

    return (
        <>
            <Head title={`${t('pages.eval.score_bid')} — ${bid.bid_reference}`} />

            <div className="space-y-6 p-4 md:p-6">
                <div>
                    {/* There was no way back to the bid list except the browser
                        button — and nothing linked into this screen either. */}
                    <Link
                        href={`/evaluations/${tender.id}/score`}
                        className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4 rtl:rotate-180" aria-hidden="true" />
                        {t('eval.bids_to_score')}
                    </Link>

                    <h1 className="mt-2 text-2xl font-semibold tracking-tight">
                        {bid.vendor?.company_name ?? t('eval.unknown_vendor')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {tender.reference_number} — {bid.bid_reference}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('eval.evaluation_scoring')}</CardTitle>
                        <CardDescription>{t('eval.scoring_instructions')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <ScoringMatrix
                            criteria={criteria}
                            existingScores={existingScores}
                            onChange={setEntries}
                        />

                        {scoreErrors.length > 0 && (
                            <ul
                                role="alert"
                                className="list-inside list-disc rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                            >
                                {scoreErrors.map((message, index) => (
                                    <li key={index}>{message}</li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center justify-end gap-3">
                    {!everythingScored && criteria.length > 0 && (
                        <p className="me-auto text-sm text-muted-foreground">
                            {t('eval.score_every_criterion_to_complete')}
                        </p>
                    )}
                    <Button
                        variant="outline"
                        onClick={() => submit(false)}
                        disabled={form.processing || scored.length === 0}
                    >
                        <Save className="me-2 size-4" aria-hidden="true" />
                        {t('btn.save_progress')}
                    </Button>
                    <Button
                        onClick={() => setConfirmComplete(true)}
                        disabled={form.processing || !everythingScored}
                    >
                        <CheckCircle className="me-2 size-4" aria-hidden="true" />
                        {t('btn.complete_scoring')}
                    </Button>
                </div>
            </div>

            <ConfirmDialog
                open={confirmComplete}
                onOpenChange={setConfirmComplete}
                onConfirm={() => {
                    submit(true);
                    setConfirmComplete(false);
                }}
                loading={form.processing}
                title={t('eval.complete_scoring')}
                description={t('eval.complete_scoring_confirm')}
            />
        </>
    );
}
