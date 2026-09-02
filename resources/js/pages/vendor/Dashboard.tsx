import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Bell,
    ClipboardList,
    Clock,
    FileText,
    Gavel,
    Tags,
} from 'lucide-react';
import { BarList } from '@/components/dashboard/BarList';
import { StatTile } from '@/components/dashboard/StatTile';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useNow } from '@/hooks/use-now';
import { useTranslation } from '@/hooks/use-translation';
import { countdownTo, isUrgent  } from '@/lib/countdown';
import type {Countdown} from '@/lib/countdown';
import { formatDate, formatDeadline } from '@/lib/datetime';
import { cn } from '@/lib/utils';

type ExpiringDocument = {
    id: string;
    title: string;
    document_type: string;
    expiry_date: string;
};

type OpenTender = {
    id: string;
    title_en: string;
    title_ar: string | null;
    reference_number: string;
    submission_deadline: string;
};

type RecentBid = {
    id: string;
    tender_id: string;
    status: string;
    submitted_at: string | null;
    tender?: { id: string; title_en: string; title_ar: string | null; reference_number: string };
};

type Props = {
    vendor: {
        company_name: string;
        company_name_ar: string | null;
        prequalification_status: string;
        qualified_at: string | null;
    };
    summary: {
        open_tenders: number;
        active_bids: number;
        documents_needing_attention: number;
        categories: number;
        unread_notifications: number;
    };
    documentBreakdown: Array<{ status: string; count: number }>;
    documentWarnings: ExpiringDocument[];
    expiredDocuments: ExpiringDocument[];
    openTenders: OpenTender[];
    recentBids: RecentBid[];
};

/**
 * What a vendor in this standing can and cannot do, in their own words.
 *
 * The page previously spoke to two of the six statuses. A suspended or
 * blacklisted vendor saw a grey pill and no explanation, and an under_review
 * vendor saw nothing at all — so the most anxious readers of this page got the
 * least from it.
 */
const STANDING_TONE: Record<string, 'ok' | 'warn' | 'stop'> = {
    qualified: 'ok',
    pending: 'warn',
    under_review: 'warn',
    rejected: 'stop',
    suspended: 'stop',
    blacklisted: 'stop',
};

