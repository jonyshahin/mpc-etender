import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * The filter set a list page carries in its query string.
 *
 * `sort` and `direction` are always present — the server resolves them to a
 * whitelisted default — while the page-specific keys are optional.
 */
export type ListFilters = {
    sort: string;
    direction: string;
} & Record<string, string | null | undefined>;

/**
 * Debounced search plus whole-set navigation for a filtered list page.
 *
 * Two things every one of these pages has to get right, and which three
 * separate copies of this block were getting right independently:
 *
 * - Every navigation carries the *whole* filter set. Rebuilding the query from
 *   the one key that changed silently drops the sort and direction.
 * - Typing searches on its own, after a pause. The pages this replaces
 *   searched only on Enter or on a button press, with nothing on screen to say
 *   that typing alone did nothing.
 *
 * The first render is skipped so mounting a page with an active search does not
 * immediately re-request it.
 */
export function useDebouncedFilters<T extends ListFilters>(
    url: string,
    filters: T,
    delayMs = 350,
) {
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    // Read through a ref so the debounce effect does not need `filters` in its
    // dependency list — it would re-fire on the very response it triggered.
    const latest = useRef(filters);
    latest.current = filters;

    const navigate = (next: Record<string, string | undefined>) => {
        const current = latest.current;

        router.get(
            url,
            {
                ...Object.fromEntries(
                    Object.entries(current)
                        .filter(([key]) => key !== 'sort' && key !== 'direction')
                        .map(([key, value]) => [key, value ?? undefined]),
                ),
                search: search || undefined,
                sort: current.sort,
                direction: current.direction,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(
            () => navigate({ search: search || undefined }),
            delayMs,
        );

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return { search, setSearch, navigate };
}
