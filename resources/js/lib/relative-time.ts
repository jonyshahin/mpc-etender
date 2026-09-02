/**
 * "3 days ago", in whatever language the reader is using.
 *
 * The vendor notification list built this string in English inline — "just
 * now", "5m ago", "2h ago" — on a page many of these vendors read in Arabic.
 * The keys already existed for the staff-side bell; nothing was using them
 * here.
 *
 * Anything older than a week returns null so the caller falls back to a real
 * date: "43 days ago" is harder to place than the date itself.
 */

type Translate = (key: string, replacements?: Record<string, string | number>) => string;

const MINUTE = 60_000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

export function relativeTime(value: string, now: number, t: Translate): string {
    const at = new Date(value).getTime();

    if (Number.isNaN(at)) {
        return '—';
    }

    // Clamp at zero: a row written by a server clock a second ahead of the
    // browser's would otherwise read as being in the future.
    const elapsed = Math.max(now - at, 0);

    if (elapsed < MINUTE) {
        return t('notifications.just_now');
    }

    if (elapsed < HOUR) {
        return t('notifications.minutes_ago_count', { count: Math.floor(elapsed / MINUTE) });
    }

    if (elapsed < DAY) {
        return t('notifications.hours_ago_count', { count: Math.floor(elapsed / HOUR) });
    }

    return t('notifications.days_ago_count', { count: Math.floor(elapsed / DAY) });
}
