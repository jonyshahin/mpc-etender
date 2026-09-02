import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarClock,
    ClipboardList,
    FileSignature,
    Layers,
    Search,
    Timer,
    X,
} from 'lucide-react';
import { StatTile } from '@/components/dashboard/StatTile';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedFilters } from '@/hooks/use-debounced-filters';
import { useTranslation } from '@/hooks/use-translation';
import { formatDeadline } from '@/lib/datetime';
import { DEADLINE_TONE_CLASS, deadlineStatus } from '@/lib/deadline';
import { localized } from '@/lib/locales';
import { cn } from '@/lib/utils';

type TenderCard = {
    id: string;
    reference_number: string;
    title_en: string;
    title_ar: string | null;
    status: string;
    submission_deadline: string;
    is_two_envelope: boolean;
    project: { id: string; name: string; name_ar: string | null } | null;
    categories: Array<{ id: string; name_en: string; name_ar: string | null }>;
    /** This vendor's own bid on this tender, or null. Never a rival's. */
    my_bid: { id: string; status: string; is_editable: boolean } | null;
};

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Filters = {
    search: string | null;
    window: string | null;
    sort: string;
    direction: string;
};

type Props = {
    tenders: PaginatedData<TenderCard>;
    filters: Filters;
    summary: {
        total: number;
        open: number;
        closing_soon: number;
        bid_started: number;
    };
};

const WINDOWS = [
    { value: 'open', labelKey: 'tender.window_open', countKey: 'open' },
    { value: 'closing_soon', labelKey: 'tender.window_closing_soon', countKey: 'closing_soon' },
    { value: 'bid_started', labelKey: 'tender.window_bid_started', countKey: 'bid_started' },
] as const;

/**
 * Published tenders in the vendor's own categories.
 */
