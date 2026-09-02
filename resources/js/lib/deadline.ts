/**
 * How much time is left against a submission deadline.
 *
 * The vendor browse page computed this inline and returned English string
 * literals — 'Closed', 'Due today', `${days} days left` — straight into the
 * card, on the one surface in the app most likely to be read in Arabic.
 *
 * Days are counted in the project's timezone rather than the browser's: a
 * deadline at 17:00 Baghdad is "tomorrow" or "today" according to the calendar
 * the deadline is written on, not the one the viewer is sitting in.
 */
import { projectTimeZone } from './datetime';

export type DeadlineTone = 'passed' | 'urgent' | 'soon' | 'normal';

export type DeadlineStatus = {
    /** Whole project-zone days between today and the deadline. Negative once past. */
    days: number;
    tone: DeadlineTone;
    /** Translation key for the label, with `:count` where the tone needs it. */
    labelKey: string;
};

/** The `YYYY-MM-DD` civil date of `instant`, read in `timeZone`. */
function civilDate(instant: Date, timeZone: string): number {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(instant);

    const p = Object.fromEntries(parts.map((x) => [x.type, x.value]));

    return Date.UTC(Number(p.year), Number(p.month) - 1, Number(p.day));
}

const DAY_MS = 24 * 60 * 60 * 1000;

/**
 * Where `deadline` stands relative to now.
 *
 * Returns a translation key rather than a string so the caller runs it through
 * `t()` with its own locale — and so "1 day left" gets its own key instead of
 * a `:count` substitution reading "1 days left". `t()` does plain replacement
 * with no plural forms, and Arabic has six of them, so anything beyond the
 * singular/plural split belongs in the catalogue as separate keys.
 */
export function deadlineStatus(deadline: string | null | undefined, now: Date = new Date()): DeadlineStatus | null {
    if (!deadline) {
        return null;
    }

    const end = new Date(deadline);

    if (Number.isNaN(end.getTime())) {
        return null;
    }

    const zone = projectTimeZone();
    const days = Math.round((civilDate(end, zone) - civilDate(now, zone)) / DAY_MS);

    if (end.getTime() < now.getTime()) {
        return { days, tone: 'passed', labelKey: 'tender.deadline_closed' };
    }

    if (days <= 0) {
        return { days: 0, tone: 'urgent', labelKey: 'tender.due_today' };
    }

    if (days === 1) {
        return { days, tone: 'urgent', labelKey: 'tender.one_day_left' };
    }

    // Arabic marks the dual grammatically — ":count أيام" renders 2 as
    // "2 أيام", which is wrong where "يومان" is required. Two is the common
    // case on a deadline countdown, so it gets its own key.
    if (days === 2) {
        return { days, tone: 'urgent', labelKey: 'tender.two_days_left' };
    }

    return {
        days,
        tone: days <= 3 ? 'urgent' : days <= 7 ? 'soon' : 'normal',
        labelKey: 'tender.days_left',
    };
}

/** Text colour for a tone. Kept beside the tones so the two cannot drift. */
export const DEADLINE_TONE_CLASS: Record<DeadlineTone, string> = {
    passed: 'text-muted-foreground',
    urgent: 'font-semibold text-destructive',
    soon: 'font-medium text-amber-600 dark:text-amber-500',
    normal: 'text-muted-foreground',
};
