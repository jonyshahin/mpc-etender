<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\AddMemberRequest;
use App\Http\Requests\Evaluation\StoreCommitteeRequest;
use App\Models\CommitteeMember;
use App\Models\EvaluationCommittee;
use App\Models\Tender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommitteeController extends Controller
{
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
        ]);
    }

    public function store(StoreCommitteeRequest $request, Tender $tender): RedirectResponse
    {
        $tender->committees()->create([
            ...$request->validated(),
            'status' => 'active',
            'formed_at' => now(),
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => __('Committee created.')]);
    }

    public function update(Request $request, Tender $tender, EvaluationCommittee $committee): RedirectResponse
    {
        $committee->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,completed'],
        ]));

        return back()->with('flash', ['type' => 'success', 'message' => __('Committee updated.')]);
    }

    public function addMember(AddMemberRequest $request, Tender $tender, EvaluationCommittee $committee): RedirectResponse
    {
        $data = $request->validated();

        if ($committee->committeeMemberRecords()->where('user_id', $data['user_id'])->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => __('User is already a member.')]);
        }

        CommitteeMember::create([
            'committee_id' => $committee->id,
            'user_id' => $data['user_id'],
            'role' => $data['role'],
            'has_scored' => false,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => __('Member added.')]);
    }

    public function removeMember(Tender $tender, EvaluationCommittee $committee, CommitteeMember $member): RedirectResponse
    {
        $member->delete();

        return back()->with('flash', ['type' => 'success', 'message' => __('Member removed.')]);
    }
}
