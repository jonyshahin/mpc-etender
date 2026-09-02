<?php

namespace App\Concerns;

use Illuminate\Http\Request;

/**
 * Reading list filters off a query string that cannot be trusted to have a
 * shape.
 *
 * `?search[]=x` arrives as an array and `?search[a][b]=c` as a nested one.
 * Every list controller read these with `trim((string) $request->input(...))`,
 * and casting an array to string raises a PHP warning that Laravel promotes to
 * an exception — so eight of the fifteen list screens returned a 500 to anyone
 * who edited the address bar.
 *
 * The third bug of this family on these controllers, after an `orderBy` column
 * that was never whitelisted and a status filter that read `has()` rather than
 * a value. Shared rather than fixed eight times so a new list page inherits it.
 */
trait FiltersLists
{
    /** A free-text search term, trimmed, whatever shape it arrived in. */
    protected function searchTerm(Request $request, string $key = 'search'): string
    {
        return trim($this->scalarInput($request, $key));
    }

    /**
     * A single-value filter — an id, a status — or null when absent.
     *
     * Null rather than a guess: an array here means the query string was
     * hand-edited or mangled, and the honest reading of "role_id=[a,b]" is
     * that no single role was asked for.
     */
    protected function filterValue(Request $request, string $key): ?string
    {
        $value = $this->scalarInput($request, $key);

        return $value !== '' ? $value : null;
    }

    /** Anything that is not already a scalar is treated as absent. */
    private function scalarInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
