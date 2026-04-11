import { Link, usePage } from '@inertiajs/react';
import { Bell, ClipboardList, FileText, Gavel, LayoutGrid, LogOut, Tags, UserCircle } from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import type { NavItem } from '@/types';

const vendorNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/vendor/dashboard', icon: LayoutGrid },
    { title: 'Open Tenders', href: '/vendor/tenders', icon: ClipboardList },
    { title: 'My Bids', href: '/vendor/bids', icon: Gavel },
    { title: 'Notifications', href: '/vendor/notifications', icon: Bell },
    { title: 'Profile', href: '/vendor/profile', icon: UserCircle },
    { title: 'Documents', href: '/vendor/documents', icon: FileText },
    { title: 'Categories', href: '/vendor/categories', icon: Tags },
];

export function VendorSidebar() {
    const { isCurrentUrl } = useCurrentUrl();
    const page = usePage<{ dir?: string }>();
    const { auth } = page.props;
    const vendor = (auth as any).vendor;
    const side = page.props.dir === 'rtl' ? 'right' : 'left';

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
                    <SidebarGroupLabel>Vendor Portal</SidebarGroupLabel>
                    <SidebarMenu>
                        {vendorNavItems.map((item) => (
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
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <span className="truncate text-sm">
                                {vendor?.company_name ?? 'Vendor'}
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip={{ children: 'Logout' }}>
                            <Link href="/vendor/logout" method="post" as="button" className="w-full">
                                <LogOut />
                                <span>Logout</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