export default function Browse({ tenders, filters, summary }: Props) {
    const { t, locale } = useTranslation();
    // One shared hook rather than a copy per page: every navigation has to
    // carry the whole filter set, and typing has to search on its own after
    // a pause. Three pages were getting both right independently.
    const { search, setSearch, navigate } = useDebouncedFilters('/vendor/tenders', filters);

    const hasFilters = Boolean(filters.search || filters.window);

    const clearAll = () => {
        setSearch('');
        navigate({ search: undefined, window: undefined });
    };

    return (
        <>
            <Head title={t('pages.vendor.browse_tenders')} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.vendor.browse_tenders')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('pages.vendor.browse_tenders_description')}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/vendor/bids">
                            <FileSignature className="me-2 size-4" />
                            {t('nav.my_bids')}
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('tender.open_to_you')}
                        value={String(summary.total)}
                        hint={t('tender.in_your_categories')}
                        icon={ClipboardList}
                    />
                    <StatTile
                        label={t('status.published')}
                        value={String(summary.open)}
                        hint={t('tender.accepting_bids')}
                        icon={Layers}
                    />
                    {/* Worth its own tile: a deadline is what a bid gets
                        rejected for missing. */}
                    <StatTile
                        label={t('tender.closing_soon')}
                        value={String(summary.closing_soon)}
                        hint={t('tender.within_seven_days')}
                        icon={Timer}
                    />
                    <StatTile
                        label={t('tender.bid_started')}
                        value={String(summary.bid_started)}
                        hint={t('tender.you_have_a_bid')}
                        icon={FileSignature}
                    />
                </div>

                <div className="space-y-3">
                    <div className="relative max-w-sm">
                        <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder={t('form.search_tenders')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="ps-9"
                            aria-label={t('form.search_tenders')}
                        />
                    </div>

                    <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                        <FilterTab
                            label={t('tender.window_all')}
                            count={summary.total}
                            active={!filters.window}
                            onSelect={() => navigate({ window: undefined })}
                        />
                        {WINDOWS.map((option) => (
                            <FilterTab
                                key={option.value}
                                label={t(option.labelKey)}
                                count={summary[option.countKey]}
                                active={filters.window === option.value}
                                onSelect={() => navigate({ window: option.value })}
                            />
                        ))}
                    </div>
                </div>

                {tenders.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <ClipboardList
                            className="mx-auto size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">
                            {hasFilters ? t('tender.no_matches') : t('tender.none_matched_categories')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {hasFilters ? t('tender.try_clearing') : t('tender.appear_when_published')}
                        </p>
                        {hasFilters && (
                            <Button variant="outline" className="mt-4" onClick={clearAll}>
                                <X className="me-2 size-4" />
                                {t('tender.clear_filters')}
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {tenders.data.map((tender) => {
                                const status = deadlineStatus(tender.submission_deadline);

                                return (
                                    <li
                                        key={tender.id}
                                        className="flex flex-col rounded-xl border bg-card p-5"
                                    >
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {tender.reference_number}
                                        </p>
                                        <h2 className="mt-1 text-base font-semibold leading-snug">
                                            <Link
                                                href={`/vendor/tenders/${tender.id}`}
                                                className="hover:underline"
                                            >
                                                {localized(locale, tender.title_en, tender.title_ar)}
                                            </Link>
                                        </h2>

                                        {tender.project && (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {localized(
                                                    locale,
                                                    tender.project.name,
                                                    tender.project.name_ar,
                                                )}
                                            </p>
                                        )}

                                        <div className="mt-3 space-y-1">
                                            <p className="flex items-center gap-1.5 text-sm">
                                                <CalendarClock
                                                    className="size-3.5 shrink-0 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                {/* formatDeadline, not formatDate: this is
                                                    the moment a bid can be rejected for
                                                    missing, so it names its zone. */}
                                                {formatDeadline(tender.submission_deadline, locale)}
                                            </p>
                                            {status && (
                                                <p
                                                    className={cn(
                                                        'text-xs',
                                                        DEADLINE_TONE_CLASS[status.tone],
                                                    )}
                                                >
                                                    {t(status.labelKey, { count: status.days })}
                                                </p>
                                            )}
                                        </div>

                                        {tender.categories.length > 0 && (
                                            <div className="mt-3 flex flex-wrap gap-1">
                                                {tender.categories.map((category) => (
                                                    <Badge
                                                        key={category.id}
                                                        variant="secondary"
                                                        className="text-xs font-normal"
                                                    >
                                                        {localized(
                                                            locale,
                                                            category.name_en,
                                                            category.name_ar,
                                                        )}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}

                                        <div className="mt-auto flex items-center gap-2 pt-4">
                                            <Button asChild variant="outline" className="flex-1">
                                                <Link href={`/vendor/tenders/${tender.id}`}>
                                                    {t('btn.view_details')}
                                                    {/* Logical: the arrow points at the
                                                        end of the line, which flips under
                                                        dir="rtl". */}
                                                    <ArrowRight className="ms-2 size-4 rtl:rotate-180" />
                                                </Link>
                                            </Button>
                                            {tender.my_bid && (
                                                <Button asChild variant="secondary">
                                                    <Link href={`/vendor/bids/${tender.my_bid.id}`}>
                                                        {t(
                                                            tender.my_bid.is_editable
                                                                ? 'vendor.tender.continue_bid'
                                                                : 'vendor.tender.view_bid',
                                                        )}
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>

                        {tenders.last_page > 1 && (
                            <nav className="flex flex-wrap items-center justify-center gap-1">
                                {tenders.links.map((link, index) => (
                                    <Button
                                        key={index}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        asChild={Boolean(link.url)}
                                    >
                                        {link.url ? (
                                            <Link
                                                href={link.url}
                                                preserveState
                                                preserveScroll
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ) : (
                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                        )}
                                    </Button>
                                ))}
                            </nav>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

function FilterTab({
    label,
    count,
    active,
    onSelect,
}: {
    label: string;
    count: number;
    active: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={active}
            className={cn(
                'flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors',
                active
                    ? 'border-transparent bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted',
            )}
        >
            {label}
            <span
                className={cn(
                    'rounded-full px-1.5 text-xs tabular-nums',
                    active ? 'bg-primary-foreground/20' : 'bg-muted',
                )}
            >
                {count}
            </span>
        </button>
    );
}
