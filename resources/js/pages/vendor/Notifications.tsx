import { Head, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

type Notif = {
    id: string;
    title_en: string;
    title_ar: string | null;
    body_en: string;
    body_ar: string | null;
    notification_type: string;
    read_at: string | null;
    created_at: string;
    data: Record<string, any> | null;
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
};

function timeAgo(date: string): string {
    const seconds = Math.floor((Date.now() - new Date(date).getTime()) / 1000);
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}

export default function Notifications({ notifications, unreadCount }: Props) {
    const { t } = useTranslation();
    const { locale } = usePage().props as any;
    const isAr = locale === 'ar';

    function markRead(id: string) {
        router.post(`/vendor/notifications/${id}/read`, {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title={t('pages.vendor.notifications')} />
            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Heading title={t('pages.vendor.notifications')} />
                        {unreadCount > 0 && (
                            <Badge variant="destructive">{unreadCount}</Badge>
                        )}
                    </div>
                </div>

                {notifications.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Bell className="mb-4 h-12 w-12 text-muted-foreground" />
                            <p className="text-muted-foreground">{t('empty.no_notifications')}</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {notifications.data.map((notif) => (
                            <Card
                                key={notif.id}
                                className={`transition-colors ${!notif.read_at ? 'border-l-4 border-l-blue-500' : ''}`}
                            >
                                <CardContent className="flex items-start justify-between gap-4 py-4">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium">
                                            {isAr ? (notif.title_ar || notif.title_en) : notif.title_en}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {isAr ? (notif.body_ar || notif.body_en) : notif.body_en}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {timeAgo(notif.created_at)}
                                        </p>
                                    </div>
                                    {!notif.read_at && (
                                        <Button variant="ghost" size="sm" onClick={() => markRead(notif.id)}>
                                            <CheckCheck className="h-4 w-4" />
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {notifications.last_page > 1 && (
                    <div className="flex justify-center gap-1">
                        {notifications.links.map((link, idx) => (
                            <Button
                                key={idx}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
