import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Check, ChevronRight, ClipboardList, ListChecks, Scale } from 'lucide-react';
import { StatTile } from '@/components/dashboard/StatTile';
import { StatusBadge } from '@/components/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Criterion = {
    id: string;
    name_en: string;
    name_ar: string | null;
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
    existingScores: Record<
        string,
        Array<{ criterion_id: string; score: string; justification: string | null }>
    >;
    /** Criterion envelopes this evaluator may score, from their committees. */
    envelopes: string[];
    hasCompleted: boolean;
};

export default function Scoring({
    tender,
    criteria,
    bids,
    existingScores,
    envelopes,
    hasCompleted,
}: Props) {
    const { t, locale } = useTranslation();

    const criterionName = (c: Criterion) => (locale === 'ar' ? (c.name_ar ?? c.name_en) : c.name_en);

    /**
     * How far through a bid the evaluator is.
     *
     * Set membership over criterion ids, not a length comparison: the two
     * counts came from different sets — criteria filtered by envelope, scores
     * not — so a part-scored bid could show a tick and a finished one could
     * read 'Not scored'. Returning the count rather than a boolean also lets
     * the row say "3 of 5" instead of implying the work is binary.
     */
    const scoredCriteria = (bidId: string) => {
        const scored = new Set((existingScores[bidId] ?? []).map((s) => s.criterion_id));

        return criteria.filter((c) => scored.has(c.id)).length;
    };

    const isComplete = (bidId: string) => criteria.length > 0 && scoredCriteria(bidId) === criteria.length;

    const scoredCount = bids.filter((b) => isComplete(b.id)).length;
    const totalWeight = criteria.reduce((sum, c) => sum + parseFloat(c.weight_percentage), 0);

    // Weights that do not add to 100 make every weighted total incomparable
    // between bids. It is the tender owner's problem, but the evaluator is the
    // one who would otherwise score a whole tender before anyone noticed.
    const weightsAreSound = criteria.length === 0 || Math.abs(totalWeight - 100) < 0.01;

    const progressPercent = bids.length === 0 ? 0 : Math.round((scoredCount / bids.length) * 100);

    return (
        <>
            <Head title={`${t('eval.scoring')} — ${tender.reference_number}`} />

            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{t('eval.scoring')}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {tender.reference_number} — {tender.title_en}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('eval.bids_to_score')}
                        value={String(bids.length)}
                        hint={t('eval.on_this_tender')}
                        icon={ClipboardList}
                    />
                    <StatTile
                        label={t('status.scored')}
                        value={String(scoredCount)}
                        hint={t('eval.fully_scored_by_you')}
                        icon={Check}
                    />
                    <StatTile
                        label={t('eval.remaining')}
                        value={String(bids.length - scoredCount)}
                        hint={t('eval.still_to_score')}
                        icon={ListChecks}
                    />
                    <StatTile
                        label={t('table.weight')}
                        value={`${totalWeight}%`}
                        hint={t('eval.criteria_count', { count: criteria.length })}
                        icon={Scale}
                    />
                </div>

                {!weightsAreSound && (
                    <p
                        role="alert"
                        className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/40"
                    >
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        {t('eval.weights_do_not_total_100', { total: totalWeight })}
                    </p>
                )}

                {/* flex-wrap: badges carry whitespace-nowrap and shrink-0, so a
                    row of them overflowed rather than wrapping on a phone. */}
                <div className="flex flex-wrap items-center gap-2">
                    {envelopes.map((envelope) => (
                        <Badge key={envelope} variant="outline">
                            {t('eval.envelope')}: {t(`eval.${envelope}`)}
                        </Badge>
                    ))}
                    {hasCompleted && (
                        <Badge className="border-transparent bg-emerald-600 text-white">
                            <Check className="me-1 size-3" aria-hidden="true" />
                            {t('eval.scoring_complete')}
                        </Badge>
                    )}
                </div>

                {bids.length > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="font-medium">{t('eval.your_progress')}</span>
                            <span className="tabular-nums text-muted-foreground">
                                {t('eval.scored_progress', { scored: scoredCount, total: bids.length })}
                            </span>
                        </div>
                        <div
                            className="h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-valuenow={progressPercent}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label={t('eval.your_progress')}
                        >
                            <div
                                className="h-full bg-emerald-600 transition-all"
                                style={{ width: `${progressPercent}%` }}
                            />
                        </div>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ClipboardList className="size-5" aria-hidden="true" />
                            {t('eval.bids_to_score')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {bids.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                {t('empty.no_bids_for_scoring')}
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {bids.map((bid) => {
                                    const done = scoredCriteria(bid.id);
                                    const complete = isComplete(bid.id);

                                    return (
                                        <li key={bid.id}>
                                            <Link
                                                href={`/evaluations/${tender.id}/score/${bid.id}`}
                                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4 transition-colors hover:bg-accent"
                                            >
                                                <span className="flex min-w-0 items-center gap-3">
                                                    <span
                                                        className={cn(
                                                            'flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-medium tabular-nums',
                                                            complete
                                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                                                : 'bg-muted text-muted-foreground',
                                                        )}
                                                        aria-hidden="true"
                                                    >
                                                        {complete ? <Check className="size-5" /> : done}
                                                    </span>
                                                    <span className="min-w-0">
                                                        <span className="block truncate font-medium">
                                                            {bid.vendor?.company_name ??
                                                                t('eval.unknown_vendor')}
                                                        </span>
                                                        <span className="block truncate text-sm text-muted-foreground">
                                                            {bid.bid_reference}
                                                        </span>
                                                    </span>
                                                </span>

                                                <span className="flex flex-wrap items-center gap-2">
                                                    <StatusBadge status={bid.status} />
                                                    {/* <bdi> around the fraction, deliberately.
                                                        Spaces either side of the slash break the
                                                        digits into separate runs, so under dir="rtl"
                                                        bidi reordered "1 / 2" to read "2 / 1" — the
                                                        row claimed more work was done than was. The
                                                        isolate pins the pair as one LTR unit. */}
                                                    <span className="text-sm text-muted-foreground">
                                                        <bdi className="tabular-nums">
                                                            {done} / {criteria.length}
                                                        </bdi>{' '}
                                                        {t('eval.criteria_word')}
                                                    </span>
                                                    {/* rtl:rotate-180 so the chevron points the way
                                                        the reader is going, not always right. */}
                                                    <ChevronRight
                                                        className="size-4 text-muted-foreground rtl:rotate-180"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('eval.evaluation_criteria')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {criteria.length === 0 ? (
                            <div className="py-10 text-center">
                                <p className="font-medium">{t('empty.no_criteria_defined')}</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('eval.no_criteria_for_your_committee')}
                                </p>
                            </div>
                        ) : (
                            <>
                                {/* Three columns still crowd a phone, so the same
                                    rows render as a list below sm. */}
                                <ul className="space-y-3 sm:hidden">
                                    {criteria.map((c) => (
                                        <li key={c.id} className="rounded-lg border p-3">
                                            <p className="font-medium">{criterionName(c)}</p>
                                            {c.description && (
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {c.description}
                                                </p>
                                            )}
                                            <p className="mt-2 flex flex-wrap gap-x-4 text-xs text-muted-foreground">
                                                <span>
                                                    {t('table.weight')}:{' '}
                                                    <span className="tabular-nums">
                                                        {c.weight_percentage}%
                                                    </span>
                                                </span>
                                                <span>
                                                    {t('table.max_score')}:{' '}
                                                    <span className="tabular-nums">{c.max_score}</span>
                                                </span>
                                            </p>
                                        </li>
                                    ))}
                                </ul>

                                <div className="hidden overflow-x-auto sm:block">
                                    <table className="w-full min-w-md text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                {/* text-start, not text-left: this app ships RTL
                                                    locales and a physical direction does not flip. */}
                                                <th className="px-4 py-2 text-start font-medium">
                                                    {t('table.criterion')}
                                                </th>
                                                <th className="px-4 py-2 text-center font-medium">
                                                    {t('table.weight')}
                                                </th>
                                                <th className="px-4 py-2 text-center font-medium">
                                                    {t('table.max_score')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {criteria.map((c) => (
                                                <tr key={c.id} className="border-b last:border-0">
                                                    <td className="px-4 py-2">
                                                        <div>{criterionName(c)}</div>
                                                        {c.description && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {c.description}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-2 text-center">
                                                        <Badge variant="secondary" className="tabular-nums">
                                                            {c.weight_percentage}%
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-2 text-center tabular-nums">
                                                        {c.max_score}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
