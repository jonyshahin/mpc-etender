/**
 * Supported locales. Single source of truth for the language selector UI
 * (welcome page + authenticated sidebar) — both consume LOCALES but render
 * with different shells, so the selectors themselves stay in separate files.
 *
 * Kurdish coverage currently limited to welcome + auth surfaces; other keys
 * in lang/ku.json carry [en] fallback prefixes. See reviewer notes when
 * expanding scope.
 */
export const LOCALES = [
    { code: 'en', label: 'English', dir: 'ltr' as const },
    { code: 'ar', label: 'العربية', dir: 'rtl' as const },
    { code: 'ku', label: 'کوردی', dir: 'rtl' as const },
] as const;

export type LocaleCode = (typeof LOCALES)[number]['code'];

export const LOCALE_BY_CODE: Record<LocaleCode, (typeof LOCALES)[number]> =
    Object.fromEntries(LOCALES.map((l) => [l.code, l])) as Record<
        LocaleCode,
        (typeof LOCALES)[number]
    >;
