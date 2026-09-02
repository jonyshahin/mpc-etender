/**
 * How long is left until a deadline, as parts a caller can translate.
 *
 * The vendor dashboard hardcoded `${days}d ${hours}h remaining` and the string
 * "Expired" in English, on a screen many of these vendors read in Arabic. It
 * also compared against the browser clock without going through the project
 * timezone, so a vendor in Dubai and an evaluator in Mosul disagreed about
 * whether the same tender was still open.
 */

export type Countdown =
    | { state: 'expired' }
    | { state: 'days'; days: number; hours: number }
    | { state: 'hours'; hours: number; minutes: number };

/**
 * `now` is injectable so a caller can hold one instant across a whole list —
 * sampling Date.now() per row lets two rows disagree mid-render — and so the
 * behaviour is testable without freezing the clock.
 *
 * No timezone conversion is needed: both sides are absolute instants, and the
 * difference between two instants is the same in every zone. Rendering the
 * deadline itself is what needs lib/datetime.
 */
export function countdownTo(deadline: string, now: number = Date.now()): Countdown {
    const target = new Date(deadline).getTime();

    if (Number.isNaN(target) || target <= now) {
        return { state: 'expired' };
    }

    const totalMinutes = Math.floor((target - now) / 60_000);
    const days = Math.floor(totalMinutes / (60 * 24));

    if (days >= 1) {
        return { state: 'days', days, hours: Math.floor((totalMinutes % (60 * 24)) / 60) };
    }

    return { state: 'hours', hours: Math.floor(totalMinutes / 60), minutes: totalMinutes % 60 };
}

/** Deadlines inside this window are worth colouring as urgent. */
export function isUrgent(countdown: Countdown): boolean {
    return countdown.state !== 'days';
}
