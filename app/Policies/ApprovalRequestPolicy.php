<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;

/**
 * Who may act on an approval request.
 *
 * approve() existed and was never called: the decision endpoints authorised
 * through their form requests, which asked `can('approvals.level1')` — a bare
 * permission slug, which names neither a registered gate nor a policy ability,
 * so Gate denied it for everyone and the whole decision flow was dead. With
 * that fixed the level check has to live somewhere, and this is it.
 */
class ApprovalRequestPolicy
{
    public function view(User $user, ApprovalRequest $request): bool
    {
        return $user->isAssignedToProject($request->tender->project_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('evaluations.finalize_reports');
    }

    /**
     * Sign, or refuse, at this request's level.
     *
     * Both halves matter. The project check is the same rule view() applies:
     * the queue does not list approvals outside the reader's projects, and an
     * endpoint that accepts what the queue will not show is only scoped by
     * whether the caller can guess a UUID.
     *
     * The level check is the one that was missing entirely. A chain escalates
     * 1 → 2 → 3 by award value, so without it anyone holding the lowest level
     * could sign the largest award in the system.
     */
    public function approve(User $user, ApprovalRequest $request): bool
    {
        if (! $this->view($user, $request)) {
            return false;
        }

        // The person it was handed to may sign it, whether or not they hold
        // the level themselves — that is what delegation is for, and the
        // delegator could only pass on authority they held. Scoped to this
        // one request: it confers nothing anywhere else.
        if ($request->delegated_to === $user->id) {
            return true;
        }

        return $user->hasPermission("approvals.level{$request->approval_level}");
    }

    /**
     * Hand this decision to someone else.
     *
     * Named separately from approve() so the rule has somewhere of its own to
     * live, but deliberately identical for now: you cannot pass on an authority
     * you do not hold.
     */
    public function delegate(User $user, ApprovalRequest $request): bool
    {
        return $this->approve($user, $request);
    }
}
