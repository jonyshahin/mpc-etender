/**
 * Date handling for the project's operating timezone.
 *
 * The server stores UTC and serialises it as `2026-09-01T14:00:00.000000Z`.
 * Everything a user sees or types is in the project zone instead (Asia/Baghdad
 * by default, `config('mpc.timezone')`), so a submission deadline reads the
 * same to an evaluator in Mosul and a vendor in Dubai.
 *
 * Rendering with the browser's own zone — which `new Date(x).toLocaleString()`
 * does — would show each of them a different clock time for the same deadline.
 */

/** Fallback matches config/mpc.php so a missing injection is not a silent shift. */
const FALLBACK_TIMEZONE = 'Asia/Baghdad';

/** Injected by resources/views/app.blade.php, alongside __translations__. */
export function projectTimeZone(): string {
    return (window as unknown as { __timezone__?: string }).__timezone__ || FALLBACK_TIMEZONE;
}

type Parts = Record<string, string>;

/** The wall-clock fields of `instant` as read in `timeZone`. */
function zonedParts(instant: Date, timeZone: string): Parts {
    // 'en-CA' is irrelevant to the output here — formatToParts is read field by
    // field — but hourCycle 'h23' is load-bearing: hour12:false yields "24" for
    // midnight in some engines, which would produce an invalid control value.
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(instant);

    return Object.fromEntries(parts.map((p) => [p.type, p.value]));
}

/** Milliseconds `timeZone` runs ahead of UTC at `instant`. */
function offsetAt(instant: Date, timeZone: string): number {
    const p = zonedParts(instant, timeZone);
    const asIfUtc = Date.UTC(
        Number(p.year),
        Number(p.month) - 1,
        Number(p.day),
        Number(p.hour),
        Number(p.minute),
        Number(p.second),
    );

    return asIfUtc - instant.getTime();
}

type DateLike = string | number | Date | null | undefined;

function parse(value: DateLike): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Trims a serialised date to the `YYYY-MM-DD` that `<input type="date">`
 * accepts.
 *
 * A `date` cast serialises as `2026-08-18T00:00:00.000000Z`. The control
 * rejects anything but a bare date, so it renders blank and the saved value
 * looks lost. No timezone conversion happens here on purpose: a DATE column
 * carries no time, so shifting it by an offset could only move it to the wrong
 * day. Values that are already bare pass through unchanged, which keeps
 * re-displaying user input after a validation error safe.
 */
export function toDateInput(value: string | null | undefined): string {
    return value ? value.slice(0, 10) : '';
}

/**
 * A UTC instant as the `YYYY-MM-DDTHH:mm` wall clock that
 * `<input type="datetime-local">` accepts, read in the project zone.
 *
 * Pair with {@link fromDateTimeLocalInput} on submit. Between the two the form
 * state holds a project-zone wall clock, which is the natural domain for the
 * inter-field arithmetic these forms do ("opening = deadline + buffer hours").
 */
export function toDateTimeLocalInput(value: string | null | undefined): string {
    const instant = parse(value);

    if (!instant) {
        // Already a bare wall clock (re-displayed input, or a form default).
        return value ? value.replace(' ', 'T').slice(0, 16) : '';
    }

    const p = zonedParts(instant, projectTimeZone());

    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
}

/**
 * A project-zone wall clock from a datetime control, as a UTC instant the
 * server can store.
 *
 * The server parses what it receives in `app.timezone` (UTC), so posting the
 * control's raw value would record 17:00 UTC for a deadline the user set to
 * 17:00 Baghdad — three hours late.
 */
export function fromDateTimeLocalInput(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const withSeconds = value.length === 16 ? `${value}:00` : value;
    const asIfUtc = new Date(`${withSeconds}Z`);

    if (Number.isNaN(asIfUtc.getTime())) {
        return value;
    }

    const zone = projectTimeZone();
    // Two passes: the first offset is sampled at the wrong instant by exactly
    // the offset itself, which only matters within a DST transition. Baghdad
    // has no DST, but the zone is configurable.
    const approx = new Date(asIfUtc.getTime() - offsetAt(asIfUtc, zone));

    return new Date(asIfUtc.getTime() - offsetAt(approx, zone)).toISOString();
}

/** Matches the shape the app's local formatDate helpers already used. */
const DEFAULT_DATE: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
};

const DEFAULT_DATE_TIME: Intl.DateTimeFormatOptions = {
    dateStyle: 'medium',
    timeStyle: 'short',
};

function render(
    value: DateLike,
    fallbackShape: Intl.DateTimeFormatOptions,
    locale?: string,
    options: Intl.DateTimeFormatOptions = {},
): string {
    const instant = parse(value);

    if (!instant) {
        return '—';
    }

    // An explicit shape replaces the default rather than merging into it:
    // Intl throws if dateStyle/timeStyle appear alongside individual
    // year/month/day components.
    const shape = Object.keys(options).length > 0 ? options : fallbackShape;

    return new Intl.DateTimeFormat(locale, { timeZone: projectTimeZone(), ...shape }).format(instant);
}

/**
 * Date only, in the project zone.
 *
 * Drop-in for `new Date(v).toLocaleDateString(locale, options)` — same
 * arguments, same output, except the zone is the project's rather than
 * whichever one the viewer's machine happens to be set to.
 */
export function formatDate(
    value: DateLike,
    locale?: string,
    options?: Intl.DateTimeFormatOptions,
): string {
    return render(value, DEFAULT_DATE, locale, options);
}

/**
 * Date and time, in the project zone.
 *
 * Drop-in for `new Date(v).toLocaleString()`. Pass
 * `{ timeZoneName: 'short' }` on anything legally significant — a submission
 * deadline should say which clock it is on.
 */
export function formatDateTime(
    value: DateLike,
    locale?: string,
    options?: Intl.DateTimeFormatOptions,
): string {
    return render(value, DEFAULT_DATE_TIME, locale, options);
}

/**
 * A deadline, with the zone named — '18 Aug 2026, 17:00 GMT+3'.
 *
 * Anything a bid can be rejected for missing should say which clock it is on,
 * since the reader is not necessarily sitting in that zone.
 */
export function formatDeadline(value: DateLike, locale?: string): string {
    // Individual components, not dateStyle/timeStyle: Intl throws
    // "Invalid option" if timeZoneName is combined with either of those.
    return formatDateTime(value, locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZoneName: 'short',
    });
}