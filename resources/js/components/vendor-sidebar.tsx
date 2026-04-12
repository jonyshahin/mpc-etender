import { Link, usePage } from '@inertiajs/react';
import { Bell, ClipboardList, FileText, Gavel, LayoutGrid, LogOut, Tags, UserCircle } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
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
import type { NavItem } from '@/types';

export function VendorSidebar() {
    const { isCurrentUrl } = useCurrentUrl();
    const { t } = useTranslation();
    const page = usePage<{ dir?: string }>();
    const { auth } = page.props;
    const vendor = (auth as any).vendor;
    const side = page.props.dir === 'rtl' ? 'right' : 'left';

    const vendorNavItems: NavItem[] = [
        { title: t('nav.dashboard'), href: '/vendor/dashboard', icon: LayoutGrid },
        { title: t('nav.open_tenders'), href: '/vendor/tenders', icon: ClipboardList },
        { title: t('nav.my_bids'), href: '/vendor/bids', icon: Gavel },
        { title: t('nav.notifications'), href: '/vendor/notifications', icon: Bell },
        { title: t('nav.profile'), href: '/vendor/profile', icon: UserCircle },
        { title: t('nav.documents'), href: '/vendor/documents', icon: FileText },
        { title: t('nav.categories'), href: '/vendor/categories', icon: Tags },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" side={side}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/vendor/dashboard">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>{t('nav.vendor_portal')}</SidebarGroupLabel>
                    <SidebarMenu>
                        {vendorNavItems.map((item) => (
                            <SidebarMenuItem key={String(item.href)}>
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
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <span className="truncate text-sm">
                                {vendor?.company_name ?? t('nav.vendor_portal')}
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <LanguageSwitcher />
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip={{ children: t('nav.logout') }}>
                            <Link href="/vendor/logout" method="post" as="button" className="w-full">
                                <LogOut />
                                <span>{t('nav.logout')}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
