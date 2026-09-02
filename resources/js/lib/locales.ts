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

/**
 * The reader's own version of a bilingual field, falling back to English.
 *
 * Formalises what eight call sites already do inline
 * (`locale === 'ar' ? (c.name_ar ?? c.name_en) : c.name_en`). The vendor
 * screens need it for six field pairs each — title, description, project
 * name, category name, BOQ section title, BOQ item description — and all six
 * were reading the English column unconditionally, on the surface most likely
 * to be read in Arabic.
 *
 * Kurdish rows do not exist in the schema; `ku` readers get English, which is
 * what the rest of the app does for them today.
 */
export function localized(
    locale: string,
    english: string | null | undefined,
    arabic: string | null | undefined,
): string {
    if (locale === 'ar' && arabic) {
        return arabic;
    }

    return english ?? arabic ?? '';
}
