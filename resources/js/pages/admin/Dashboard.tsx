import { Head } from '@inertiajs/react';
import { Users, FolderKanban, FileText, Building2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Heading from '@/components/heading';

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

const statCards = [
    { key: 'total_users' as const, label: 'Total Users', icon: Users, color: 'text-blue-600' },
    { key: 'active_projects' as const, label: 'Active Projects', icon: FolderKanban, color: 'text-green-600' },
    { key: 'active_tenders' as const, label: 'Active Tenders', icon: FileText, color: 'text-purple-600' },
    { key: 'pending_vendors' as const, label: 'Pending Vendors', icon: Building2, color: 'text-orange-600' },
];

export default function Dashboard({ stats, recentActivity }: Props) {
    return (
        <>
            <Head title="Admin Dashboard" />

            <Heading title="Dashboard" description="Overview of the e-Tender system." />

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
                        <CardTitle>Recent Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentActivity.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No recent activity.</p>
                        ) : (
                            <ul className="space-y-4">
                                {recentActivity.map((activity) => (
                                    <li key={activity.id} className="flex items-start justify-between gap-4">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">
                                                {activity.user?.name ?? 'Unknown User'}
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
