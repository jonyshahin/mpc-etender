<?php

// Table: bid_documents — Extend with envelope_type, uploaded_by_vendor_id,
// and original_filename to support two-envelope bid submissions (BUG-18).
//
// envelope_type splits documents by procurement envelope ('single' for
// single-envelope tenders, 'technical' / 'financial' for two-envelope).
// The bids row stays one-per-(tender, vendor) per the unique constraint
// (BUG-19); the envelope split lives entirely on documents.
//
// uploaded_by_vendor_id is a nullable FK so we can audit who uploaded a
// given file independently from the bid_id (vendors might have multiple
// authorized contacts in future). Nullable for backfill safety.
//
// original_filename preserves the user's original name for display
// alongside `title` (which is the user-entered label).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bid_documents', function (Blueprint $table) {
            $table->string('envelope_type', 20)->default('single')->after('doc_type');
            $table->foreignUuid('uploaded_by_vendor_id')
                ->nullable()
                ->after('envelope_type')
                ->constrained('vendors')
                ->nullOnDelete();
            $table->string('original_filename', 255)
                ->nullable()
                ->after('title');

            $table->index(['bid_id', 'envelope_type'], 'bid_documents_bid_envelope_idx');
        });
    }

    public function down(): void
    {
        // MySQL 8 auto-promotes our (bid_id, envelope_type) composite index to
        // back the existing bid_id foreign key, so dropping the composite
        // directly fails with SQLSTATE 1553 ("Cannot drop index ...: needed
        // in a foreign key constraint"). Drop+restore the FK around the
        // index removal so the column drops cascade cleanly.
        Schema::table('bid_documents', function (Blueprint $table) {
            $table->dropForeign(['bid_id']);
        });
        Schema::table('bid_documents', function (Blueprint $table) {
            $table->dropIndex('bid_documents_bid_envelope_idx');
            $table->dropConstrainedForeignId('uploaded_by_vendor_id');
            $table->dropColumn(['envelope_type', 'original_filename']);
            $table->foreign('bid_id')->references('id')->on('bids')->cascadeOnDelete();
        });
    }
};
