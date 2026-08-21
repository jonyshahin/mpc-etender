import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Trims a Laravel-serialised date to the `YYYY-MM-DD` that
 * `<input type="date">` accepts.
 *
 * A `date` cast serialises as `2026-08-18T00:00:00.000000Z`. The control
 * rejects anything but a bare date, so it renders blank and the saved value
 * looks lost. The underlying column holds no time, so the prefix is lossless.
 * Values that are already bare pass through unchanged, which keeps
 * re-displaying user input after a validation error safe.
 */
export function toDateInput(value: string | null | undefined): string {
    return value ? value.slice(0, 10) : '';
}

/**
 * Trims a Laravel-serialised datetime to the `YYYY-MM-DDTHH:mm` that
 * `<input type="datetime-local">` accepts.
 *
 * Deliberately keeps the UTC wall clock instead of converting to the viewer's
 * zone: these forms post back whatever the control holds and the server parses
 * it in the app timezone (UTC), so converting here would shift the stored time
 * by the viewer's offset every time the form is saved.
 */
export function toDateTimeLocalInput(value: string | null | undefined): string {
    return value ? value.replace(' ', 'T').slice(0, 16) : '';
}
