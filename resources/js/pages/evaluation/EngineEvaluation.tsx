import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Clock,
    Info,
    Loader2,
    Lock,
    Play,
    RefreshCw,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/datetime';
import { formatDecimal, formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

const CRITERIA = [
    'price',
    'technical',
    'timeline',
    'references',
    'payment_flexibility',
] as const;
const SCORE_FIELDS = [
    ['technical', 'technical_score'],
    ['references', 'references_score'],
    ['payment_flexibility', 'payment_flexibility_score'],
] as const;
const GATES = ['bank_guarantee', 'mandatory_documents'] as const;

/** How often a running batch is checked. A cold engine takes ~2 s to wake. */
const POLL_MS = 2500;

type Gate = (typeof GATES)[number];

type Profile = {
    profileKey: string;
    lockedAt: string | null;
    lockedBy: string | null;
    weights: Record<(typeof CRITERIA)[number], number>;
    gates: Record<Gate, boolean>;
    marginBelow: number;
    contractType: string | null;
    contractTypeLabel: string | null;
    contractTypeLabelKey: string | null;
    timelineFormulaLabelKey: string | null;
    timelineFormulaExplanationKey: string | null;
};

type BidRow = {
    id: string;
    reference: string;
    vendorName: string | null;
    amount: number;
    currency: string;
    durationDays: number | null;
    missing: string[];
    assessment: {
        technical_score: number | null;
        references_score: number | null;
        payment_flexibility_score: number | null;
        scoredBy: string | null;
        scoredAt: string | null;
        gates: Record<
            Gate,
            {
                value: boolean | null;
                attestedBy: string | null;
                attestedAt: string | null;
            }
        >;
    };
};

type Evaluation = {
    id: string;
    batchId: string;
    status: 'queued' | 'processing' | 'completed' | 'partial';
    final: boolean;
    stuck: boolean;
    submittedAt: string;
    submittedBy: string | null;
    finalisedAt: string | null;
    supersedes: string | null;
    unreachable: string | null;
    /** Seconds after which an unfinished batch counts as stuck. */
    stuckAfterSeconds: number;
};

type CriterionResult = {
    key: string;
    score: number | null;
    weightPercent: number;
    weighted: number | null;
    source: string | null;
};

type RankedRow = {
    rank: number;
    reference: string;
    vendorName: string;
    weightedTotal: number;
    complete: boolean;
    outcome: string | null;
    missing: string[];
    criteria: CriterionResult[];
};

type NotRankedRow = {
    reference: string;
    vendorName: string;
    status: string;
    outcome: string | null;
    failedGates: string[];
    unresolvedGates: string[];
    missing: string[];
    error: string | null;
};

type Result = {
    batchId: string;
    status: 'completed' | 'partial';
    provisional: boolean;
    finalisedAt: string | null;
    margin: number | null;
    marginBelow: number;
    marginFlagged: boolean;
    ranking: RankedRow[];
    notRanked: NotRankedRow[];
    baseline: { priceBest: number | null; timelineBest: number | null };
};

type ComparisonRow = {
    bidId: string;
    reference: string;
    vendorName: string;
    engineRank: number | null;
    engineTotal: number | null;
    engineComplete: boolean | null;
    moduleRank: number | null;
    moduleTotal: number | null;
    rankDifference: number | null;
};

type Props = {
    tender: {
        id: string;
        reference_number: string;
        title_en: string;
        title_ar: string | null;
        status: string;
        currency: string;
    };
    unavailableReason: string | null;
    runBlockedReason: string | null;
    canRun: boolean;
    profile: Profile | null;
    bids: BidRow[];
    /** Two-envelope bids below the technical pass mark, left out of the set. */
    technicallyExcluded: Array<{
        reference: string;
        vendorName: string | null;
    }>;
    technicalPassScore: number | null;
    latest: Evaluation | null;
    history: Evaluation[];
    result: Result | null;
    comparison: {
        moduleScored: boolean;
        /** How many bids both methods ranked — the set ranks are compared over. */
        comparedCount: number;
        rows: ComparisonRow[];
    } | null;
};

/**
 * Decision Engine scoring for one tender, in shadow mode.
 *
 * Officers enter one agreed score per judgement criterion and confirm the two
 * document conditions; the engine computes price and timeline against the
 * other bids and ranks by weighted total. The ranking is shown next to the
 * existing evaluation and decides nothing — the page says so, and never calls
 * the top row a winner or a recommendation.
 */
export default function EngineEvaluation(props: Props) {
    const {
        tender,
        unavailableReason,
        runBlockedReason,
        canRun,
        profile,
        bids,
        history,
        result,
        comparison,
    } = props;
    const { t, locale } = useTranslation();
    const title =
        locale === 'ar' && tender.title_ar ? tender.title_ar : tender.title_en;

    return (
        <>
            <Head
                title={`${t('engine.run.title')} — ${tender.reference_number}`}
            />

            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <Link
                        href={`/tenders/${tender.id}`}
                        className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft
                            className="size-4 rtl:rotate-180"
                            aria-hidden="true"
                        />
                        <bdi>{tender.reference_number}</bdi>
                    </Link>
                    <h1 className="mt-2 text-2xl font-semibold tracking-tight">
                        {t('engine.run.title')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {title}
                    </p>
                </div>

                <Notice icon={Info} tone="info">
                    {t('engine.run.shadow_notice')}
                </Notice>

                {unavailableReason || !profile ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            {t(
                                unavailableReason ??
                                    'engine.unavailable.opened_before_engine',
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <ProfileSummary profile={profile} />
                        <RunPanel {...props} profile={profile} />
                        <EntryTable
                            tenderId={tender.id}
                            bids={bids}
                            profile={profile}
                            editable={canRun && runBlockedReason === null}
                        />
                        {result ? (
                            <Results
                                result={result}
                                currency={tender.currency}
                            />
                        ) : null}
                        {comparison && result ? (
                            <Comparison comparison={comparison} />
                        ) : null}
                        {history.length > 1 ? (
                            <History history={history} />
                        ) : null}
                    </>
                )}
            </div>
        </>
    );
}

/* ───────────────────────────────────────────────────────────────────────── */

function Notice({
    icon: Icon,
    tone,
    title,
    children,
}: {
    icon: typeof Info;
    tone: 'info' | 'warning' | 'danger';
    title?: string;
    children: React.ReactNode;
}) {
    return (
        <div
            role={tone === 'info' ? 'note' : 'alert'}
            className={cn(
                'flex items-start gap-3 rounded-md border p-4 text-sm',
                tone === 'info' && 'border-border bg-muted/40',
                tone === 'warning' && 'border-amber-500/40 bg-amber-500/10',
                tone === 'danger' && 'border-red-500/40 bg-red-500/10',
            )}
        >
            <Icon
                className={cn(
                    'mt-0.5 size-4 shrink-0',
                    tone === 'info' && 'text-muted-foreground',
                    tone === 'warning' && 'text-amber-600 dark:text-amber-400',
                    tone === 'danger' && 'text-red-600 dark:text-red-400',
                )}
                aria-hidden="true"
            />
            <div className="min-w-0 space-y-1">
                {title ? <p className="font-medium">{title}</p> : null}
                <div className="text-muted-foreground">{children}</div>
            </div>
        </div>
    );
}

/** The locked criteria this tender is scored against. */
function ProfileSummary({ profile }: { profile: Profile }) {
    const { t, locale } = useTranslation();
    const contractType =
        profile.contractType === 'other'
            ? profile.contractTypeLabel
            : profile.contractTypeLabelKey
              ? t(profile.contractTypeLabelKey)
              : null;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Lock
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {t('engine.criteria.title')}
                </CardTitle>
                {profile.lockedAt ? (
                    <CardDescription>
                        {t('engine.criteria.locked', {
                            date: formatDateTime(profile.lockedAt, locale),
                            name: profile.lockedBy ?? '—',
                        })}{' '}
                        <span className="font-mono text-xs">
                            <bdi>
                                {t('engine.criteria.locked_key', {
                                    key: profile.profileKey,
                                })}
                            </bdi>
                        </span>
                    </CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className="grid gap-6 md:grid-cols-3">
                <dl className="space-y-1.5 text-sm">
                    {CRITERIA.map((key) => (
                        <div key={key} className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">
                                {t(`engine.criterion.${key}`)}
                            </dt>
                            <dd className="font-medium tabular-nums">
                                <bdi>{profile.weights[key]}%</bdi>
                            </dd>
                        </div>
                    ))}
                </dl>
                <dl className="space-y-1.5 text-sm">
                    {GATES.map((gate) => (
                        <div key={gate} className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">
                                {t(`engine.gate.${gate}`)}
                            </dt>
                            <dd>
                                {profile.gates[gate]
                                    ? t('engine.entry.yes')
                                    : t('engine.entry.no')}
                            </dd>
                        </div>
                    ))}
                </dl>
                <dl className="space-y-1.5 text-sm">
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">
                            {t('engine.criteria.contract_type')}
                        </dt>
                        <dd className="font-medium">
                            {contractType ?? t('engine.criteria.not_set')}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            {t('engine.criteria.timeline_formula')}
                        </dt>
                        <dd className="font-medium">
                            {profile.timelineFormulaLabelKey
                                ? t(profile.timelineFormulaLabelKey)
                                : t('engine.criteria.not_set')}
                        </dd>
                        {profile.timelineFormulaExplanationKey ? (
                            <dd className="mt-1 text-xs text-muted-foreground">
                                {t(profile.timelineFormulaExplanationKey)}
                            </dd>
                        ) : null}
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">
                            {t('engine.criteria.margin_short')}
                        </dt>
                        <dd className="font-medium tabular-nums">
                            <bdi>
                                &lt;{' '}
                                {formatDecimal(profile.marginBelow, locale, {
                                    maximumFractionDigits: 2,
                                })}
                            </bdi>
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}

/** Run button, readiness, and the waiting / stuck state of the latest batch. */
function RunPanel({
    tender,
    runBlockedReason,
    canRun,
    bids,
    technicallyExcluded,
    technicalPassScore,
    latest: initialLatest,
    result,
}: Props & { profile: Profile }) {
    const { t, locale } = useTranslation();
    const [latest, setLatest] = useState<Evaluation | null>(initialLatest);
    const [checking, setChecking] = useState(false);
    // Consecutive checks that got no answer at all — not a result.
    const [failures, setFailures] = useState(0);
    const [now, setNow] = useState(() => Date.now());
    const runForm = useForm({});

    useEffect(() => {
        setLatest(initialLatest);
        setFailures(0);
    }, [initialLatest]);

    // Stuck is decided here too, from when the batch was sent. If every check
    // fails, the waiting state must still end in something an officer can act
    // on — the server's flag alone would never arrive.
    const stuck =
        latest !== null &&
        !latest.final &&
        (latest.stuck ||
            now - Date.parse(latest.submittedAt) >=
                latest.stuckAfterSeconds * 1000);

    const check = useCallback(async () => {
        if (!latest) {
            return;
        }

        setChecking(true);

        try {
            const response = await fetch(
                `/tenders/${tender.id}/engine-evaluation/${latest.id}/status`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const next: Evaluation = await response.json();
            setLatest(next);
            setFailures(0);

            if (next.final) {
                // The result, breakdown and comparison come with the page.
                router.reload();
            }
        } catch {
            // eTender itself did not answer (network, session, server). Keep
            // checking, more slowly, and say so on screen.
            setFailures((n) => n + 1);
        } finally {
            setChecking(false);
            setNow(Date.now());
        }
    }, [latest, tender.id]);

    // Poll while the batch runs, backing off after failed checks; stop when it
    // is final or stuck. A stuck batch is checked only on request.
    useEffect(() => {
        if (!latest || latest.final || stuck) {
            return;
        }

        const timer = window.setTimeout(
            check,
            Math.min(POLL_MS * 2 ** failures, 30_000),
        );

        return () => window.clearTimeout(timer);
    }, [latest, stuck, failures, check]);

    const notReady = bids.filter((bid) => bid.missing.length > 0).length;
    const running = latest !== null && !latest.final;
    const canSubmit =
        canRun &&
        runBlockedReason === null &&
        !running &&
        bids.length > 0 &&
        notReady === 0;

    return (
        <Card>
            <CardContent className="space-y-4">
                {runBlockedReason ? (
                    <p className="text-sm text-muted-foreground">
                        {t(runBlockedReason)}
                    </p>
                ) : (
                    <p
                        className={cn(
                            'text-sm',
                            notReady > 0
                                ? 'text-amber-700 dark:text-amber-400'
                                : 'text-muted-foreground',
                        )}
                    >
                        {notReady > 0
                            ? t('engine.run.incomplete', { count: notReady })
                            : t('engine.run.ready')}
                    </p>
                )}

                {technicallyExcluded.length > 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('engine.run.technical_excluded', {
                            count: technicallyExcluded.length,
                            score: formatDecimal(
                                technicalPassScore ?? 0,
                                locale,
                                {
                                    maximumFractionDigits: 2,
                                },
                            ),
                            bids: listOf(
                                technicallyExcluded.map(
                                    (b) => b.vendorName ?? b.reference,
                                ),
                                locale,
                            ),
                        })}
                    </p>
                ) : null}

                {running && !stuck ? (
                    <div
                        className="flex items-center gap-3 text-sm"
                        role="status"
                        aria-live="polite"
                    >
                        <Loader2
                            className="size-4 animate-spin text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span>{t('engine.wait.running')}</span>
                        <Badge variant="secondary">
                            {t(`engine.status.${latest.status}`)}
                        </Badge>
                    </div>
                ) : null}

                {running && stuck ? (
                    <Notice
                        icon={Clock}
                        tone="warning"
                        title={t('engine.wait.stuck_title')}
                    >
                        <p>
                            {t('engine.wait.stuck_body', {
                                date: formatDateTime(
                                    latest.submittedAt,
                                    locale,
                                ),
                            })}
                        </p>
                        <p className="mt-1 font-mono text-xs">
                            <bdi>
                                {t('engine.wait.batch', { id: latest.batchId })}
                            </bdi>
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            className="mt-3"
                            onClick={check}
                            disabled={checking}
                        >
                            <RefreshCw
                                className={cn(
                                    'me-2 size-4',
                                    checking && 'animate-spin',
                                )}
                                aria-hidden="true"
                            />
                            {t('engine.wait.check_again')}
                        </Button>
                    </Notice>
                ) : null}

                {running && latest?.unreachable ? (
                    <Notice icon={AlertTriangle} tone="warning">
                        {t('engine.wait.unreachable', {
                            reason: latest.unreachable,
                        })}
                    </Notice>
                ) : null}

                {running && failures > 0 ? (
                    <Notice icon={AlertTriangle} tone="warning">
                        {t('engine.wait.check_failed')}
                    </Notice>
                ) : null}

                {canRun && runBlockedReason === null ? (
                    <Button
                        onClick={() =>
                            runForm.post(
                                `/tenders/${tender.id}/engine-evaluation`,
                                { preserveScroll: true },
                            )
                        }
                        disabled={!canSubmit || runForm.processing}
                    >
                        {runForm.processing ? (
                            <Loader2
                                className="me-2 size-4 animate-spin"
                                aria-hidden="true"
                            />
                        ) : (
                            <Play
                                className="me-2 size-4 rtl:rotate-180"
                                aria-hidden="true"
                            />
                        )}
                        {result ? t('engine.run.rerun') : t('engine.run.run')}
                    </Button>
                ) : null}
                {runForm.errors && 'evaluation' in runForm.errors ? (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        {(runForm.errors as Record<string, string>).evaluation}
                    </p>
                ) : null}
            </CardContent>
        </Card>
    );
}

/** One row per opened bid: the officer's scores and confirmed conditions. */
function EntryTable({
    tenderId,
    bids,
    profile,
    editable,
}: {
    tenderId: string;
    bids: BidRow[];
    profile: Profile;
    editable: boolean;
}) {
    const { t } = useTranslation();
    const activeScores = SCORE_FIELDS.filter(
        ([key]) => profile.weights[key] > 0,
    );
    const activeGates = GATES.filter((gate) => profile.gates[gate]);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    {t('engine.entry.title')}
                </CardTitle>
                <CardDescription>
                    {editable
                        ? t('engine.entry.description')
                        : t('engine.entry.read_only')}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {bids.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('engine.run.no_bids')}
                    </p>
                ) : (
                    bids.map((bid) => (
                        <EntryRow
                            key={bid.id}
                            tenderId={tenderId}
                            bid={bid}
                            scores={activeScores}
                            gates={activeGates}
                            editable={editable}
                        />
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function EntryRow({
    tenderId,
    bid,
    scores,
    gates,
    editable,
}: {
    tenderId: string;
    bid: BidRow;
    scores: ReadonlyArray<readonly [string, keyof BidRow['assessment']]>;
    gates: readonly Gate[];
    editable: boolean;
}) {
    const { t, locale } = useTranslation();
    const initial: Record<string, string> = {};
    scores.forEach(([, field]) => {
        const value = bid.assessment[field];
        initial[field] =
            value === null || value === undefined ? '' : String(value);
    });
    gates.forEach((gate) => {
        const value = bid.assessment.gates[gate].value;
        initial[gate] = value === null ? '' : value ? '1' : '0';
    });

    const form = useForm<Record<string, string>>(initial);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => {
            const out: Record<string, number | boolean | null> = {};
            scores.forEach(
                ([, field]) =>
                    (out[field] =
                        data[field] === '' ? null : Number(data[field])),
            );
            gates.forEach(
                (gate) =>
                    (out[gate] = data[gate] === '' ? null : data[gate] === '1'),
            );

            return out;
        });
        form.put(`/tenders/${tenderId}/engine-evaluation/bids/${bid.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="rounded-md border p-4">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <div className="min-w-0">
                    <p className="font-medium">
                        {bid.vendorName ?? bid.reference}
                    </p>
                    <p className="font-mono text-xs text-muted-foreground">
                        <bdi>{bid.reference}</bdi>
                    </p>
                </div>
                <div className="flex flex-wrap gap-x-6 gap-y-1 text-sm tabular-nums">
                    <span>
                        <bdi>
                            {formatMoney(bid.amount, bid.currency, locale)}
                        </bdi>
                    </span>
                    <span
                        className={cn(
                            bid.durationDays === null &&
                                'text-red-600 dark:text-red-400',
                        )}
                    >
                        {bid.durationDays === null
                            ? t('engine.entry.missing.duration')
                            : t('engine.bid.duration_days', {
                                  days: bid.durationDays,
                              })}
                    </span>
                </div>
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {scores.map(([key, field]) => (
                    <label key={field} className="space-y-1 text-sm">
                        <span className="text-muted-foreground">
                            {t(`engine.criterion.${key}`)}
                        </span>
                        <Input
                            type="number"
                            inputMode="decimal"
                            min={0}
                            max={100}
                            step="0.01"
                            value={form.data[field]}
                            onChange={(event) =>
                                form.setData(field, event.target.value)
                            }
                            disabled={!editable}
                            aria-invalid={Boolean(form.errors[field])}
                            className="tabular-nums"
                        />
                        {form.errors[field] ? (
                            <span className="text-xs text-red-600 dark:text-red-400">
                                {form.errors[field]}
                            </span>
                        ) : null}
                    </label>
                ))}
                {gates.map((gate) => {
                    const attestation = bid.assessment.gates[gate];

                    return (
                        <label key={gate} className="space-y-1 text-sm">
                            <span className="text-muted-foreground">
                                {t(`engine.gate.${gate}`)}
                            </span>
                            <select
                                value={form.data[gate]}
                                onChange={(event) =>
                                    form.setData(gate, event.target.value)
                                }
                                disabled={!editable}
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs disabled:opacity-50"
                            >
                                <option value="">
                                    {t('engine.entry.not_checked')}
                                </option>
                                <option value="1">
                                    {t('engine.entry.yes')}
                                </option>
                                <option value="0">
                                    {t('engine.entry.no')}
                                </option>
                            </select>
                            {attestation.attestedBy &&
                            attestation.attestedAt ? (
                                <span className="block text-xs text-muted-foreground">
                                    {t('engine.entry.attested_by', {
                                        name: attestation.attestedBy,
                                        date: formatDateTime(
                                            attestation.attestedAt,
                                            locale,
                                        ),
                                    })}
                                </span>
                            ) : null}
                        </label>
                    );
                })}
            </div>

            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div className="text-xs text-muted-foreground">
                    {bid.missing.length > 0 ? (
                        <span className="text-amber-700 dark:text-amber-400">
                            {t('engine.entry.missing')}:{' '}
                            {listOf(
                                bid.missing.map((key) => missingLabel(key, t)),
                                locale,
                            )}
                        </span>
                    ) : bid.assessment.scoredBy && bid.assessment.scoredAt ? (
                        t('engine.entry.saved_by', {
                            name: bid.assessment.scoredBy,
                            date: formatDateTime(
                                bid.assessment.scoredAt,
                                locale,
                            ),
                        })
                    ) : null}
                </div>
                {editable ? (
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={form.processing || !form.isDirty}
                    >
                        {t('engine.entry.save')}
                    </Button>
                ) : null}
            </div>
        </form>
    );
}

/** A list joined the way the reader's language joins one — "a, b and c", "أ، ب و ج". */
function listOf(items: string[], locale: string): string {
    try {
        return new Intl.ListFormat(locale, { type: 'conjunction' }).format(
            items,
        );
    } catch {
        return items.join(', ');
    }
}

function missingLabel(
    key: string,
    t: (key: string, r?: Record<string, string | number>) => string,
): string {
    if (key === 'duration') {
        return t('engine.entry.missing.duration');
    }

    return (GATES as readonly string[]).includes(key)
        ? t(`engine.gate.${key}`)
        : t(`engine.criterion.${key}`);
}

/** The engine's ranking: weighted totals, the margin, and what was left out. */
function Results({ result, currency }: { result: Result; currency: string }) {
    const { t, locale } = useTranslation();
    const [open, setOpen] = useState<string | null>(null);
    const max = result.ranking.reduce(
        (m, row) => Math.max(m, row.weightedTotal),
        0,
    );
    const fmt = (n: number | null) =>
        n === null ? '—' : formatDecimal(n, locale); // fixed two places, so totals line up

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                    {t('engine.result.title')}
                    <Badge
                        variant={result.provisional ? 'outline' : 'secondary'}
                    >
                        {t(`engine.status.${result.status}`)}
                    </Badge>
                </CardTitle>
                <CardDescription>
                    {result.finalisedAt
                        ? t('engine.result.finalised', {
                              date: formatDateTime(result.finalisedAt, locale),
                          })
                        : null}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                {result.provisional ? (
                    <Notice
                        icon={AlertTriangle}
                        tone="danger"
                        title={t('engine.result.provisional_title')}
                    >
                        {t('engine.result.provisional_body')}
                    </Notice>
                ) : null}

                {result.marginFlagged &&
                result.margin !== null &&
                !result.provisional ? (
                    <Notice
                        icon={AlertTriangle}
                        tone="warning"
                        title={t('engine.result.margin_flag_title')}
                    >
                        {t('engine.result.margin_flag_body', {
                            margin: fmt(result.margin),
                            threshold: fmt(result.marginBelow),
                        })}
                    </Notice>
                ) : null}

                <p className="text-sm text-muted-foreground">
                    {t('engine.result.arithmetic_note')}
                </p>

                <ol className="space-y-4">
                    {result.ranking.map((row) => (
                        <li key={row.reference}>
                            <button
                                type="button"
                                className="w-full text-start"
                                onClick={() =>
                                    setOpen(
                                        open === row.reference
                                            ? null
                                            : row.reference,
                                    )
                                }
                                aria-expanded={open === row.reference}
                            >
                                <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <span className="flex min-w-0 items-baseline gap-2">
                                        <span className="text-sm text-muted-foreground tabular-nums">
                                            <bdi>{row.rank}</bdi>
                                        </span>
                                        <span className="truncate font-medium">
                                            {row.vendorName}
                                        </span>
                                        {!row.complete ? (
                                            <Badge
                                                variant="outline"
                                                className="shrink-0 border-amber-500/60 font-normal text-amber-700 dark:text-amber-400"
                                            >
                                                {t('engine.result.incomplete')}
                                            </Badge>
                                        ) : null}
                                    </span>
                                    <span className="text-sm font-medium tabular-nums">
                                        <bdi>{fmt(row.weightedTotal)}</bdi>
                                    </span>
                                </div>
                                <div
                                    className="mt-1.5 h-5 w-full overflow-hidden rounded-[2px] bg-muted/50"
                                    aria-hidden="true"
                                >
                                    <div
                                        className={cn(
                                            'h-full rounded-e-[4px] transition-[width]',
                                            row.complete
                                                ? 'bg-[#2a78d6] dark:bg-[#3987e5]'
                                                : 'bg-[#2a78d6]/35 dark:bg-[#3987e5]/35',
                                        )}
                                        style={{
                                            width: `${max > 0 ? (row.weightedTotal / max) * 100 : 0}%`,
                                        }}
                                    />
                                </div>
                            </button>
                            {open === row.reference ? (
                                <Breakdown row={row} />
                            ) : null}
                        </li>
                    ))}
                </ol>

                {/* No margin on a provisional ranking: a gap measured
                    against an incomplete total means nothing, and would
                    read as a decisive lead. */}
                {result.margin !== null &&
                !result.marginFlagged &&
                !result.provisional ? (
                    <p className="text-sm text-muted-foreground">
                        {t('engine.result.margin', {
                            margin: fmt(result.margin),
                        })}
                    </p>
                ) : null}

                {result.notRanked.length > 0 ? (
                    <div className="space-y-2">
                        <h3 className="text-sm font-medium">
                            {t('engine.result.not_ranked_title')}
                        </h3>
                        <ul className="space-y-2">
                            {result.notRanked.map((row) => (
                                <li
                                    key={row.reference}
                                    className="rounded-md border p-3 text-sm"
                                >
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <span className="font-medium">
                                            {row.vendorName}
                                        </span>
                                        {row.outcome ? (
                                            <Badge
                                                variant="outline"
                                                className="font-normal"
                                            >
                                                {t(
                                                    `engine.result.outcome.${row.outcome}`,
                                                )}
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <ul className="mt-1 space-y-0.5 text-muted-foreground">
                                        {row.failedGates.length > 0 ? (
                                            <li>
                                                {t(
                                                    'engine.result.not_ranked.gate_failed',
                                                    {
                                                        gates: listOf(
                                                            row.failedGates.map(
                                                                (g) =>
                                                                    t(
                                                                        `engine.gate.${g}`,
                                                                    ),
                                                            ),
                                                            locale,
                                                        ),
                                                    },
                                                )}
                                            </li>
                                        ) : null}
                                        {row.unresolvedGates.length > 0 ? (
                                            <li>
                                                {t(
                                                    'engine.result.not_ranked.gate_unresolved',
                                                    {
                                                        gates: listOf(
                                                            row.unresolvedGates.map(
                                                                (g) =>
                                                                    t(
                                                                        `engine.gate.${g}`,
                                                                    ),
                                                            ),
                                                            locale,
                                                        ),
                                                    },
                                                )}
                                            </li>
                                        ) : null}
                                        {row.missing.length > 0 ? (
                                            <li>
                                                {t(
                                                    'engine.result.not_ranked.missing',
                                                    {
                                                        criteria: listOf(
                                                            row.missing.map(
                                                                (c) =>
                                                                    t(
                                                                        `engine.criterion.${c}`,
                                                                    ),
                                                            ),
                                                            locale,
                                                        ),
                                                    },
                                                )}
                                            </li>
                                        ) : null}
                                        {row.error ? (
                                            <li>
                                                {t(
                                                    'engine.result.not_ranked.failed',
                                                    { error: row.error },
                                                )}
                                            </li>
                                        ) : null}
                                    </ul>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}

                {result.baseline.priceBest !== null &&
                result.baseline.timelineBest !== null ? (
                    <p className="text-xs text-muted-foreground">
                        {t('engine.result.baseline', {
                            price: formatMoney(
                                result.baseline.priceBest,
                                currency,
                                locale,
                            ),
                            days: formatDecimal(
                                result.baseline.timelineBest,
                                locale,
                                { maximumFractionDigits: 0 },
                            ),
                        })}
                    </p>
                ) : null}
            </CardContent>
        </Card>
    );
}

/** Per-criterion scores behind one weighted total. */
function Breakdown({ row }: { row: RankedRow }) {
    const { t, locale } = useTranslation();
    const fmt = (n: number | null) =>
        n === null ? '—' : formatDecimal(n, locale); // fixed two places, so totals line up

    return (
        <div className="mt-3 overflow-x-auto rounded-md border">
            <table className="w-full text-sm">
                <caption className="sr-only">
                    {t('engine.result.breakdown')}
                </caption>
                <thead className="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th className="px-3 py-2 text-start font-medium">
                            {t('engine.result.breakdown')}
                        </th>
                        <th className="px-3 py-2 text-end font-medium">
                            {t('engine.result.score')}
                        </th>
                        <th className="px-3 py-2 text-end font-medium">
                            {t('engine.result.weight')}
                        </th>
                        <th className="px-3 py-2 text-end font-medium">
                            {t('engine.result.weighted')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {row.criteria.map((c) => (
                        <tr key={c.key} className="border-t">
                            <td className="px-3 py-2">
                                {t(`engine.criterion.${c.key}`)}
                                {c.source ? (
                                    <span className="ms-2 text-xs text-muted-foreground">
                                        ({t(`engine.result.source.${c.source}`)}
                                        )
                                    </span>
                                ) : null}
                            </td>
                            <td className="px-3 py-2 text-end tabular-nums">
                                <bdi>{fmt(c.score)}</bdi>
                            </td>
                            <td className="px-3 py-2 text-end tabular-nums">
                                <bdi>{fmt(c.weightPercent)}%</bdi>
                            </td>
                            <td className="px-3 py-2 text-end font-medium tabular-nums">
                                <bdi>{fmt(c.weighted)}</bdi>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** The engine's order beside the existing evaluation's, bid by bid. */
function Comparison({
    comparison,
}: {
    comparison: NonNullable<Props['comparison']>;
}) {
    const { t, locale } = useTranslation();
    const fmt = (n: number | null) =>
        n === null ? '—' : formatDecimal(n, locale); // fixed two places, so totals line up
    const disagreements = comparison.rows.filter(
        (r) => r.rankDifference !== null && r.rankDifference !== 0,
    ).length;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    {t('engine.compare.title')}
                </CardTitle>
                <CardDescription>
                    {t('engine.compare.description')}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {!comparison.moduleScored ? (
                    <p className="text-sm text-muted-foreground">
                        {t('engine.compare.module_not_scored')}
                    </p>
                ) : (
                    <p
                        className={cn(
                            'text-sm',
                            disagreements > 0
                                ? 'font-medium text-amber-700 dark:text-amber-400'
                                : 'text-muted-foreground',
                        )}
                    >
                        {disagreements > 0
                            ? t('engine.compare.disagreements', {
                                  count: disagreements,
                              })
                            : t('engine.compare.all_agree')}
                    </p>
                )}
                {comparison.moduleScored ? (
                    <p className="text-xs text-muted-foreground">
                        {t('engine.compare.common_set', {
                            count: comparison.comparedCount,
                        })}
                    </p>
                ) : null}
                {/* With nothing scored on the existing side every row is
                    dashes beside a copy of the ranking above — so no table. */}
                {comparison.moduleScored ? (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 text-start font-medium">
                                        {t('engine.result.bid')}
                                    </th>
                                    <th className="px-3 py-2 text-end font-medium">
                                        {t('engine.compare.engine_rank')}
                                    </th>
                                    <th className="px-3 py-2 text-end font-medium">
                                        {t('engine.compare.engine_total')}
                                    </th>
                                    <th className="px-3 py-2 text-end font-medium">
                                        {t('engine.compare.module_rank')}
                                    </th>
                                    <th className="px-3 py-2 text-end font-medium">
                                        {t('engine.compare.module_total')}
                                    </th>
                                    <th className="px-3 py-2 text-start font-medium">
                                        {t('engine.compare.difference')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {comparison.rows.map((row) => {
                                    const differs =
                                        row.rankDifference !== null &&
                                        row.rankDifference !== 0;

                                    return (
                                        <tr
                                            key={row.bidId}
                                            className={cn(
                                                'border-t',
                                                differs && 'bg-amber-500/10',
                                            )}
                                        >
                                            <td className="px-3 py-2">
                                                <span className="font-medium">
                                                    {row.vendorName}
                                                </span>
                                                <span className="ms-2 font-mono text-xs text-muted-foreground">
                                                    <bdi>{row.reference}</bdi>
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums">
                                                <bdi>
                                                    {row.engineRank ?? '—'}
                                                </bdi>
                                                {row.engineComplete ===
                                                false ? (
                                                    <span className="ms-1 text-xs text-amber-700 dark:text-amber-400">
                                                        *
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums">
                                                <bdi>
                                                    {fmt(row.engineTotal)}
                                                </bdi>
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums">
                                                <bdi>
                                                    {row.moduleRank ?? '—'}
                                                </bdi>
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums">
                                                <bdi>
                                                    {fmt(row.moduleTotal)}
                                                </bdi>
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.rankDifference === null
                                                    ? '—'
                                                    : row.rankDifference === 0
                                                      ? t(
                                                            'engine.compare.agree',
                                                        )
                                                      : row.rankDifference > 0
                                                        ? t(
                                                              'engine.compare.engine_higher',
                                                              {
                                                                  n: row.rankDifference,
                                                              },
                                                          )
                                                        : t(
                                                              'engine.compare.engine_lower',
                                                              {
                                                                  n: Math.abs(
                                                                      row.rankDifference,
                                                                  ),
                                                              },
                                                          )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : null}
                {comparison.moduleScored &&
                comparison.rows.some((r) => r.engineComplete === false) ? (
                    <p className="text-xs text-amber-700 dark:text-amber-400">
                        * {t('engine.result.incomplete')}
                    </p>
                ) : null}
            </CardContent>
        </Card>
    );
}

/** Every batch run for this tender, newest first. */
function History({ history }: { history: Evaluation[] }) {
    const { t, locale } = useTranslation();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    {t('engine.history.title')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <ul className="divide-y text-sm">
                    {history.map((evaluation) => (
                        <li
                            key={evaluation.id}
                            className="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <span className="text-muted-foreground">
                                {t('engine.history.submitted', {
                                    date: formatDateTime(
                                        evaluation.submittedAt,
                                        locale,
                                    ),
                                    name: evaluation.submittedBy ?? '—',
                                })}
                                {evaluation.supersedes ? (
                                    <span className="ms-2 font-mono text-xs">
                                        <bdi>
                                            {t('engine.history.supersedes', {
                                                id: evaluation.supersedes,
                                            })}
                                        </bdi>
                                    </span>
                                ) : null}
                            </span>
                            <span className="flex items-center gap-2">
                                <span className="font-mono text-xs text-muted-foreground">
                                    <bdi>{evaluation.batchId}</bdi>
                                </span>
                                <Badge variant="outline">
                                    {t(`engine.status.${evaluation.status}`)}
                                </Badge>
                            </span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
