import { Head } from '@inertiajs/react';
import { Users, FolderKanban, FileText, Building2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    stats: {
        total_users: number;
        active_projects: number;
        active_tenders: number;
        pending_vendors: number;
    };
    recentActivity: Array<{
        id: string;
        user_id: string;
        description: string;
        subject_type: string;
        subject_id: string;
        created_at: string;
        user?: { id: string; name: string };
    }>;
};

function timeAgo(dateString: string): string {
    const now = new Date();
    const date = new Date(dateString);
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days}d ago`;
    const months = Math.floor(days / 30);
    return `${months}mo ago`;
}

export default function Dashboard({ stats, recentActivity }: Props) {
    const { t } = useTranslation();

    const statCards = [
        { key: 'total_users' as const, label: t('pages.admin.total_users'), icon: Users, color: 'text-blue-600' },
        { key: 'active_projects' as const, label: t('pages.admin.active_projects'), icon: FolderKanban, color: 'text-green-600' },
        { key: 'active_tenders' as const, label: t('pages.admin.active_tenders'), icon: FileText, color: 'text-purple-600' },
        { key: 'pending_vendors' as const, label: t('pages.admin.pending_vendors'), icon: Building2, color: 'text-orange-600' },
    ];

    return (
        <>
            <Head title="Admin Dashboard" />

            <Heading title={t('pages.admin.dashboard')} description={t('pages.admin.dashboard_description')} />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {statCards.map((card) => {
                    const Icon = card.icon;
                    return (
                        <Card key={card.key}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{card.label}</CardTitle>
                                <Icon className={`h-5 w-5 ${card.color}`} />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats[card.key].toLocaleString()}</div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            <div className="mt-8">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('pages.admin.recent_activity')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentActivity.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('empty.no_recent_activity')}</p>
                        ) : (
                            <ul className="space-y-4">
                                {recentActivity.map((activity) => (
                                    <li key={activity.id} className="flex items-start justify-between gap-4">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">
                                                {activity.user?.name ?? t('pages.admin.unknown_user')}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {activity.description}
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {timeAgo(activity.created_at)}
                                        </span>
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
