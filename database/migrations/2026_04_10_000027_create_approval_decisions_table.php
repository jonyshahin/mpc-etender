<?php

// Table: approval_decisions — Purpose: decisions recorded by approvers against an approval request.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignUuid('approver_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 30);
            $table->text('comments')->nullable();
            $table->foreignUuid('delegated_from')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
};
