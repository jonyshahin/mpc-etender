import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Bell,
    Building2,
    CheckSquare,
    ClipboardList,
    FileText,
    FolderKanban,
    LayoutGrid,
    ListChecks,
    MonitorDot,
    ScrollText,
    Settings,
    Shield,
    Tags,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
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
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage<{ dir?: string }>();
    const { auth } = page.props;
    const { isCurrentUrl } = useCurrentUrl();
    const { t } = useTranslation();
    const roleSlug = (auth as any).user?.role_slug;
    const isAdmin = roleSlug === 'super_admin' || roleSlug === 'admin';
    const pendingCategoryRequests = Number(
        (auth as any).user?.pending_vendor_category_requests_count ?? 0,
    );
    const side = page.props.dir === 'rtl' ? 'right' : 'left';

    const mainNavItems: NavItem[] = [
        { title: t('nav.dashboard'), href: dashboard(), icon: LayoutGrid },
        { title: t('nav.tenders'), href: '/tenders', icon: FileText },
        { title: t('nav.approvals'), href: '/approvals', icon: CheckSquare },
        { title: t('nav.portfolio'), href: '/dashboard/portfolio', icon: BarChart3 },
        { title: t('nav.notifications'), href: '/notifications', icon: Bell },
    ];

    const adminNavItems: NavItem[] = [
        { title: t('nav.admin_dashboard'), href: '/admin/dashboard', icon: ClipboardList },
        { title: t('nav.users'), href: '/admin/users', icon: Users },
        { title: t('nav.projects'), href: '/admin/projects', icon: FolderKanban },
        { title: t('nav.vendors'), href: '/admin/vendors', icon: Building2 },
        { title: t('nav.category_requests'), href: '/admin/vendor-category-requests', icon: ListChecks },
        { title: t('nav.roles'), href: '/admin/roles', icon: Shield },
        { title: t('nav.categories'), href: '/admin/categories', icon: Tags },
        { title: t('nav.settings'), href: '/admin/settings', icon: Settings },
        { title: t('nav.audit_logs'), href: '/admin/audit-logs', icon: ScrollText },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" side={side}>
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
                        <SidebarGroupLabel>{t('nav.admin')}</SidebarGroupLabel>
                        <SidebarMenu>
                            {adminNavItems.map((item) => {
                                const showBadge =
                                    item.href === '/admin/vendor-category-requests' &&
                                    pendingCategoryRequests > 0;
                                return (
                                    <SidebarMenuItem key={String(item.href)}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isCurrentUrl(item.href)}
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link href={item.href} prefetch>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                                {showBadge && (
                                                    <span className="ms-auto inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-xs font-medium text-primary-foreground">
                                                        {pendingCategoryRequests}
                                                    </span>
                                                )}
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {roleSlug === 'super_admin' && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>{t('nav.dev_tools')}</SidebarGroupLabel>
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
                <SidebarMenu>
                    <LanguageSwitcher />
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
