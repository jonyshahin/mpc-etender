/**
 * Regression guard for resources/js/lib/datetime.ts.
 *
 * Run with `npm run test:js`. Uses Node's built-in test runner and its
 * TypeScript type-stripping, so it needs no dependency and no build step, and
 * it exercises the shipped module rather than a copy of it.
 *
 * Worth having despite the project carrying no other JS tests: an offset
 * conversion that is wrong by three hours looks entirely plausible on screen,
 * and Intl silently accepts some option combinations while throwing on others.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

let zone = 'Asia/Baghdad';

// The module reads the zone off window, as the browser injects it in app.blade.php.
globalThis.window = {
    get __timezone__() {
        return zone;
    },
};

// pathToFileURL, not the bare path: on Windows ESM reads "C:\..." as a URL scheme.
const {
    formatDate,
    formatDateTime,
    formatDeadline,
    fromDateTimeLocalInput,
    toDateInput,
    toDateTimeLocalInput,
} = await import(pathToFileURL(resolve(import.meta.dirname, '../resources/js/lib/datetime.ts')).href);

test('a stored instant reaches the control as project-zone wall clock', () => {
    assert.equal(toDateTimeLocalInput('2026-09-01T14:00:00.000000Z'), '2026-09-01T17:00');
});

test('the control posts back the instant it started from', () => {
    assert.equal(fromDateTimeLocalInput('2026-09-01T17:00'), '2026-09-01T14:00:00.000Z');
    assert.equal(toDateTimeLocalInput(fromDateTimeLocalInput('2026-09-01T17:00')), '2026-09-01T17:00');
});

test('midnight is 00:00, never 24:00', () => {
    // hour12:false yields "24" in some engines, which the control rejects.
    assert.equal(toDateTimeLocalInput('2026-09-01T21:00:00.000000Z'), '2026-09-02T00:00');
});

test('a date column is trimmed, never shifted', () => {
    // Shifting a DATE by an offset could only ever move it to the wrong day.
    assert.equal(toDateInput('2026-08-18T00:00:00.000000Z'), '2026-08-18');
    assert.equal(toDateInput('2026-08-18'), '2026-08-18');
});

test('empty and invalid values degrade quietly', () => {
    assert.equal(toDateTimeLocalInput(null), '');
    assert.equal(fromDateTimeLocalInput(''), '');
    assert.equal(formatDate(null), '—');
    assert.equal(formatDateTime(undefined), '—');
});

test('re-displayed input survives a validation error round trip', () => {
    assert.equal(toDateTimeLocalInput('2026-09-01T17:00'), '2026-09-01T17:00');
});

test('a deadline names its zone', () => {
    // Intl throws "Invalid option" if timeZoneName is paired with dateStyle,
    // so this asserts the option set is legal as much as the output.
    const rendered = formatDeadline('2026-09-01T14:00:00.000000Z', 'en-GB');
    assert.match(rendered, /17:00/);
    assert.match(rendered, /GMT\+3/);
});

test('the reader location does not change the clock shown', () => {
    const baghdad = formatDeadline('2026-09-01T14:00:00.000000Z', 'en-GB');
    process.env.TZ = 'America/New_York';
    assert.equal(formatDeadline('2026-09-01T14:00:00.000000Z', 'en-GB'), baghdad);
    process.env.TZ = 'UTC';
});

test('a zone with DST converts correctly on both sides of the transition', () => {
    // Baghdad has no DST, but the zone is configurable — this covers the
    // second offset pass in fromDateTimeLocalInput.
    zone = 'Europe/London';

    assert.equal(toDateTimeLocalInput('2026-07-01T12:00:00.000Z'), '2026-07-01T13:00');
    assert.equal(fromDateTimeLocalInput('2026-07-01T13:00'), '2026-07-01T12:00:00.000Z');
    assert.equal(toDateTimeLocalInput('2026-01-01T12:00:00.000Z'), '2026-01-01T12:00');
    assert.equal(fromDateTimeLocalInput('2026-01-01T12:00'), '2026-01-01T12:00:00.000Z');

    zone = 'Asia/Baghdad';
});
