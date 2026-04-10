import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    Building2,
    ClipboardList,
    FolderGit2,
    FolderKanban,
    LayoutGrid,
    MonitorDot,
    ScrollText,
    Settings,
    Shield,
    Tags,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const adminNavItems: NavItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard', icon: ClipboardList },
    { title: 'Users', href: '/admin/users', icon: Users },
    { title: 'Projects', href: '/admin/projects', icon: FolderKanban },
    { title: 'Vendors', href: '/admin/vendors', icon: Building2 },
    { title: 'Roles', href: '/admin/roles', icon: Shield },
    { title: 'Categories', href: '/admin/categories', icon: Tags },
    { title: 'Settings', href: '/admin/settings', icon: Settings },
    { title: 'Audit Logs', href: '/admin/audit-logs', icon: ScrollText },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const roleSlug = (auth as any).user?.role_slug;
    const isAdmin = roleSlug === 'super_admin' || roleSlug === 'admin';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                {isAdmin && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Administration</SidebarGroupLabel>
                        <SidebarMenu>
                            {adminNavItems.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {roleSlug === 'super_admin' && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Dev Tools</SidebarGroupLabel>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild tooltip={{ children: 'Horizon' }}>
                                    <a href="/horizon" target="_blank" rel="noopener noreferrer">
                                        <MonitorDot />
                                        <span>Horizon</span>
                                    </a>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild tooltip={{ children: 'Pulse' }}>
                                    <a href="/pulse" target="_blank" rel="noopener noreferrer">
                                        <Activity />
                                        <span>Pulse</span>
                                    </a>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
