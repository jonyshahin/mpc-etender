/**
 * Which filters a sortable table should carry into its next request.
 *
 * `DataTable` takes a `filters` prop that is optional in the type but was
 * effectively required: it merges the prop into every sort request, so a page
 * that forgot it sorted with an empty object — wiping the active search and
 * filters, and, because the toggle compares against a `filters.sort` that was
 * always undefined, making descending order unreachable. Six of the ten list
 * pages had forgotten it.
 *
 * Falling back to the live query string makes the prop a refinement rather than
 * a requirement: the URL already holds exactly the filters the server applied.
 */
export function resolveTableFilters(
    provided: Record<string, unknown> | undefined,
    search: string,
): Record<string, unknown> {
    if (provided && Object.keys(provided).length > 0) {
        return stripPage(provided);
    }

    return stripPage(Object.fromEntries(new URLSearchParams(search)));
}

/**
 * Re-sorting should land you on the first page.
 *
 * Carrying `page` over means a new sort order opens at page 5 of the old one,
 * which is rarely where the rows you just asked to see the top of are.
 */
function stripPage(filters: Record<string, unknown>): Record<string, unknown> {
    // delete rather than destructure-and-discard: the lint config has no
    // ignore pattern for an intentionally unused binding.
    const rest = { ...filters };
    delete rest.page;

    return rest;
}

/**
 * The direction a column header click should produce.
 *
 * Two-state: a new column starts ascending, and clicking the active column
 * flips it.
 */
export function nextSortDirection(
    column: string,
    currentSort: unknown,
    currentDirection: unknown,
): 'asc' | 'desc' {
    return currentSort === column && currentDirection === 'asc' ? 'desc' : 'asc';
}
