import { useState, useRef, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Bell, Clock, MailOpen } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type RecentNotif = {
    id: string;
    title_en: string;
    title_ar: string | null;
    body_en: string;
    body_ar: string | null;
    notification_type: string;
    read_at: string | null;
    created_at: string;
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

export function NotificationBell({ unreadCount: initialCount }: { unreadCount: number }) {
    const { props } = usePage();
    const locale = (props as any).locale ?? 'en';
    const isAr = locale === 'ar';

    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState<RecentNotif[]>([]);
    const [loading, setLoading] = useState(false);
    const [unreadCount, setUnreadCount] = useState(initialCount);
    const dropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setUnreadCount(initialCount);
    }, [initialCount]);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    async function fetchRecent() {
        setLoading(true);
        try {
            const res = await fetch('/notifications/recent', {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                setNotifications(data.notifications ?? data);
            }
        } catch {
            // silently fail
        } finally {
            setLoading(false);
        }
    }

    function toggleOpen() {
        const next = !open;
        setOpen(next);
        if (next) {
            fetchRecent();
        }
    }

    async function markRead(id: string) {
        try {
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            });
            setNotifications((prev) =>
                prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)),
            );
            setUnreadCount((c) => Math.max(0, c - 1));
        } catch {
            // silently fail
        }
    }

    function getTitle(n: RecentNotif): string {
        if (isAr && n.title_ar) return n.title_ar;
        return n.title_en;
    }

    return (
        <div className="relative" ref={dropdownRef}>
            <Button variant="ghost" size="icon" onClick={toggleOpen} className="relative">
                <Bell className="h-5 w-5" />
                {unreadCount > 0 && (
                    <Badge className="absolute -top-1 -right-1 h-5 min-w-5 px-1 text-xs bg-red-600 text-white border-0">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </Badge>
                )}
            </Button>

            {open && (
                <div className="absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-80 bg-popover border rounded-lg shadow-lg z-50">
                    <div className="flex items-center justify-between px-4 py-3 border-b">
                        <h3 className="font-semibold text-sm">Notifications</h3>
                        {unreadCount > 0 && (
                            <Badge variant="secondary" className="text-xs">
                                {unreadCount} unread
                            </Badge>
                        )}
                    </div>

                    <div className="max-h-80 overflow-y-auto">
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <div className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                                <Bell className="h-8 w-8 mb-2" />
                                <p className="text-sm">No notifications</p>
                            </div>
                        ) : (
                            notifications.map((n) => (
                                <div
                                    key={n.id}
                                    className={`px-4 py-3 border-b last:border-0 hover:bg-muted/50 transition-colors ${
                                        !n.read_at ? 'bg-blue-50/50 dark:bg-blue-950/20' : ''
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium truncate">{getTitle(n)}</p>
                                            <div className="flex items-center gap-1 mt-1 text-xs text-muted-foreground">
                                                <Clock className="h-3 w-3" />
                                                {relativeTime(n.created_at)}
                                            </div>
                                        </div>
                                        {!n.read_at && (
                                            <button
                                                onClick={() => markRead(n.id)}
                                                className="shrink-0 p-1 text-muted-foreground hover:text-foreground transition-colors"
                                                title="Mark as read"
                                            >
                                                <MailOpen className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    <div className="border-t px-4 py-2">
                        <Link
                            href="/notifications"
                            className="text-sm text-primary hover:underline block text-center"
                            onClick={() => setOpen(false)}
                        >
                            View All Notifications
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
