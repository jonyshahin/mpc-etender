import { router, usePage } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { LOCALES, LOCALE_BY_CODE, type LocaleCode } from '@/lib/locales';

export function LanguageSwitcher() {
    const { locale } = usePage<{ locale: string }>().props;
    const current = (locale as LocaleCode) ?? 'en';
    const currentLabel = LOCALE_BY_CODE[current]?.label ?? 'English';

    const switchLocale = (target: LocaleCode) => {
        if (target === current) return;
        router.put(
            '/user/language',
            { language: target },
            { onSuccess: () => window.location.reload() },
        );
    };

    return (
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <SidebarMenuButton tooltip={{ children: currentLabel }}>
                        <Globe />
                        <span>{currentLabel}</span>
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent side="top" align="start">
                    {LOCALES.map((l) => (
                        <DropdownMenuItem
                            key={l.code}
                            onSelect={() => switchLocale(l.code)}
                            disabled={l.code === current}
                        >
                            {l.label}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    );
}
