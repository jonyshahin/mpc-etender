/**
 * Regression guard for resources/js/lib/table.ts.
 *
 * Run with `npm run test:js`. The behaviour it protects is invisible until it
 * breaks: a table that sorts with the wrong filter set silently discards what
 * the user had narrowed to, and looks like it simply ignored the click.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

// pathToFileURL, not the bare path: on Windows ESM reads "C:\..." as a URL scheme.
const { nextSortDirection, resolveTableFilters } = await import(
    pathToFileURL(resolve(import.meta.dirname, '../resources/js/lib/table.ts')).href
);

test('an explicit filter set is used as given', () => {
    const provided = { search: 'hvac', status: 'published', sort: 'title_en', direction: 'asc' };

    assert.deepEqual(resolveTableFilters(provided, '?ignored=1'), provided);
});

test('it falls back to the query string when the prop was forgotten', () => {
    // The case six list pages were in: no prop, so sorting used to spread {}.
    assert.deepEqual(
        resolveTableFilters(undefined, '?search=hvac&status=published&sort=title_en&direction=desc'),
        { search: 'hvac', status: 'published', sort: 'title_en', direction: 'desc' },
    );

    // An empty object is the same omission by another name.
    assert.deepEqual(resolveTableFilters({}, '?status=draft'), { status: 'draft' });
});

test('it drops page so a new sort order opens at the top', () => {
    assert.deepEqual(resolveTableFilters(undefined, '?page=5&status=draft'), { status: 'draft' });
    assert.deepEqual(resolveTableFilters({ page: 5, status: 'draft' }, ''), { status: 'draft' });
});

test('no filters anywhere is an empty set, not a crash', () => {
    assert.deepEqual(resolveTableFilters(undefined, ''), {});
    assert.deepEqual(resolveTableFilters(undefined, '?'), {});
});

test('a fresh column sorts ascending and the active one flips', () => {
    assert.equal(nextSortDirection('title_en', 'created_at', 'desc'), 'asc');
    assert.equal(nextSortDirection('title_en', 'title_en', 'asc'), 'desc');
    assert.equal(nextSortDirection('title_en', 'title_en', 'desc'), 'asc');
});

test('descending is reachable even when nothing is sorted yet', () => {
    // The old bug in one line: with an undefined current sort every click
    // produced 'asc', so a second click changed nothing.
    const first = nextSortDirection('title_en', undefined, undefined);
    const second = nextSortDirection('title_en', 'title_en', first);

    assert.equal(first, 'asc');
    assert.equal(second, 'desc');
});
