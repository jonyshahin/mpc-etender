/**
 * File sizes, split into a number and a unit the caller can translate.
 *
 * Four pages each carried their own copy that returned `${n} KB` with the unit
 * hardcoded in English and the number formatted with the browser's default
 * locale rather than the app's. On an Arabic screen that produced a Latin unit
 * beside Arabic-Indic digits, or Latin digits beside Arabic text, depending on
 * which page you were looking at.
 *
 * Returning the parts rather than a string keeps the unit a translation key and
 * lets the caller wrap the pair in <bdi> — "1.5 MB" is a number followed by a
 * Latin abbreviation, which bidi reordering will otherwise pull apart under
 * dir="rtl".
 */

const UNITS = ['bytes', 'kb', 'mb', 'gb'] as const;

export type FileSizeParts = {
    /** Locale-formatted, already rounded for its magnitude. */
    value: string;
    /** Translation key, e.g. `unit.mb`. */
    unitKey: string;
};

export function fileSizeParts(bytes: number, locale?: string): FileSizeParts {
    const safe = Number.isFinite(bytes) && bytes > 0 ? bytes : 0;

    // Index by magnitude rather than a chain of thresholds, clamped so a
    // implausibly large value still lands on a unit that exists.
    const magnitude = safe === 0 ? 0 : Math.floor(Math.log(safe) / Math.log(1024));
    const index = Math.min(Math.max(magnitude, 0), UNITS.length - 1);
    const scaled = safe / 1024 ** index;

    // Whole bytes; one decimal above that, dropped when it would read ".0".
    const fractionDigits = index === 0 ? 0 : 1;

    return {
        value: new Intl.NumberFormat(locale, {
            minimumFractionDigits: 0,
            maximumFractionDigits: fractionDigits,
        }).format(scaled),
        unitKey: `unit.${UNITS[index]}`,
    };
}
