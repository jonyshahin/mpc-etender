/**
 * How an approval's deadline should read, given where the request has got to.
 *
 * The queue's deadline column knew only the date, so it compared it against
 * now and rendered "Overdue" in red whenever it had passed — on approved,
 * rejected, escalated and expired rows alike. Nothing was late about them:
 * they had been decided, and the badge beside the countdown already said so.
 *
 * It also disagreed with the tile above it, which counts overdue as
 * `status = pending AND deadline < now` (ApprovalController::summary). Pending
 * is the only state with a live deadline, and this is the one place that fact
 * is written down for the client.
 *
 * Deliberately import-free so `scripts/deadline.test.mjs` can load it through
 * Node's type stripping.
 */

/** The only status whose deadline is still counting down. */
export const LIVE_APPROVAL_STATUS = 'pending';

export type ApprovalDeadlineState =
    /** No deadline was set. */
    | { kind: 'none' }
    /** Decided, lapsed or moved on: the date is history, not a countdown. */
    | { kind: 'settled' }
    /** Awaiting a decision, so the remaining time still means something. */
    | { kind: 'live'; overdue: boolean; dueSoon: boolean; hours: number };

/**
 * @param deadline ISO timestamp, or null when none was set.
 * @param status   The request's `ApprovalStatus` value.
 * @param nowMs    Server-stamped clock, so the rows and the tile agree.
 * @param dueSoonMs How close counts as due soon.
 */
export function approvalDeadlineState(
    deadline: string | null | undefined,
    status: string,
    nowMs: number,
    dueSoonMs: number,
): ApprovalDeadlineState {
    if (!deadline) {
        return { kind: 'none' };
    }

    const end = new Date(deadline).getTime();

    // An unparseable date is not a deadline that has passed — treat it as none
    // rather than rendering "Overdue" off a NaN comparison.
    if (Number.isNaN(end)) {
        return { kind: 'none' };
    }

    if (status !== LIVE_APPROVAL_STATUS) {
        return { kind: 'settled' };
    }

    const msLeft = end - nowMs;
    const overdue = msLeft < 0;

    return {
        kind: 'live',
        overdue,
        dueSoon: !overdue && msLeft < dueSoonMs,
        hours: Math.max(0, Math.round(Math.abs(msLeft) / 3_600_000)),
    };
}
