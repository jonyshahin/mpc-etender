<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a pending approval has been handed to.
 *
 * Delegation was never modelled. delegate() wrote an ApprovalDecision row with
 * `decision = 'delegated'` and stopped there — nothing read it, nothing changed
 * about who could sign, and the request stayed exactly where it was. The screen
 * flashed "Approval delegated successfully" over a no-op.
 *
 * The column is the assignment itself, so the policy has something to honour and
 * the queue has something to show.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->foreignUuid('delegated_to')
                ->nullable()
                ->after('requested_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delegated_to');
        });
    }
};
