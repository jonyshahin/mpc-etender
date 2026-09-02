import { useEffect, useState } from 'react';

/**
 * The clock, sampled after mount and kept moving.
 *
 * Held in state rather than read during render: `Date.now()` is impure, so
 * calling it in the render body is both a lint error and a correctness hazard —
 * two rows in the same list can land on opposite sides of a boundary if each
 * samples the clock itself.
 *
 * Keeping it moving matters on screens that are left open. A countdown computed
 * once and never updated goes on insisting a deadline is hours away long after
 * it has passed.
 *
 * @param intervalMs How often to re-sample. Deadlines want a minute or less;
 *                   a "3 days ago" timestamp is fine much slower.
 */
export function useNow(intervalMs = 30_000): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        // Only from the callback: setting state in the effect body itself would
        // cascade a second render on every mount.
        const timer = setInterval(() => setNow(Date.now()), intervalMs);

        return () => clearInterval(timer);
    }, [intervalMs]);

    return now;
}
