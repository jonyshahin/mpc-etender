import { Head, router } from '@inertiajs/react';
import { BellOff, CheckCheck } from 'lucide-react';
import { Pagination } from '@/components/Pagination';
import { Button } from '@/components/ui/button';
import { useNow } from '@/hooks/use-now';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/datetime';
import { relativeTime } from '@/lib/relative-time';
import { cn } from '@/lib/utils';

type Notif = {
    id: string;
    notification_type: string;
    title_en: string;
    title_ar: string | null;
    body_en: string;
    body_ar: string | null;
    read_at: string | null;
    created_at: string;
};

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    notifications: PaginatedData<Notif>;
    unreadCount: number;
    filters: { unread: boolean };
};

export default function Notifications({ notifications, unreadCount, filters }: Props) {
    const { t, locale } = useTranslation();
    const isAr = locale === 'ar';

    // One instant for the whole list: sampling per row lets two notifications
    // written in the same second report different ages.
    const now = useNow(60_000);

    const markRead = (id: string) =>
        router.post(`/vendor/notifications/${id}/read`, {}, { preserveScroll: true });

    const markAllRead = () =>
        router.post('/vendor/notifications/read-all', {}, { preserveScroll: true });

    const setFilter = (unread: boolean) =>
        router.get(
            '/vendor/notifications',
            unread ? { unread: 1 } : {},
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title={t('pages.vendor.notifications')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.vendor.notifications')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {unreadCount > 0
                                ? t('vendor.unread_notifications', { count: unreadCount })
                                : t('vendor.all_caught_up')}
                        </p>
                    </div>
                    {unreadCount > 0 && (
                        <Button variant="outline" onClick={markAllRead}>
                            <CheckCheck className="me-2 size-4" aria-hidden="true" />
                            {t('btn.mark_all_read')}
                        </Button>
                    )}
                </div>

                <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                    <FilterTab
                        label={t('btn.filter_all')}
                        count={notifications.total}
                        active={!filters.unread}
                        onSelect={() => setFilter(false)}
                    />
                    <FilterTab
                        label={t('notifications.unread')}
                        count={unreadCount}
                        active={filters.unread}
                        onSelect={() => setFilter(true)}
                    />
                </div>

                {notifications.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <BellOff
                            className="mx-auto size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">
                            {filters.unread
                                ? t('vendor.all_caught_up')
                                : t('empty.no_notifications')}
                        </p>
                        <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                            {filters.unread
                                ? t('vendor.no_unread_hint')
                                : t('empty.no_notifications_hint')}
                        </p>
                        {filters.unread && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="mt-4"
                                onClick={() => setFilter(false)}
                            >
                                {t('btn.filter_all')}
                            </Button>
                        )}
                    </div>
                ) : (
                    <ul className="space-y-2">
                        {notifications.data.map((notif) => {
                            const unread = !notif.read_at;

                            return (
                                <li
                                    key={notif.id}
                                    className={cn(
                                        // border-s, not border-l: under dir="rtl"
                                        // the unread marker belonged on the
                                        // right and sat on the left.
                                        'rounded-xl border bg-card p-4 transition-colors',
                                        unread && 'border-s-4 border-s-primary bg-primary/[0.03]',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p
                                                className={cn(
                                                    'text-sm',
                                                    unread ? 'font-semibold' : 'font-medium',
                                                )}
                                            >
                                                {(isAr && notif.title_ar) || notif.title_en}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {(isAr && notif.body_ar) || notif.body_en}
                                            </p>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {/* Absolute date as the title so
                                                    "3 days ago" is checkable. */}
                                                <time
                                                    dateTime={notif.created_at}
                                                    title={formatDateTime(notif.created_at, locale)}
                                                >
                                                    {relativeTime(notif.created_at, now, t)}
                                                </time>
                                            </p>
                                        </div>
                                        {unread && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                aria-label={t('btn.mark_read')}
                                                title={t('btn.mark_read')}
                                                onClick={() => markRead(notif.id)}
                                            >
                                                <CheckCheck className="size-4" aria-hidden="true" />
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}

                <Pagination links={notifications.links} />
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
                'inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors',
                active
                    ? 'border-transparent bg-primary text-primary-foreground'
                    : 'hover:bg-accent',
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
