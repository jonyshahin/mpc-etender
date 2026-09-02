/**
 * Regression guard for resources/js/lib/approval.ts.
 *
 * Run with `npm run test:js`. The bug it protects against read as a styling
 * detail and was a factual one: the queue told you an approval was overdue
 * after it had been approved.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

// pathToFileURL, not the bare path: on Windows ESM reads "C:\..." as a URL scheme.
const { approvalDeadlineState } = await import(
    pathToFileURL(resolve(import.meta.dirname, '../resources/js/lib/approval.ts')).href
);

const NOW = Date.parse('2026-09-02T12:00:00Z');
const DUE_SOON = 48 * 3_600_000;

const at = (iso) => Date.parse(iso) && iso;

test('a pending approval past its deadline is overdue', () => {
    const state = approvalDeadlineState(at('2026-09-01T12:00:00Z'), 'pending', NOW, DUE_SOON);

    assert.equal(state.kind, 'live');
    assert.equal(state.overdue, true);
    assert.equal(state.hours, 24);
});

test('a decided approval past its deadline is not overdue', () => {
    // The defect: these rendered "Overdue" in red beside an Approved badge.
    for (const status of ['approved', 'rejected', 'escalated', 'expired']) {
        const state = approvalDeadlineState(at('2026-09-01T12:00:00Z'), status, NOW, DUE_SOON);

        assert.equal(state.kind, 'settled', `${status} should be settled`);
    }
});

test('a pending approval inside the window is due soon', () => {
    const state = approvalDeadlineState(at('2026-09-03T12:00:00Z'), 'pending', NOW, DUE_SOON);

    assert.equal(state.kind, 'live');
    assert.equal(state.overdue, false);
    assert.equal(state.dueSoon, true);
});

test('a pending approval well ahead of its deadline is neither', () => {
    const state = approvalDeadlineState(at('2026-09-20T12:00:00Z'), 'pending', NOW, DUE_SOON);

    assert.equal(state.kind, 'live');
    assert.equal(state.overdue, false);
    assert.equal(state.dueSoon, false);
});

test('no deadline is not a passed deadline', () => {
    assert.equal(approvalDeadlineState(null, 'pending', NOW, DUE_SOON).kind, 'none');
    assert.equal(approvalDeadlineState(undefined, 'pending', NOW, DUE_SOON).kind, 'none');
});

test('an unparseable deadline does not read as overdue', () => {
    // NaN comparisons are false, so this used to fall through to the countdown
    // branch and render a NaN duration.
    assert.equal(approvalDeadlineState('not a date', 'pending', NOW, DUE_SOON).kind, 'none');
});
