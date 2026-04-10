import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * Page props shape for accessing auth user language preference.
 */
interface PageProps {
    auth: {
        user: {
            language_pref?: string;
        };
    };
    [key: string]: any;
}

/**
 * A toggle button that switches the application language between English and Arabic.
 */
export function LanguageToggle() {
    const { auth } = usePage<PageProps>().props;
    const currentLang = auth.user?.language_pref ?? 'en';

    function handleToggle() {
        const newLang = currentLang === 'en' ? 'ar' : 'en';
        router.put(
            '/user/language',
            { language: newLang },
            { preserveState: true },
        );
    }

    return (
        <Button variant="ghost" size="sm" onClick={handleToggle}>
            <Languages className="mr-1 h-4 w-4" />
            {currentLang.toUpperCase()}
        </Button>
    );
}
