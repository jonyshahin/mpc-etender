import { usePage } from '@inertiajs/react';

/**
 * Lightweight translation hook.
 * Reads locale from Inertia shared props (set by HandleInertiaRequests).
 * Translations are loaded lazily from window.__translations__ if pre-loaded,
 * or falls back to key passthrough.
 */
export function useTranslation() {
    const { locale } = usePage().props as { locale?: string };
    const lang = locale || 'en';

    function t(key: string, replacements?: Record<string, string | number>): string {
        // Access translations from global window if populated by blade template
        const translations = (window as any).__translations__ as Record<string, string> | undefined;
        let value = translations?.[key] ?? key;

        if (replacements) {
            Object.entries(replacements).forEach(([k, v]) => {
                value = value.replace(`:${k}`, String(v));
            });
        }

        return value;
    }

    return { t, locale: lang, dir: lang === 'ar' ? ('rtl' as const) : ('ltr' as const) };
}
