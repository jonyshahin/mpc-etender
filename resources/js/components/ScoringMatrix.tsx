import { useState, useCallback, useMemo } from 'react';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';

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

    const handleScoreChange = (criterionId: string, maxScore: number, value: string) => {
        const num = Math.max(0, Math.min(maxScore, parseFloat(value) || 0));
        const updated = { ...scores, [criterionId]: { ...scores[criterionId], score: num } };
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

    return (
        <div className="overflow-x-auto">
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="border-b bg-muted/50">
                        <th className="px-4 py-3 text-left font-medium">Criterion</th>
                        <th className="px-4 py-3 text-center font-medium">Weight</th>
                        <th className="px-4 py-3 text-center font-medium">Max Score</th>
                        <th className="px-4 py-3 text-center font-medium">Score</th>
                        <th className="px-4 py-3 text-left font-medium">Justification</th>
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
                                            value={entry?.score ?? 0}
                                            onChange={(e) => handleScoreChange(criterion.id, maxScore, e.target.value)}
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
                            Weighted Total
                        </td>
                        <td className="px-4 py-3 text-center text-lg">{weightedTotal.toFixed(2)}%</td>
                        <td className="px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
