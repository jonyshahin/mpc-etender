<?php

namespace Database\Seeders;

use App\Enums\BidDocType;
use App\Enums\BidStatus;
use App\Enums\EnvelopeType;
use App\Enums\TenderStatus;
use App\Enums\VendorStatus;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\BoqItem;
use App\Models\BoqSection;
use App\Models\Category;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Local-dev fixture seeder. Creates 3 vendors and 7 tenders aligned to
 * specific bug-repro scenarios so the Docker stack starts populated
 * with the data Johnny needs to click around BUG-15, BUG-22, BUG-19.
 *
 * Idempotent — every model uses updateOrCreate keyed by a stable
 * natural key (email for users/vendors, code for projects, reference
 * number for tenders). Re-running the seeder reconciles changes
 * without duplicating rows.
 *
 * NOT called from DatabaseSeeder. Invoke explicitly:
 *     php artisan db:seed --class=DevDataSeeder
 *     # or via the Makefile:
 *     make seed-dev
 *
 * Depends on: RoleSeeder, PermissionSeeder, RolePermissionSeeder,
 * CategorySeeder, AdminUserSeeder having already run (covered by
 * `make fresh` which calls migrate:fresh --seed).
 */
class DevDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('→ DevDataSeeder: vendors');
        [$ahmed, $fatima, $vendor3] = $this->seedVendors();

        $this->command->info('→ DevDataSeeder: project + tenders');
        $admin = User::where('email', 'admin@mpc-group.com')->firstOrFail();
        $project = $this->seedProject($admin);

        $civilWorks = Category::where('name_en', 'Civil Works')->firstOrFail();
        $mep = Category::where('name_en', 'MEP')->firstOrFail();

        $tenders = $this->seedTenders($project, $admin, $civilWorks, $mep);

        $this->command->info('→ DevDataSeeder: VIZ-T003 sample PDF + Ahmed draft bid');
        $this->seedSampleBidWithPdf($tenders['VIZ-T003'], $ahmed);

        $this->command->info('→ DevDataSeeder: VIZ-T007 submitted + withdrawn bids');
        $this->seedT007Bids($tenders['VIZ-T007'], $ahmed, $vendor3);

        $this->command->info('✓ DevDataSeeder complete');
    }

    /**
     * @return array{0: Vendor, 1: Vendor, 2: Vendor}
     */
    private function seedVendors(): array
    {
        $civilWorks = Category::where('name_en', 'Civil Works')->firstOrFail();
        $mep = Category::where('name_en', 'MEP')->firstOrFail();
        $hvac = Category::where('name_en', 'HVAC')->firstOrFail();

        $ahmed = Vendor::updateOrCreate(
            ['email' => 'ahmed@al-rashid.iq'],
            [
                'company_name' => 'Al-Rashid Construction Co.',
                'company_name_ar' => 'شركة الرشيد للإنشاءات',
                'trade_license_no' => 'TL-AR-100001',
                'contact_person' => 'Ahmed Al-Rashid',
                'password' => Hash::make('password'),
                'phone' => '+964-770-100-0001',
                'address' => 'Al-Mansour District',
                'city' => 'Baghdad',
                'country' => 'Iraq',
                'prequalification_status' => VendorStatus::Qualified,
                'qualified_at' => now(),
                'language_pref' => 'en',
                'is_active' => true,
            ],
        );
        $ahmed->categories()->sync([$civilWorks->id, $mep->id]);

        $fatima = Vendor::updateOrCreate(
            ['email' => 'fatima@erbil-mep.iq'],
            [
                'company_name' => 'Erbil MEP Solutions',
                'company_name_ar' => 'حلول الميكانيك والكهرباء أربيل',
                'trade_license_no' => 'TL-EM-100002',
                'contact_person' => 'Fatima Hassan',
                'password' => Hash::make('password'),
                'phone' => '+964-770-100-0002',
                'address' => 'Industrial Zone',
                'city' => 'Erbil',
                'country' => 'Iraq',
                'prequalification_status' => VendorStatus::Qualified,
                'qualified_at' => now(),
                'language_pref' => 'ar',
                'is_active' => true,
            ],
        );
        // MEP + HVAC: HVAC overlaps with VIZ-T002 (which Ahmed deliberately
        // doesn't see). Keeping T002 reachable from a vendor perspective is
        // important for the future BUG-16 publish-with-zero-BOQ fix testing.
        $fatima->categories()->sync([$mep->id, $hvac->id]);

        $vendor3 = Vendor::updateOrCreate(
            ['email' => 'viz-vendor-3@test.local'],
            [
                'company_name' => 'Throwaway Test Vendor',
                'company_name_ar' => null,
                'trade_license_no' => 'TL-TV-100003',
                'contact_person' => 'Viz Tester',
                'password' => Hash::make('password'),
                'phone' => '+964-770-100-0003',
                'address' => 'Test Address',
                'city' => 'Baghdad',
                'country' => 'Iraq',
                'prequalification_status' => VendorStatus::Qualified,
                'qualified_at' => now(),
                'language_pref' => 'en',
                'is_active' => true,
            ],
        );
        $vendor3->categories()->sync([$civilWorks->id]);

        return [$ahmed, $fatima, $vendor3];
    }

    private function seedProject(User $admin): Project
    {
        $project = Project::updateOrCreate(
            ['code' => 'VIZ'],
            [
                'name' => 'MPC Visualizer Test Project',
                'name_ar' => 'مشروع اختبار العرض البصري',
                'description' => 'Synthetic project used by DevDataSeeder to host the 7 dev-stack test tenders.',
                'location' => 'Baghdad, Iraq',
                'client_name' => 'MPC (Internal)',
                'status' => 'active',
                'start_date' => now()->subMonths(3),
                'end_date' => now()->addYear(),
                'created_by' => $admin->id,
            ],
        );

        // TenderController::index scopes by user_project pivot — even
        // super_admin sees nothing without a pivot row. Attach admin
        // (and any future MPC user) so the seeded tenders are visible
        // immediately after `make seed-dev`.
        $project->users()->syncWithoutDetaching([
            $admin->id => ['project_role' => 'project_manager', 'assigned_at' => now()],
        ]);

        return $project;
    }

    /**
     * @return array<string, Tender>
     */
    private function seedTenders(Project $project, User $admin, Category $civilWorks, Category $mep): array
    {
        // T002 lives in HVAC so Ahmed (Civil + MEP) doesn't see it on his
        // dashboard — keeps his view cleanly scoped to BUG-15/BUG-19 repro
        // tenders. Fatima (MEP + HVAC after the future second-vendor seeding)
        // still picks it up.
        $hvac = Category::where('name_en', 'HVAC')->firstOrFail();

        // VIZ-T001 — Healthy baseline. Single-envelope, fully BOQ'd, published, deadline +7d.
        $t001 = $this->upsertTender('VIZ-T001', [
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title_en' => '[VIZ-T001] Single-envelope healthy baseline',
            'title_ar' => '[VIZ-T001] مرجع سليم بمظروف واحد',
            'description_en' => 'Fully-BOQ\'d single-envelope tender for general dev smoke tests.',
            'tender_type' => 'open',
            'status' => TenderStatus::Published,
            'estimated_value' => 250000,
            'currency' => 'USD',
            'publish_date' => now(),
            'submission_deadline' => now()->addDays(7),
            'opening_date' => now()->addDays(8),
            'is_two_envelope' => false,
            'technical_pass_score' => null,
        ]);
        $this->ensureBoq($t001, sections: 1, itemsPerSection: 3);
        $t001->categories()->sync([$civilWorks->id]);

        // VIZ-T002 — Empty-BOQ published tender. Reproduces the BUG-16 condition
        // (TenderService::publish() should reject empty-BOQ but currently doesn't).
        $t002 = $this->upsertTender('VIZ-T002', [
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title_en' => '[VIZ-T002] Single-envelope, ZERO BOQ items (BUG-16 repro)',
            'title_ar' => null,
            'description_en' => 'Published with no BOQ — verifies the future BUG-16 publish guard.',
            'tender_type' => 'open',
            'status' => TenderStatus::Published,
            'estimated_value' => 100000,
            'currency' => 'USD',
            'publish_date' => now(),
            'submission_deadline' => now()->addDays(14),
            'opening_date' => now()->addDays(15),
            'is_two_envelope' => false,
            'technical_pass_score' => null,
        ]);
        // Intentionally NO BOQ.
        // Category=HVAC so Ahmed (Civil + MEP) doesn't see it; Fatima can.
        $t002->categories()->sync([$hvac->id]);

        // VIZ-T003 — Two-envelope, pass score 70, BOQ + categories matched to Ahmed.
        // Used for BUG-15 repro AND for the pre-seeded sample PDF on Ahmed's draft bid.
        $t003 = $this->upsertTender('VIZ-T003', [
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title_en' => '[VIZ-T003] Two-envelope, pass score 70 (BUG-15 repro)',
            'title_ar' => '[VIZ-T003] مظروفان، درجة النجاح ٧٠',
            'description_en' => 'Two-envelope tender with technical_pass_score=70. Categories match Ahmed.',
            'tender_type' => 'open',
            'status' => TenderStatus::Published,
            'estimated_value' => 500000,
            'currency' => 'USD',
            'publish_date' => now(),
            'submission_deadline' => now()->addDays(14),
            'opening_date' => now()->addDays(15),
            'is_two_envelope' => true,
            'technical_pass_score' => 70,
        ]);
        $this->ensureBoq($t003, sections: 2, itemsPerSection: 3);
        $t003->categories()->sync([$civilWorks->id, $mep->id]);

        // VIZ-T004 — Two-envelope WITHOUT technical_pass_score — alternate BUG-15 path.
        $t004 = $this->upsertTender('VIZ-T004', [
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title_en' => '[VIZ-T004] Two-envelope, NO pass score (BUG-15 alt)',
            'title_ar' => null,
            'description_en' => 'Two-envelope but technical_pass_score is null — should fail publish prereqs.',
            'tender_type' => 'open',
            'status' => TenderStatus::Published,
            'estimated_value' => 300000,
            'currency' => 'USD',
            'publish_date' => now(),
            'submission_deadline' => now()->addDays(14),
            'opening_date' => now()->addDays(15),
            'is_two_envelope' => true,
            'technical_pass_score' => null,
        ]);
        $this->ensureBoq($t004, sections: 1, itemsPerSection: 2);
        $t004->categories()->sync([$mep->id]);

        // VIZ-T005 — Draft single-envelope, partially-filled BOQ. For wizard editing repros.
        $t005 = $this->upsertTender('VIZ-T005', [
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title_en' => '[VIZ-T005] Draft single-envelope (wizard editing repros)',
            'title_ar' => null,
            'description_en' => 'Draft tender with one partial BOQ section — for editing/wizard tests.',
            'tender_type' => 'open',
            'status' => TenderStatus::Draft,
            'estimated_value' => 150000,
            'currency' => 'USD',
            'publish_date' => null,
            'submission_deadline' => now()->addDays(30),
            'opening_date' => now()->addDays(31),
            'is_two_envelope' => false,
            'technical_pass_score' => null,
        ]);
        $this->ensureBoq($t005, sections: 1, itemsPerSection: 1);
        $t005->categories()->sync([$civilWorks->id]);

        // VIZ-T006 — Was-published-then-cancelled. Used for BUG-23 (addenda on cancelled).
        $t006 = $this->upsertTender('VIZ-T006', [
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title_en' => '[VIZ-T006] Cancelled tender (BUG-23 addenda repro)',
            'title_ar' => null,
            'description_en' => 'Was published, then cancelled. Addenda tab should disable Issue button.',
            'tender_type' => 'open',
            'status' => TenderStatus::Cancelled,
            'estimated_value' => 200000,
            'currency' => 'USD',
            'publish_date' => now()->subDays(10),
            'submission_deadline' => now()->addDays(7),
            'opening_date' => now()->addDays(8),
            'is_two_envelope' => false,
            'technical_pass_score' => null,
            'cancelled_reason' => 'Cancelled by DevDataSeeder for fixture purposes.',
        ]);
        $this->ensureBoq($t006, sections: 1, itemsPerSection: 2);
        $t006->categories()->sync([$civilWorks->id]);

        // VIZ-T007 — Two-envelope published with one submitted bid (Ahmed) and
        // one withdrawn bid (vendor3). Used for BUG-19 local repro: withdraw →
        // start bid → unique constraint violation guard.
        $t007 = $this->upsertTender('VIZ-T007', [
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title_en' => '[VIZ-T007] Two-envelope w/ submitted + withdrawn bids (BUG-19 repro)',
            'title_ar' => null,
            'description_en' => 'Pre-seeded with one submitted bid (Ahmed) and one withdrawn bid (vendor3).',
            'tender_type' => 'open',
            'status' => TenderStatus::Published,
            'estimated_value' => 750000,
            'currency' => 'USD',
            'publish_date' => now()->subDays(2),
            'submission_deadline' => now()->addDays(14),
            'opening_date' => now()->addDays(15),
            'is_two_envelope' => true,
            'technical_pass_score' => 65,
        ]);
        $this->ensureBoq($t007, sections: 1, itemsPerSection: 3);
        $t007->categories()->sync([$civilWorks->id, $mep->id]);

        return [
            'VIZ-T001' => $t001,
            'VIZ-T002' => $t002,
            'VIZ-T003' => $t003,
            'VIZ-T004' => $t004,
            'VIZ-T005' => $t005,
            'VIZ-T006' => $t006,
            'VIZ-T007' => $t007,
        ];
    }

    private function upsertTender(string $reference, array $attributes): Tender
    {
        return Tender::updateOrCreate(['reference_number' => $reference], $attributes);
    }

    /**
     * Idempotent BOQ creation — only seeds if the tender has no sections yet.
     * Avoids blowing up unique constraints or duplicating items on re-seed.
     */
    private function ensureBoq(Tender $tender, int $sections, int $itemsPerSection): void
    {
        if ($tender->boqSections()->exists()) {
            return;
        }

        for ($s = 0; $s < $sections; $s++) {
            $section = BoqSection::create([
                'tender_id' => $tender->id,
                'title' => 'Section '.($s + 1),
                'title_ar' => 'القسم '.($s + 1),
                'sort_order' => $s,
            ]);

            for ($i = 0; $i < $itemsPerSection; $i++) {
                BoqItem::create([
                    'section_id' => $section->id,
                    'item_code' => sprintf('S%d.%d', $s + 1, $i + 1),
                    'description_en' => sprintf('Item %d.%d — synthetic dev fixture', $s + 1, $i + 1),
                    'description_ar' => null,
                    'unit' => 'm³',
                    'quantity' => 100 * ($i + 1),
                    'sort_order' => $i,
                ]);
            }
        }
    }

    private function seedSampleBidWithPdf(Tender $tender, Vendor $ahmed): void
    {
        $bid = Bid::firstOrCreate(
            ['tender_id' => $tender->id, 'vendor_id' => $ahmed->id],
            [
                'bid_reference' => 'BID-'.$tender->reference_number.'-AHMED',
                'envelope_type' => EnvelopeType::Single,
                'status' => BidStatus::Draft,
                'is_sealed' => false,
                'currency' => $tender->currency,
            ],
        );

        $path = 'bid-docs/sample-technical-proposal.pdf';
        $pdfBytes = $this->minimalPdf();

        // Always (re)write the sample so a fresh MinIO bucket gets it back
        // — this is dev data, churn is fine.
        Storage::disk('s3')->put($path, $pdfBytes);

        BidDocument::firstOrCreate(
            ['bid_id' => $bid->id, 'title' => 'Methodology'],
            [
                'original_filename' => 'sample-technical-proposal.pdf',
                'file_path' => $path,
                'file_size' => strlen($pdfBytes),
                'mime_type' => 'application/pdf',
                'doc_type' => BidDocType::TechnicalProposal,
                'envelope_type' => 'technical',
                'uploaded_by_vendor_id' => $ahmed->id,
                'uploaded_at' => now(),
            ],
        );
    }

    private function seedT007Bids(Tender $tender, Vendor $ahmed, Vendor $vendor3): void
    {
        // Ahmed's submitted bid.
        Bid::firstOrCreate(
            ['tender_id' => $tender->id, 'vendor_id' => $ahmed->id],
            [
                'bid_reference' => 'BID-'.$tender->reference_number.'-AHMED',
                'envelope_type' => EnvelopeType::Single,
                'status' => BidStatus::Submitted,
                'is_sealed' => true,
                'submitted_at' => now()->subDay(),
                'total_amount' => 720000,
                'currency' => $tender->currency,
            ],
        );

        // vendor3's withdrawn bid — for BUG-19 (withdraw → re-start should not
        // hit a unique constraint; the bid row stays, the new bid attempt
        // should reuse it).
        Bid::firstOrCreate(
            ['tender_id' => $tender->id, 'vendor_id' => $vendor3->id],
            [
                'bid_reference' => 'BID-'.$tender->reference_number.'-V3',
                'envelope_type' => EnvelopeType::Single,
                'status' => BidStatus::Withdrawn,
                'is_sealed' => false,
                'withdrawal_reason' => 'Reconsidering pricing — DevDataSeeder fixture.',
                'currency' => $tender->currency,
            ],
        );
    }

    /**
     * Minimal valid PDF — ~350 bytes, opens as a blank page in any PDF
     * reader, passes Laravel's `mimes:pdf` (finfo magic-byte sniff).
     * Avoids checking a binary fixture file into git that the repo
     * would have to round-trip through line-ending normalization.
     */
    private function minimalPdf(): string
    {
        return implode("\n", [
            '%PDF-1.4',
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> >> endobj',
            'xref',
            '0 4',
            '0000000000 65535 f ',
            '0000000009 00000 n ',
            '0000000063 00000 n ',
            '0000000115 00000 n ',
            'trailer << /Size 4 /Root 1 0 R >>',
            'startxref',
            '187',
            '%%EOF',
            '',
        ]);
    }
}
