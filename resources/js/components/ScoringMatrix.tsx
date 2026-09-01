import { useState, useCallback, useMemo } from 'react';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

type Criterion = {
    id: string;
    name_en: string;
    weight_percentage: string;
    max_score: string;
    description: string | null;
};

type ExistingScore = {
    criterion_id: string;
    score: string;
    justification: string | null;
};

type ScoreEntry = {
    criterion_id: string;
    score: number;
    justification: string | null;
};

type ScoringMatrixProps = {
    criteria: Criterion[];
    existingScores?: Record<string, ExistingScore>;
    readOnly?: boolean;
    onChange?: (scores: ScoreEntry[]) => void;
};

export function ScoringMatrix({ criteria, existingScores = {}, readOnly = false, onChange }: ScoringMatrixProps) {
    const { t } = useTranslation();
    const [scores, setScores] = useState<Record<string, { score: number; justification: string | null }>>(() => {
        const initial: Record<string, { score: number; justification: string | null }> = {};
        for (const criterion of criteria) {
            const existing = existingScores[criterion.id];
            initial[criterion.id] = {
                score: existing ? parseFloat(existing.score) : 0,
                justification: existing?.justification ?? null,
            };
        }
        return initial;
    });

    const emitChange = useCallback(
        (updated: Record<string, { score: number; justification: string | null }>) => {
            if (!onChange) return;
            const entries: ScoreEntry[] = criteria.map((c) => ({
                criterion_id: c.id,
                score: updated[c.id]?.score ?? 0,
                justification: updated[c.id]?.justification ?? null,
            }));
            onChange(entries);
        },
        [onChange, criteria],
    );

    /**
     * What the evaluator has typed, before it is a number.
     *
     * Every keystroke used to be parsed and clamped and written straight
     * back into the controlled input, which made a decimal impossible to
     * enter: "7." parsed to 7, so the point vanished and 7.5 could never be
     * typed. A partial "7" on the way to 75 was clamped to the maximum for
     * the same reason, and the field could never be cleared.
     */
    const [drafts, setDrafts] = useState<Record<string, string>>({});

    const handleScoreChange = (criterionId: string, value: string) => {
        setDrafts((current) => ({ ...current, [criterionId]: value }));

        const parsed = parseFloat(value);
        const updated = {
            ...scores,
            [criterionId]: {
                ...scores[criterionId],
                score: Number.isFinite(parsed) ? parsed : 0,
            },
        };
        setScores(updated);
        emitChange(updated);
    };

    /** Settle the value once, when the evaluator leaves the field. */
    const handleScoreBlur = (criterionId: string, maxScore: number) => {
        const parsed = parseFloat(drafts[criterionId] ?? '');
        const settled = Number.isFinite(parsed) ? Math.max(0, Math.min(maxScore, parsed)) : 0;

        setDrafts((current) => {
            const next = { ...current };
            delete next[criterionId];

            return next;
        });

        const updated = {
            ...scores,
            [criterionId]: { ...scores[criterionId], score: settled },
        };
        setScores(updated);
        emitChange(updated);
    };

    const handleJustificationChange = (criterionId: string, value: string) => {
        const updated = { ...scores, [criterionId]: { ...scores[criterionId], justification: value || null } };
        setScores(updated);
        emitChange(updated);
    };

    const weightedTotal = useMemo(() => {
        return criteria.reduce((sum, criterion) => {
            const entry = scores[criterion.id];
            if (!entry) return sum;
            const maxScore = parseFloat(criterion.max_score);
            const weight = parseFloat(criterion.weight_percentage);
            if (maxScore === 0) return sum;
            return sum + (entry.score / maxScore) * weight;
        }, 0);
    }, [scores, criteria]);

    // A matrix with no rows still rendered its headings and a "Weighted Total
    // 0.00%" footer, so an evaluator whose committee has no criteria saw a
    // table that looked usable and two buttons that failed server validation.
    if (criteria.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                {t('empty.no_criteria_defined')}
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="border-b bg-muted/50">
                        <th className="px-4 py-3 text-start font-medium">{t('table.criterion')}</th>
                        <th className="px-4 py-3 text-center font-medium">{t('table.weight')}</th>
                        <th className="px-4 py-3 text-center font-medium">{t('table.max_score')}</th>
                        <th className="px-4 py-3 text-center font-medium">{t('table.score')}</th>
                        <th className="px-4 py-3 text-start font-medium">{t('table.justification')}</th>
                    </tr>
                </thead>
                <tbody>
                    {criteria.map((criterion) => {
                        const entry = scores[criterion.id];
                        const maxScore = parseFloat(criterion.max_score);
                        return (
                            <tr key={criterion.id} className="border-b">
                                <td className="px-4 py-3">
                                    <div className="font-medium">{criterion.name_en}</div>
                                    {criterion.description && (
                                        <div className="mt-1 text-xs text-muted-foreground">{criterion.description}</div>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <Badge variant="secondary">{criterion.weight_percentage}%</Badge>
                                </td>
                                <td className="px-4 py-3 text-center">{criterion.max_score}</td>
                                <td className="px-4 py-3 text-center">
                                    {readOnly ? (
                                        <span className="font-semibold">{entry?.score ?? 0}</span>
                                    ) : (
                                        <Input
                                            type="number"
                                            min={0}
                                            max={maxScore}
                                            step="0.01"
                                            value={drafts[criterion.id] ?? (entry?.score ?? '')}
                                            onChange={(e) => handleScoreChange(criterion.id, e.target.value)}
                                            onBlur={() => handleScoreBlur(criterion.id, maxScore)}
                                            aria-invalid={(entry?.score ?? 0) > maxScore}
                                            className="w-20 mx-auto text-center"
                                        />
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    {readOnly ? (
                                        <span className="text-muted-foreground">{entry?.justification ?? '-'}</span>
                                    ) : (
                                        <textarea
                                            value={entry?.justification ?? ''}
                                            onChange={(e) => handleJustificationChange(criterion.id, e.target.value)}
                                            rows={2}
                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            placeholder="Provide justification..."
                                        />
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
                <tfoot>
                    <tr className="bg-muted/50 font-semibold">
                        <td className="px-4 py-3" colSpan={3}>
                            {t('eval.weighted_total')}
                        </td>
                        <td className="px-4 py-3 text-center text-lg">{weightedTotal.toFixed(2)}%</td>
                        <td className="px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
