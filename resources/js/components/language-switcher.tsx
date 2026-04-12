import { router, usePage } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import {
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

export function LanguageSwitcher() {
    const { locale } = usePage<{ locale: string }>().props;
    const isArabic = locale === 'ar';

    const switchLanguage = () => {
        router.put('/user/language', {
            language: isArabic ? 'en' : 'ar',
        }, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                onClick={switchLanguage}
                tooltip={{ children: isArabic ? 'English' : 'العربية' }}
            >
                <Globe />
                <span>{isArabic ? 'English' : 'العربية'}</span>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}
