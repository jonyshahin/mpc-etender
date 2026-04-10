<?php

namespace Database\Factories;

use App\Enums\CommitteeRole;
use App\Models\CommitteeMember;
use App\Models\EvaluationCommittee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitteeMember>
 */
class CommitteeMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'committee_id' => EvaluationCommittee::factory(),
            'user_id' => User::factory(),
            'role' => CommitteeRole::Member,
            'has_scored' => false,
            'scored_at' => null,
        ];
    }
}
