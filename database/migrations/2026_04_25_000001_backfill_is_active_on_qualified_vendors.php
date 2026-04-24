<?php

// Data migration — Purpose: repair is_active=false on vendors whose
// prequalification_status is 'qualified'. Before BUG-09, VendorService::suspend()
// flipped is_active=false while prequalify() never reset it, so any vendor who
// was suspended and then re-approved was silently blocked by the bid-start
// guard. Writes one audit_logs entry per backfilled vendor so the paper trail
// survives on a procurement system.

use App\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $stuck = DB::table('vendors')
            ->where('prequalification_status', 'qualified')
            ->where('is_active', false)
            ->get(['id', 'email']);

        if ($stuck->isEmpty()) {
            Log::info('BUG-09 backfill: no qualified+inactive vendors found; no rows changed.');

            return;
        }

        $now = now();
        $auditRows = [];
        foreach ($stuck as $v) {
            DB::table('vendors')->where('id', $v->id)->update([
                'is_active' => true,
                'updated_at' => $now,
            ]);

            $auditRows[] = [
                'id' => (string) Str::uuid(),
                'user_id' => null,
                'vendor_id' => $v->id,
                'auditable_type' => Vendor::class,
                'auditable_id' => $v->id,
                'action' => 'is_active_backfill_bug09',
                'old_values' => json_encode(['is_active' => false]),
                'new_values' => json_encode(['is_active' => true]),
                'ip_address' => null,
                'user_agent' => 'artisan/migration',
                'created_at' => $now,
            ];
        }

        DB::table('audit_logs')->insert($auditRows);

        Log::info('BUG-09 backfill: restored is_active=true on '.$stuck->count().' vendor(s).', [
            'vendor_ids' => $stuck->pluck('id')->all(),
            'vendor_emails' => $stuck->pluck('email')->all(),
        ]);
    }

    public function down(): void
    {
        // Intentional no-op — rolling back the migration must not re-break
        // vendors. The audit_logs entries remain as the authoritative record
        // of the backfill.
    }
};
