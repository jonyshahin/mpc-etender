/**
 * Regression guard for resources/js/lib/roles.ts.
 *
 * Run with `npm run test:js`. The fallback is the part worth pinning: t()
 * returns the key it was given when it finds nothing, so a naive lookup puts
 * the literal string "role.site_auditor" on screen for any role added after
 * the language files were written.
 */
import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';

// pathToFileURL, not the bare path: on Windows ESM reads "C:\..." as a URL scheme.
const { roleLabel } = await import(
    pathToFileURL(resolve(import.meta.dirname, '../resources/js/lib/roles.ts')).href
);

/** Stands in for useTranslation's t(): a hit returns the value, a miss the key. */
const translator = (dictionary) => (key) => dictionary[key] ?? key;

test('a seeded role reads from the language file, not the database column', () => {
    const t = translator({ 'role.procurement_officer': 'مسؤول المشتريات' });

    assert.equal(
        roleLabel(t, { name: 'Procurement Officer', slug: 'procurement_officer' }),
        'مسؤول المشتريات',
    );
});

test('a role with no translation falls back to the stored name', () => {
    // What an administrator adds after the language files were written.
    const t = translator({});

    assert.equal(roleLabel(t, { name: 'Site Auditor', slug: 'site_auditor' }), 'Site Auditor');
});

test('it never renders the raw translation key', () => {
    const t = translator({});

    assert.doesNotMatch(roleLabel(t, { name: 'Site Auditor', slug: 'site_auditor' }), /^role\./);
});

test('an account with no role reads as a dash rather than as empty', () => {
    const t = translator({});

    assert.equal(roleLabel(t, null), '—');
    assert.equal(roleLabel(t, undefined), '—');
});
