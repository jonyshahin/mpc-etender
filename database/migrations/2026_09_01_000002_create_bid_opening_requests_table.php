<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bid opening awaiting its second signature.
 *
 * Dual authorisation used to be a single POST: the opener chose an
 * `authorizer_id` from a dropdown and the bids opened immediately. Nothing
 * required the named person to act, or even to know — and BidSealingService
 * then wrote `authorized_by` into an append-only audit row, attributing the
 * authorisation to someone who never gave it.
 *
 * A row here is the opener's half. It becomes an opening only when the named
 * authorizer confirms it from their own session, which is the half that was
 * missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_opening_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();

            // The two parties. Both must hold bids.open and be on the project;
            // the pair is re-checked at confirmation, not just at request time.
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('authorizer_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 20)->default('pending');

            $table->timestamp('requested_at');
            // Opening is a scheduled ceremony, so a request is meant to be
            // confirmed within minutes. A short window keeps a forgotten
            // request from being confirmable days later.
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // The guard behind "one open request per tender at a time".
            $table->index(['tender_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_opening_requests');
    }
};
