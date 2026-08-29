<?php

// Table: vendor_documents — adds uploaded_by, recording the MPC user who filed
// a document on a vendor's behalf. NULL means the vendor uploaded it themselves
// through the portal, which is the only path that existed before this column.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            // Distinct from reviewed_by on purpose: an admin upload sets both,
            // but a vendor upload approved later sets only the latter, and the
            // two answer different questions in an audit.
            $table->foreignUuid('uploaded_by')
                ->nullable()
                ->after('vendor_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
        });
    }
};