export default function Dashboard({
    vendor,
    summary,
    documentBreakdown,
    documentWarnings,
    expiredDocuments,
    openTenders,
    recentBids,
}: Props) {
    const { t, locale } = useTranslation();
    const isAr = locale === 'ar';

    const companyName = (isAr && vendor.company_name_ar) || vendor.company_name;
    const tenderTitle = (tender: { title_en: string; title_ar: string | null }) =>
        (isAr && tender.title_ar) || tender.title_en;

    // One instant for every countdown on the page, and one that keeps moving:
    // sampling per row lets two rows in the same render disagree about where a
    // boundary fell, and a static sample leaves a tab open overnight insisting
    // a closed tender is still hours from its deadline.
    const now = useNow(30_000);

    const tone = STANDING_TONE[vendor.prequalification_status] ?? 'warn';
    const needsAttention = documentWarnings.length + expiredDocuments.length;

    return (
        <>
            <Head title={t('pages.vendor.dashboard')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('vendor.welcome_name', { name: companyName })}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('vendor.dashboard_description')}
                    </p>
                </div>

                {/* ── Standing ── */}
                <Card
                    className={cn(
                        'border-s-4',
                        tone === 'ok' && 'border-s-emerald-500',
                        tone === 'warn' && 'border-s-amber-500',
                        tone === 'stop' && 'border-s-destructive',
                    )}
                >
                    <CardContent className="flex flex-wrap items-start justify-between gap-4 p-5">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                                    {t('vendor.prequalification_status')}
                                </h2>
                                <StatusBadge status={vendor.prequalification_status} />
                            </div>
                            <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                                {t(`vendor.standing.${vendor.prequalification_status}`)}
                            </p>
                            {vendor.qualified_at && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t('vendor.qualified_on')} {formatDate(vendor.qualified_at, locale)}
                                </p>
                            )}
                        </div>
                        {summary.unread_notifications > 0 && (
                            <Button asChild variant="outline" size="sm">
                                <Link href="/vendor/notifications">
                                    <Bell className="me-2 size-4" />
                                    {t('vendor.unread_notifications', {
                                        count: summary.unread_notifications,
                                    })}
                                </Link>
                            </Button>
                        )}
                    </CardContent>
                </Card>

                {/* ── Headline figures ── */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('vendor.open_tenders')}
                        value={String(summary.open_tenders)}
                        hint={t('vendor.matching_your_categories')}
                        icon={ClipboardList}
                        emphasis
                    />
                    <StatTile
                        label={t('vendor.active_bids')}
                        value={String(summary.active_bids)}
                        hint={t('vendor.in_progress_or_submitted')}
                        icon={Gavel}
                    />
                    <StatTile
                        label={t('vendor.documents_needing_attention')}
                        value={String(summary.documents_needing_attention)}
                        hint={t('vendor.expiring_expired_or_rejected')}
                        icon={FileText}
                    />
                    <StatTile
                        label={t('nav.categories')}
                        value={String(summary.categories)}
                        hint={t('vendor.approved_by_mpc')}
                        icon={Tags}
                    />
                </div>

                {/* ── Paperwork alerts ── */}
                {needsAttention > 0 && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {expiredDocuments.length > 0 && (
                            <DocumentAlert
                                tone="stop"
                                icon={AlertCircle}
                                title={t('vendor.expired_documents')}
                                documents={expiredDocuments}
                                describe={(doc) =>
                                    `${t('vendor.expired')} ${formatDate(doc.expiry_date, locale)}`
                                }
                            />
                        )}
                        {documentWarnings.length > 0 && (
                            <DocumentAlert
                                tone="warn"
                                icon={AlertTriangle}
                                title={t('vendor.documents_expiring_soon')}
                                documents={documentWarnings}
                                describe={(doc) =>
                                    `${t('vendor.expires')} ${formatDate(doc.expiry_date, locale)}`
                                }
                            />
                        )}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* ── Open tenders ── */}
                    <section className="space-y-3 lg:col-span-2">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <ClipboardList className="size-5" aria-hidden="true" />
                                {t('vendor.open_tenders')}
                            </h2>
                            {openTenders.length > 0 && (
                                <Button asChild variant="ghost" size="sm">
                                    <Link href="/vendor/tenders">
                                        {t('btn.view_all')}
                                        <ArrowRight className="ms-1 size-4 rtl:rotate-180" />
                                    </Link>
                                </Button>
                            )}
                        </div>

                        {openTenders.length === 0 ? (
                            <EmptyState
                                icon={ClipboardList}
                                title={t('empty.no_open_tenders')}
                                body={
                                    summary.categories === 0
                                        ? t('empty.no_open_tenders_no_categories')
                                        : t('empty.no_open_tenders_hint')
                                }
                                action={
                                    summary.categories === 0 ? (
                                        <Button asChild variant="outline" size="sm" className="mt-4">
                                            <Link href="/vendor/categories">
                                                <Tags className="me-2 size-4" />
                                                {t('nav.categories')}
                                            </Link>
                                        </Button>
                                    ) : null
                                }
                            />
                        ) : (
                            <ul className="grid gap-3 sm:grid-cols-2">
                                {openTenders.map((tender) => {
                                    const countdown = countdownTo(tender.submission_deadline, now);

                                    return (
                                        <li key={tender.id}>
                                            <Link
                                                href={`/vendor/tenders/${tender.id}`}
                                                className="flex h-full flex-col rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                                            >
                                                <span className="line-clamp-2 font-medium">
                                                    {tenderTitle(tender)}
                                                </span>
                                                <span className="mt-1 block font-mono text-xs text-muted-foreground">
                                                    {tender.reference_number}
                                                </span>
                                                <span className="mt-auto flex items-center gap-1.5 pt-3 text-xs">
                                                    <Clock
                                                        className={cn(
                                                            'size-3.5 shrink-0',
                                                            isUrgent(countdown)
                                                                ? 'text-destructive'
                                                                : 'text-muted-foreground',
                                                        )}
                                                        aria-hidden="true"
                                                    />
                                                    <CountdownLabel countdown={countdown} />
                                                </span>
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    {formatDeadline(tender.submission_deadline, locale)}
                                                </span>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </section>

                    {/* ── Documents on file ── */}
                    <section className="space-y-3">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <FileText className="size-5" aria-hidden="true" />
                            {t('vendor.documents_on_file')}
                        </h2>
                        <Card>
                            <CardContent className="p-5">
                                <BarList
                                    data={documentBreakdown.map((row) => ({
                                        key: row.status,
                                        label: t(`status.${row.status}`),
                                        value: row.count,
                                    }))}
                                    unit={t('nav.documents').toLowerCase()}
                                    emptyLabel={t('empty.no_documents')}
                                />
                                <Button asChild variant="outline" size="sm" className="mt-4 w-full">
                                    <Link href="/vendor/documents">
                                        {t('vendor.manage_documents')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </section>
                </div>

                {/* ── Recent bids ── */}
                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <Gavel className="size-5" aria-hidden="true" />
                            {t('vendor.recent_bids')}
                        </h2>
                        {recentBids.length > 0 && (
                            <Button asChild variant="ghost" size="sm">
                                <Link href="/vendor/bids">
                                    {t('btn.view_all')}
                                    <ArrowRight className="ms-1 size-4 rtl:rotate-180" />
                                </Link>
                            </Button>
                        )}
                    </div>

                    {recentBids.length === 0 ? (
                        <EmptyState
                            icon={Gavel}
                            title={t('empty.no_bids_submitted')}
                            body={t('empty.no_bids_submitted_hint')}
                        />
                    ) : (
                        <>
                            {/* Four columns do not survive a phone; the same
                                rows render as cards below md. */}
                            <ul className="space-y-2 md:hidden">
                                {recentBids.map((bid) => (
                                    <li key={bid.id}>
                                        <Link
                                            href={`/vendor/bids/${bid.id}`}
                                            className="block rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <span className="min-w-0">
                                                    <span className="block truncate font-medium">
                                                        {bid.tender ? tenderTitle(bid.tender) : '—'}
                                                    </span>
                                                    <span className="mt-0.5 block truncate font-mono text-xs text-muted-foreground">
                                                        {bid.tender?.reference_number ?? '—'}
                                                    </span>
                                                </span>
                                                <span className="shrink-0">
                                                    <StatusBadge status={bid.status} />
                                                </span>
                                            </div>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {bid.submitted_at
                                                    ? `${t('table.submitted')}: ${formatDate(bid.submitted_at, locale)}`
                                                    : t('status.not_submitted')}
                                            </p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden overflow-hidden rounded-xl border md:block">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50 text-start">
                                            <th className="px-4 py-3 text-start font-medium text-muted-foreground">
                                                {t('table.tender')}
                                            </th>
                                            <th className="px-4 py-3 text-start font-medium text-muted-foreground">
                                                {t('table.reference')}
                                            </th>
                                            <th className="px-4 py-3 text-start font-medium text-muted-foreground">
                                                {t('table.status')}
                                            </th>
                                            <th className="px-4 py-3 text-start font-medium text-muted-foreground">
                                                {t('table.submitted')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentBids.map((bid) => (
                                            <tr
                                                key={bid.id}
                                                className="border-b transition-colors last:border-0 hover:bg-muted/50"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={`/vendor/bids/${bid.id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {bid.tender ? tenderTitle(bid.tender) : '—'}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                    {bid.tender?.reference_number ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge status={bid.status} />
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {bid.submitted_at
                                                        ? formatDate(bid.submitted_at, locale)
                                                        : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </section>
            </div>
        </>
    );
}

function CountdownLabel({ countdown }: { countdown: Countdown }) {
    const { t } = useTranslation();

    if (countdown.state === 'expired') {
        return <span className="text-destructive">{t('vendor.deadline_passed')}</span>;
    }

    // <bdi> around the whole phrase: it mixes translated words with Latin
    // digits and unit letters, which bidi reordering pulls apart under RTL.
    return (
        <bdi className={countdown.state === 'hours' ? 'text-destructive' : 'text-muted-foreground'}>
            {countdown.state === 'days'
                ? t('vendor.remaining_days', { days: countdown.days, hours: countdown.hours })
                : t('vendor.remaining_hours', {
                      hours: countdown.hours,
                      minutes: countdown.minutes,
                  })}
        </bdi>
    );
}

function DocumentAlert({
    tone,
    icon: Icon,
    title,
    documents,
    describe,
}: {
    tone: 'warn' | 'stop';
    icon: typeof AlertTriangle;
    title: string;
    documents: ExpiringDocument[];
    describe: (doc: ExpiringDocument) => string;
}) {
    const { t } = useTranslation();

    return (
        <div
            className={cn(
                'rounded-xl border p-4',
                tone === 'stop'
                    ? 'border-destructive/40 bg-destructive/5'
                    : 'border-amber-500/40 bg-amber-50 dark:bg-amber-950/20',
            )}
        >
            <div className="flex items-center gap-2">
                <Icon
                    className={cn(
                        'size-4 shrink-0',
                        tone === 'stop' ? 'text-destructive' : 'text-amber-600 dark:text-amber-400',
                    )}
                    aria-hidden="true"
                />
                <h3
                    className={cn(
                        'text-sm font-semibold',
                        tone === 'stop' ? 'text-destructive' : 'text-amber-900 dark:text-amber-200',
                    )}
                >
                    {title}
                </h3>
            </div>
            <ul className="mt-2 space-y-1">
                {documents.map((doc) => (
                    <li key={doc.id} className="flex flex-wrap items-baseline gap-x-2 text-sm">
                        <span className="font-medium">{doc.title}</span>
                        <span className="text-xs text-muted-foreground">{describe(doc)}</span>
                    </li>
                ))}
            </ul>
            <Button asChild variant="outline" size="sm" className="mt-3">
                <Link href="/vendor/documents">{t('vendor.manage_documents')}</Link>
            </Button>
        </div>
    );
}

function EmptyState({
    icon: Icon,
    title,
    body,
    action,
}: {
    icon: typeof ClipboardList;
    title: string;
    body: string;
    action?: React.ReactNode;
}) {
    return (
        <div className="rounded-xl border border-dashed py-12 text-center">
            <Icon className="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
            <p className="mt-3 font-medium">{title}</p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">{body}</p>
            {action}
        </div>
    );
}
