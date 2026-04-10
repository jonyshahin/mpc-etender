import { Head, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck, Clock, Mail, MailOpen } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

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

function relativeTime(dateStr: string): string {
    const now = new Date();
    const date = new Date(dateStr);
    const diffMs = now.getTime() - date.getTime();
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'Just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `${diffHr}h ago`;
    const diffDays = Math.floor(diffHr / 24);
    if (diffDays < 30) return `${diffDays}d ago`;
    return date.toLocaleDateString();
}

function typeBadgeColor(type: string): string {
    const colors: Record<string, string> = {
        tender: 'bg-blue-100 text-blue-800',
        bid: 'bg-green-100 text-green-800',
        evaluation: 'bg-purple-100 text-purple-800',
        approval: 'bg-yellow-100 text-yellow-800',
        system: 'bg-gray-100 text-gray-800',
    };
    return colors[type] || 'bg-gray-100 text-gray-800';
}

export default function Index({ notifications, unreadCount }: Props) {
    const { props } = usePage();
    const locale = (props as any).locale ?? 'en';
    const isAr = locale === 'ar';

    function getTitle(n: Notif): string {
        if (isAr && n.title_ar) return n.title_ar;
        return n.title_en;
    }

    function getBody(n: Notif): string {
        if (isAr && n.body_ar) return n.body_ar;
        return n.body_en;
    }

    function markAllRead() {
        router.post('/notifications/mark-all-read');
    }

    function markRead(id: string) {
        router.post(`/notifications/${id}/read`);
    }

    return (
        <>
            <Head title="Notifications" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Heading title="Notifications" />
                        {unreadCount > 0 && (
                            <Badge variant="default" className="bg-blue-600 text-white">
                                {unreadCount} unread
                            </Badge>
                        )}
                    </div>
                    {unreadCount > 0 && (
                        <Button variant="outline" onClick={markAllRead} className="gap-2">
                            <CheckCheck className="h-4 w-4" />
                            Mark All Read
                        </Button>
                    )}
                </div>

                {notifications.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Bell className="h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-muted-foreground">No notifications yet.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {notifications.data.map((n) => (
                            <Card
                                key={n.id}
                                className={`transition-colors ${
                                    !n.read_at
                                        ? 'border-l-4 border-l-blue-500 bg-blue-50/50 dark:bg-blue-950/20'
                                        : ''
                                }`}
                            >
                                <CardContent className="flex items-start justify-between gap-4 py-4">
                                    <div className="flex items-start gap-3 min-w-0 flex-1">
                                        <div className="mt-1">
                                            {n.read_at ? (
                                                <MailOpen className="h-5 w-5 text-muted-foreground" />
                                            ) : (
                                                <Mail className="h-5 w-5 text-blue-600" />
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2 mb-1">
                                                <h3 className="font-semibold text-sm truncate">
                                                    {getTitle(n)}
                                                </h3>
                                                <Badge
                                                    variant="secondary"
                                                    className={`text-xs shrink-0 ${typeBadgeColor(n.notification_type)}`}
                                                >
                                                    {n.notification_type}
                                                </Badge>
                                            </div>
                                            <p className="text-sm text-muted-foreground line-clamp-2">
                                                {getBody(n)}
                                            </p>
                                            <div className="flex items-center gap-1 mt-2 text-xs text-muted-foreground">
                                                <Clock className="h-3 w-3" />
                                                {relativeTime(n.created_at)}
                                            </div>
                                        </div>
                                    </div>
                                    {!n.read_at && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => markRead(n.id)}
                                            className="shrink-0"
                                        >
                                            Mark Read
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {notifications.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {notifications.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
