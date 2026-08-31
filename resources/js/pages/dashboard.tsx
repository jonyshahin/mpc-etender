import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Building2,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    FileText,
    Gavel,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { BarList } from '@/components/dashboard/BarList';
import type { BarDatum } from '@/components/dashboard/BarList';
import { StatTile } from '@/components/dashboard/StatTile';
import { TrendArea } from '@/components/dashboard/TrendArea';
import type { TrendPoint } from '@/components/dashboard/TrendArea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDeadline } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type AttentionItem = {
    key: string;
    count: number;
    href: string;
    tone: 'critical' | 'warning' | 'default';
};

type ClosingTender = {
    id: string;
    reference_number: string;
    title: string;
    project: string | null;
    submission_deadline: string;
    bids_count: number;
};

type Props = {
    dashboard: {
        headline: {
            active_tenders: number;
            bids_received: number;
            qualified_vendors: number;
            total_vendors: number;
            awarded_value: number;
            savings_rate: number | null;
        };
        attention: AttentionItem[];
        statusDistribution: Array<{ status: string; count: number }>;
        awardTrend: TrendPoint[];
        closingSoon: ClosingTender[];
    };
};

/** Icons are decorative; every tile and row is labelled in text. */
const ATTENTION_ICONS: Record<string, LucideIcon> = {
    approvals: Gavel,
    evaluations: ClipboardCheck,
    vendor_documents: FileText,
    category_requests: Building2,
    vendor_prequalification: Users,
};

/**
 * Status colours are reserved for state and never reused as series colours, and
 * they always ship with an icon and a label so the meaning never rests on hue
 * alone.
 */
const TONES: Record<AttentionItem['tone'], { ring: string; text: string }> = {
    critical: { ring: 'ring-[#d03b3b]/30', text: 'text-[#d03b3b] dark:text-[#e66767]' },
    warning: { ring: 'ring-[#fab219]/35', text: 'text-[#a8792e] dark:text-[#fab219]' },
    default: { ring: 'ring-border', text: 'text-foreground' },
};

function useCurrency() {
    const { locale } = useTranslation();

    return (amount: number) => {
        // Compact above six figures: a dashboard tile is scanned, not audited,
        // and "$1.2M" is read faster than "$1,234,567".
        const compact = Math.abs(amount) >= 100_000;

        return new Intl.NumberFormat(locale === 'ar' ? 'ar' : 'en', {
            style: 'currency',
            currency: 'USD',
            notation: compact ? 'compact' : 'standard',
            maximumFractionDigits: compact ? 1 : 0,
        }).format(amount);
    };
}

export default function Dashboard({ dashboard: data }: Props) {
    const { t, locale } = useTranslation();
    const money = useCurrency();
    const page = usePage();
    const userName = (page.props as { auth?: { user?: { name?: string } } }).auth?.user?.name;

    const { headline, attention, statusDistribution, awardTrend, closingSoon } = data;

    const statusBars: BarDatum[] = statusDistribution.map((row) => ({
        key: row.status,
        label: t(`status.${row.status}`),
        value: row.count,
    }));

    // Only queues with something in them are worth a card; an empty one is
    // noise on a page whose job is telling you where to go next.
    const actionable = attention.filter((item) => item.count > 0);

    return (
        <>
            <Head title={t('pages.dashboard.title')} />

            <div className="space-y-6 p-4 md:p-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {userName
                            ? t('dashboard.greeting_named', { name: userName })
                            : t('pages.dashboard.title')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('dashboard.subtitle')}
                    </p>
                </header>

                <section aria-label={t('dashboard.summary')}>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatTile
                            label={t('dashboard.active_tenders')}
                            value={String(headline.active_tenders)}
                            hint={t('dashboard.open_for_bidding')}
                            icon={FileText}
                        />
                        <StatTile
                            label={t('dashboard.bids_received')}
                            value={String(headline.bids_received)}
                            hint={t('dashboard.across_all_tenders')}
                            icon={Gavel}
                        />
                        <StatTile
                            label={t('dashboard.qualified_vendors')}
                            value={String(headline.qualified_vendors)}
                            hint={t('dashboard.of_total_registered', {
                                total: headline.total_vendors,
                            })}
                            icon={Users}
                        />
                        <StatTile
                            label={t('dashboard.awarded_value')}
                            value={money(headline.awarded_value)}
                            hint={
                                headline.savings_rate === null
                                    ? t('dashboard.no_awards_yet')
                                    : t('dashboard.saved_against_estimate', {
                                          percent: headline.savings_rate,
                                      })
                            }
                            icon={CheckCircle2}
                            emphasis
                        />
                    </div>
                </section>

                {actionable.length > 0 && (
                    <section aria-label={t('dashboard.needs_attention')}>
                        <h2 className="mb-3 text-sm font-semibold">
                            {t('dashboard.needs_attention')}
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {actionable.map((item) => {
                                const Icon = ATTENTION_ICONS[item.key] ?? AlertTriangle;
                                const tone = TONES[item.tone];

                                return (
                                    <Link
                                        key={item.key}
                                        href={item.href}
                                        className={cn(
                                            'group flex items-center justify-between gap-3 rounded-xl border bg-card p-4 ring-1 ring-inset transition-colors hover:bg-accent',
                                            tone.ring,
                                        )}
                                    >
                                        <span className="flex items-center gap-3">
                                            <Icon
                                                className={cn('size-5 shrink-0', tone.text)}
                                                aria-hidden="true"
                                            />
                                            <span>
                                                <span className="block text-lg font-semibold tabular-nums">
                                                    {item.count}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {t(`dashboard.attention.${item.key}`)}
                                                </span>
                                            </span>
                                        </span>
                                        <ArrowRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5" />
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                )}

                <div className="grid gap-4 lg:grid-cols-5">
                    <Card className="lg:col-span-3">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('dashboard.award_trend')}
                            </CardTitle>
                            <CardDescription>{t('dashboard.last_12_months')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <TrendArea
                                data={awardTrend}
                                formatValue={money}
                                seriesLabel={t('dashboard.award_trend')}
                                emptyLabel={t('dashboard.no_awards_in_period')}
                            />
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('dashboard.tender_pipeline')}
                            </CardTitle>
                            <CardDescription>{t('dashboard.by_stage')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BarList
                                data={statusBars}
                                unit={t('dashboard.tenders').toLowerCase()}
                                emptyLabel={t('dashboard.no_tenders_yet')}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-3">
                        <div>
                            <CardTitle className="text-base">
                                {t('dashboard.closing_soon')}
                            </CardTitle>
                            <CardDescription>{t('dashboard.next_seven_days')}</CardDescription>
                        </div>
                        <CalendarClock className="size-4 text-muted-foreground" aria-hidden="true" />
                    </CardHeader>
                    <CardContent>
                        {closingSoon.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                {t('dashboard.nothing_closing')}
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {closingSoon.map((tender) => (
                                    <li key={tender.id}>
                                        <Link
                                            href={`/tenders/${tender.id}`}
                                            className="-mx-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-md px-2 py-3 transition-colors hover:bg-accent"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-medium">
                                                    {tender.title}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {tender.reference_number}
                                                    {tender.project && ` · ${tender.project}`}
                                                    {' · '}
                                                    {t('dashboard.bids_count', {
                                                        count: tender.bids_count,
                                                    })}
                                                </span>
                                            </span>
                                            {/* The zone is named: a deadline a bid
                                                can be rejected for missing must not
                                                depend on the reader's own clock. */}
                                            <span className="shrink-0 text-xs font-medium tabular-nums text-muted-foreground">
                                                {formatDeadline(tender.submission_deadline, locale)}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
