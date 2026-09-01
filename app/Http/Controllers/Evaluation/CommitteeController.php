<?php

namespace App\Http\Controllers\Evaluation;

use App\Enums\CommitteeStatus;
use App\Enums\CommitteeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\AddMemberRequest;
use App\Http\Requests\Evaluation\StoreCommitteeRequest;
use App\Models\CommitteeMember;
use App\Models\EvaluationCommittee;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommitteeController extends Controller
{
    /**
     * {tender} and {committee} are bound independently by the router.
     *
     * Nothing tied them together, so passing a committee belonging to another
     * tender authorised against the wrong tender entirely.
     */
    private function assertCommitteeBelongsTo(Tender $tender, EvaluationCommittee $committee): void
    {
        abort_unless($committee->tender_id === $tender->id, 404);
    }

    public function index(Request $request, Tender $tender): Response
    {
        $this->authorize('view', $tender);

        $committees = $tender->committees()
            ->with(['members' => fn ($q) => $q->select('users.id', 'users.name', 'users.email')])
            ->get();

        // Get project team members for the add-member dropdown
        $projectUsers = $tender->project->users()
            ->select('users.id', 'users.name', 'users.email')
            ->get();

        return Inertia::render('evaluation/Committees', [
            'tender' => $tender->only('id', 'reference_number', 'title_en', 'is_two_envelope'),
            'committees' => $committees,
            'projectUsers' => $projectUsers,
            // Served from the enums rather than listed in the page, so the
            // pickers cannot drift from what validation accepts.
            'typeOptions' => CommitteeType::options(),
            'statusOptions' => CommitteeStatus::options(),
            'canManage' => $request->user()->can('manageCommittees', $tender),
        ]);
    }

    public function store(StoreCommitteeRequest $request, Tender $tender): RedirectResponse
    {
        $this->authorize('manageCommittees', $tender);

        $tender->committees()->create([
            ...$request->validated(),
            'status' => CommitteeStatus::Active,
            'formed_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Committee created.')]);

        return back();
    }

    public function update(Request $request, Tender $tender, EvaluationCommittee $committee): RedirectResponse
    {
        // Took a plain Request and called no authorize(): any verified user
        // could rename any committee in the system and flip its status, which
        // is the flag the evaluation workflow reads to decide completion.
        $this->authorize('manageCommittees', $tender);
        $this->assertCommitteeBelongsTo($tender, $committee);

        $committee->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(CommitteeStatus::class)],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Committee updated.')]);

        return back();
    }

    public function addMember(AddMemberRequest $request, Tender $tender, EvaluationCommittee $committee): RedirectResponse
    {
        // AddMemberRequest checks a global permission only, so a holder could
        // add themselves to a committee on another project and thereby unlock
        // everything EvaluationScorePolicy::score() gates.
        $this->authorize('manageCommittees', $tender);
        $this->assertCommitteeBelongsTo($tender, $committee);

        $data = $request->validated();

        $candidate = User::findOrFail($data['user_id']);

        // AddMemberRequest validates only that the id exists somewhere in the
        // users table. Committee membership is what EvaluationScorePolicy::score
        // gates on, so adding an outsider hands them the evaluation data for a
        // project they have nothing to do with.
        if (! $candidate->isAssignedToProject($tender->project_id)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('That user is not assigned to this project.')]);

            return back();
        }

        // One committee per tender per person. Sitting on both the technical
        // and the financial committee is not a richer role — it is an ambiguity
        // the scoring screen has to guess its way out of.
        $alreadyOnThisTender = CommitteeMember::whereHas(
            'committee',
            fn ($q) => $q->where('tender_id', $tender->id),
        )->where('user_id', $candidate->id)->exists();

        if ($alreadyOnThisTender) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('User is already a member.')]);

            return back();
        }

        CommitteeMember::create([
            'committee_id' => $committee->id,
            'user_id' => $data['user_id'],
            'role' => $data['role'],
            'has_scored' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added.')]);

        return back();
    }

    /**
     * Bound by USER, not by the pivot row.
     *
     * EvaluationCommittee::members() is a belongsToMany over users and does
     * not expose the pivot id, so the screen only ever had a user id to
     * send — while this bound a CommitteeMember. Removal 404'd every time.
     */
    public function removeMember(Tender $tender, EvaluationCommittee $committee, User $user): RedirectResponse
    {
        // No FormRequest and no authorize(): any verified user could delete any
        // committee membership row in the system, changing who may score and
        // the denominator of every aggregate built from those scores.
        $this->authorize('manageCommittees', $tender);
        $this->assertCommitteeBelongsTo($tender, $committee);

        $member = CommitteeMember::where('committee_id', $committee->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $member->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }
}
