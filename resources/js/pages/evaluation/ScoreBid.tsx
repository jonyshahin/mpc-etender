import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
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
        form.setData('complete', false);
        form.post(`/evaluations/${tender.id}/score/${bid.id}`, {
            preserveScroll: true,
        });
    };

    const handleCompleteScoring = () => {
        form.setData('complete', true);
        form.post(`/evaluations/${tender.id}/score/${bid.id}`, {
            preserveScroll: true,
        });
        setConfirmComplete(false);
    };

    return (
        <>
            <Head title={`Score Bid - ${bid.bid_reference}`} />
            <Heading
                title="Score Bid"
                description={`${tender.reference_number} - ${bid.vendor?.company_name ?? bid.bid_reference}`}
            />

            <div className="mt-6 space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Bid Details</CardTitle>
                        <CardDescription>
                            Vendor: {bid.vendor?.company_name ?? 'Unknown'} | Reference: {bid.bid_reference}
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Evaluation Scoring</CardTitle>
                        <CardDescription>
                            Score each criterion. Provide justification for your scores where applicable.
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
                        Save Progress
                    </Button>
                    <Button onClick={() => setConfirmComplete(true)} disabled={form.processing}>
                        <CheckCircle className="mr-2 h-4 w-4" />
                        Complete Scoring
                    </Button>
                </div>
            </div>

            <ConfirmDialog
                open={confirmComplete}
                onOpenChange={setConfirmComplete}
                onConfirm={handleCompleteScoring}
                title="Complete Scoring"
                description="Once completed, you will not be able to modify your scores for this bid. Are you sure you want to finalize your evaluation?"
            />
        </>
    );
}
