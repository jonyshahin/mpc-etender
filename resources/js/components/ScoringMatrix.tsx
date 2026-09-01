import { useCallback, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Criterion = {
    id: string;
    name_en: string;
    name_ar?: string | null;
    weight_percentage: string;
    max_score: string;
    description: string | null;
};

type ExistingScore = {
    criterion_id: string;
    score: string;
    justification: string | null;
};

export type ScoreEntry = {
    criterion_id: string;
    /**
     * null means the evaluator has not scored this criterion.
     *
     * It used to be seeded to 0 for every untouched row and emitted regardless,
     * so pressing "Save progress" wrote a zero against criteria nobody had
     * looked at — which then counted as scored, marked the bid complete, and
     * fed those zeros into the final ranking. An unscored criterion and a
     * criterion genuinely scored zero are different facts.
     */
    score: number | null;
    justification: string | null;
};

type ScoringMatrixProps = {
    criteria: Criterion[];
    existingScores?: Record<string, ExistingScore>;
    readOnly?: boolean;
    onChange?: (scores: ScoreEntry[]) => void;
};

type Entry = { score: number | null; justification: string | null };

export function ScoringMatrix({
    criteria,
    existingScores = {},
    readOnly = false,
    onChange,
}: ScoringMatrixProps) {
    const { t, locale } = useTranslation();

    const criterionName = (c: Criterion) => (locale === 'ar' ? (c.name_ar ?? c.name_en) : c.name_en);

    const [scores, setScores] = useState<Record<string, Entry>>(() => {
        const initial: Record<string, Entry> = {};

        for (const criterion of criteria) {
            const existing = existingScores[criterion.id];
            initial[criterion.id] = {
                score: existing ? parseFloat(existing.score) : null,
                justification: existing?.justification ?? null,
            };
        }

        return initial;
    });

    /**
     * What the evaluator has typed, before it is a number.
     *
     * Every keystroke used to be parsed, clamped and written straight back into
     * the controlled input, which made a decimal impossible to enter: "7."
     * parsed to 7, so the point vanished and 7.5 could never be typed. A
     * partial "7" on the way to 75 was clamped to the maximum for the same
     * reason, and the field could never be cleared.
     */
    const [drafts, setDrafts] = useState<Record<string, string>>({});

    const emitChange = useCallback(
        (updated: Record<string, Entry>) => {
            if (!onChange) {
                return;
            }

            onChange(
                criteria.map((c) => ({
                    criterion_id: c.id,
                    score: updated[c.id]?.score ?? null,
                    justification: updated[c.id]?.justification ?? null,
                })),
            );
        },
        [onChange, criteria],
    );

    const write = (criterionId: string, entry: Partial<Entry>) => {
        const updated = {
            ...scores,
            [criterionId]: { ...scores[criterionId], ...entry },
        };
        setScores(updated);
        emitChange(updated);
    };

    const handleScoreChange = (criterionId: string, value: string) => {
        setDrafts((current) => ({ ...current, [criterionId]: value }));

        const parsed = parseFloat(value);
        write(criterionId, { score: Number.isFinite(parsed) ? parsed : null });
    };

    /** Settle the value once, when the evaluator leaves the field. */
    const handleScoreBlur = (criterionId: string, maxScore: number) => {
        const raw = drafts[criterionId];
        const parsed = parseFloat(raw ?? '');

        setDrafts((current) => {
            const next = { ...current };
            delete next[criterionId];

            return next;
        });

        // An emptied field means unscored, not zero.
        write(criterionId, {
            score: Number.isFinite(parsed) ? Math.max(0, Math.min(maxScore, parsed)) : null,
        });
    };

    const handleJustificationChange = (criterionId: string, value: string) => {
        write(criterionId, { justification: value || null });
    };

    const scoredCount = useMemo(
        () => criteria.filter((c) => scores[c.id]?.score !== null && scores[c.id]?.score !== undefined).length,
        [scores, criteria],
    );

    const weightedTotal = useMemo(
        () =>
            criteria.reduce((sum, criterion) => {
                const entry = scores[criterion.id];
                const maxScore = parseFloat(criterion.max_score);

                if (!entry || entry.score === null || maxScore === 0) {
                    return sum;
                }

                return sum + (entry.score / maxScore) * parseFloat(criterion.weight_percentage);
            }, 0),
        [scores, criteria],
    );

    // A matrix with no rows still rendered its headings and a "Weighted Total
    // 0.00%" footer, so an evaluator whose committee has no criteria saw a
    // table that looked usable and two buttons that could only fail.
    if (criteria.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                {t('empty.no_criteria_defined')}
            </p>
        );
    }

    const scoreField = (criterion: Criterion) => {
        const entry = scores[criterion.id];
        const maxScore = parseFloat(criterion.max_score);

        if (readOnly) {
            return (
                <span className="font-semibold tabular-nums">
                    {entry?.score ?? <span className="font-normal text-muted-foreground">—</span>}
                </span>
            );
        }

        return (
            <Input
                type="number"
                min={0}
                max={maxScore}
                step="0.01"
                inputMode="decimal"
                value={drafts[criterion.id] ?? (entry?.score ?? '')}
                onChange={(e) => handleScoreChange(criterion.id, e.target.value)}
                onBlur={() => handleScoreBlur(criterion.id, maxScore)}
                aria-invalid={(entry?.score ?? 0) > maxScore}
                aria-label={`${criterionName(criterion)} — ${t('table.score')}`}
                placeholder="—"
                className="w-24 text-center tabular-nums"
            />
        );
    };

    const justificationField = (criterion: Criterion) => {
        const entry = scores[criterion.id];

        if (readOnly) {
            return <span className="text-muted-foreground">{entry?.justification ?? '—'}</span>;
        }

        return (
            <Textarea
                value={entry?.justification ?? ''}
                onChange={(e) => handleJustificationChange(criterion.id, e.target.value)}
                rows={2}
                aria-label={`${criterionName(criterion)} — ${t('table.justification')}`}
                placeholder={t('form.provide_justification')}
            />
        );
    };

    return (
        <div className="space-y-4">
            {/* Five columns, two of them form controls, do not fit a phone. The
                same rows render as stacked cards below md. */}
            <ul className="space-y-3 md:hidden">
                {criteria.map((criterion) => (
                    <li key={criterion.id} className="space-y-3 rounded-lg border p-4">
                        <div>
                            <p className="font-medium">{criterionName(criterion)}</p>
                            {criterion.description && (
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {criterion.description}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="secondary" className="tabular-nums">
                                {criterion.weight_percentage}%
                            </Badge>
                            <span>
                                {t('table.max_score')}:{' '}
                                <span className="tabular-nums">{criterion.max_score}</span>
                            </span>
                        </div>

                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">{t('table.score')}</span>
                            {scoreField(criterion)}
                        </div>

                        {justificationField(criterion)}
                    </li>
                ))}
            </ul>

            <div className="hidden overflow-x-auto md:block">
                <table className="w-full min-w-2xl border-collapse text-sm">
                    <thead>
                        <tr className="border-b bg-muted/50">
                            <th className="px-4 py-3 text-start font-medium">{t('table.criterion')}</th>
                            <th className="px-4 py-3 text-center font-medium">{t('table.weight')}</th>
                            <th className="px-4 py-3 text-center font-medium">{t('table.max_score')}</th>
                            <th className="px-4 py-3 text-center font-medium">{t('table.score')}</th>
                            <th className="px-4 py-3 text-start font-medium">
                                {t('table.justification')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {criteria.map((criterion) => (
                            <tr key={criterion.id} className="border-b last:border-0">
                                <td className="px-4 py-3">
                                    <div className="font-medium">{criterionName(criterion)}</div>
                                    {criterion.description && (
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {criterion.description}
                                        </div>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <Badge variant="secondary" className="tabular-nums">
                                        {criterion.weight_percentage}%
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-center tabular-nums">
                                    {criterion.max_score}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-center">{scoreField(criterion)}</div>
                                </td>
                                <td className="px-4 py-3">{justificationField(criterion)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div
                className={cn(
                    'flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/50 px-4 py-3 font-semibold',
                )}
            >
                <span>{t('eval.weighted_total')}</span>
                <span className="flex flex-wrap items-center gap-3">
                    {/* <bdi>: spaces either side of the slash split the digits into
                        separate bidi runs, so under dir="rtl" "1 / 5" was reordered
                        to read "5 / 1". */}
                    <span className="text-sm font-normal text-muted-foreground">
                        <bdi className="tabular-nums">
                            {scoredCount} / {criteria.length}
                        </bdi>{' '}
                        {t('eval.criteria_word')}
                    </span>
                    <span className="tabular-nums">{weightedTotal.toFixed(2)}%</span>
                </span>
            </div>
        </div>
    );
}
