<?php

namespace App\Services;

use App\Enums\VendorStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Handle vendor onboarding and prequalification workflow.
 */
class VendorService
{
    /**
     * Onboard a new vendor on behalf of an MPC admin.
     *
     * This is the only path that creates a vendor record — public
     * self-registration was removed, so every vendor is traceable to the admin
     * who created it (unlike the old register(), which wrote no audit row).
     *
     * The vendor is created in Pending status: creating the account and
     * vouching for it are separate steps, so the existing prequalification
     * review still applies. The caller supplies a temporary password and is
     * responsible for surfacing it to the admin exactly once.
     *
     * @param  array  $data  Validated data from Admin\StoreVendorRequest
     * @param  User  $creator  The admin performing the onboarding
     * @param  string  $temporaryPassword  Plain-text temp password to hash and store
     * @return Vendor The created vendor, pending prequalification
     */
    public function createByAdmin(array $data, User $creator, string $temporaryPassword): Vendor
    {
        return DB::transaction(function () use ($data, $creator, $temporaryPassword) {
            $categoryIds = $data['category_ids'] ?? [];
            unset($data['category_ids']);

            $data['password'] = Hash::make($temporaryPassword);
            $data['prequalification_status'] = VendorStatus::Pending;
            $data['is_active'] = true;
            // Admin-chosen credentials are transitional by definition — the
            // vendor.password.required middleware traps the vendor on the
            // change-password screen until they pick their own.
            $data['must_change_password'] = true;

            $vendor = Vendor::create($data);

            if ($categoryIds) {
                $vendor->categories()->attach($categoryIds);
            }

            AuditLog::create([
                'user_id' => $creator->id,
                'vendor_id' => $vendor->id,
                'auditable_type' => Vendor::class,
                'auditable_id' => $vendor->id,
                'action' => 'vendor_created_by_admin',
                'old_values' => null,
                'new_values' => [
                    'company_name' => $vendor->company_name,
                    'email' => $vendor->email,
                    'prequalification_status' => VendorStatus::Pending->value,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $vendor;
        });
    }

    /**
     * Correct a vendor's details on their behalf.
     *
     * Deliberately narrow: it writes the fields on the admin form and the
     * category assignment, and nothing else. Prequalification status,
     * credentials and is_active each have their own action with their own
     * audit row, and folding them in here would let a routine typo fix quietly
     * change a vendor's standing.
     *
     * Two of these fields are not cosmetic. `email` is the vendor's login
     * identity, so changing it changes who can sign in; `category_ids` decides
     * which tenders they are eligible for. Both show up in the diff below.
     *
     * @param  array  $data  Validated data from Admin\UpdateVendorRequest
     * @param  User  $editor  The admin making the change
     */
    public function updateByAdmin(Vendor $vendor, array $data, User $editor): Vendor
    {
        return DB::transaction(function () use ($vendor, $data, $editor) {
            $categoryIds = $data['category_ids'] ?? null;
            unset($data['category_ids']);

            // Captured before update() — Eloquent resyncs $original on save, so
            // getOriginal() afterwards returns the value just written.
            $before = $vendor->only(array_keys($data));
            $categoriesBefore = $vendor->categories()->pluck('categories.id')->sort()->values()->all();

            $vendor->update($data);

            if ($categoryIds !== null) {
                // sync(), not attach(): an edit removes categories as well as
                // adding them. The pivot is a UuidPivot, so its key is generated.
                $vendor->categories()->sync($categoryIds);
            }

            $after = $vendor->only(array_keys($data));
            $categoriesAfter = $vendor->categories()->pluck('categories.id')->sort()->values()->all();

            // Only what actually moved. An audit row listing every field on
            // every save buries the one thing a reader came looking for.
            $changed = array_keys(array_diff_assoc(
                array_map(strval(...), $after),
                array_map(strval(...), $before),
            ));

            if ($categoriesBefore !== $categoriesAfter) {
                $changed[] = 'category_ids';
                $before['category_ids'] = $categoriesBefore;
                $after['category_ids'] = $categoriesAfter;
            }

            if ($changed === []) {
                return $vendor->refresh();
            }

            AuditLog::create([
                'user_id' => $editor->id,
                'vendor_id' => $vendor->id,
                'auditable_type' => Vendor::class,
                'auditable_id' => $vendor->id,
                'action' => 'vendor_updated_by_admin',
                'old_values' => Arr::only($before, $changed),
                'new_values' => Arr::only($after, $changed),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $vendor->refresh();
        });
    }

    /**
     * Approve a vendor's prequalification.
     */
    public function prequalify(Vendor $vendor, User $reviewer): void
    {
        // Captured before update() — Eloquent syncs $original on save, so
        // getOriginal() after update() returns the just-saved value.
        $oldIsActive = $vendor->is_active;

        $vendor->update([
            'prequalification_status' => VendorStatus::Qualified,
            'qualified_at' => now(),
            'qualified_by' => $reviewer->id,
            'rejection_reason' => null,
            // Suspend flips this to false; re-qualifying must restore it or
            // the bid-start guard blocks the vendor forever (BUG-09).
            'is_active' => true,
        ]);

        AuditLog::create([
            'user_id' => $reviewer->id,
            'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id,
            'action' => 'approved',
            'old_values' => ['prequalification_status' => 'pending', 'is_active' => $oldIsActive],
            'new_values' => ['prequalification_status' => 'qualified', 'is_active' => true],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Reject a vendor's prequalification with reason.
     */
    public function reject(Vendor $vendor, User $reviewer, string $reason): void
    {
        $vendor->update([
            'prequalification_status' => VendorStatus::Rejected,
            'rejection_reason' => $reason,
        ]);

        AuditLog::create([
            'user_id' => $reviewer->id,
            'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id,
            'action' => 'rejected',
            'old_values' => ['prequalification_status' => $vendor->getOriginal('prequalification_status')],
            'new_values' => ['prequalification_status' => 'rejected', 'rejection_reason' => $reason],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Suspend a vendor with reason.
     */
    public function suspend(Vendor $vendor, User $reviewer, string $reason): void
    {
        // Captured before update() — see prequalify() for the getOriginal gotcha.
        $oldStatus = $vendor->prequalification_status?->value;
        $oldIsActive = $vendor->is_active;

        $vendor->update([
            'prequalification_status' => VendorStatus::Suspended,
            'rejection_reason' => $reason,
            'is_active' => false,
        ]);

        AuditLog::create([
            'user_id' => $reviewer->id,
            'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id,
            'action' => 'updated',
            'old_values' => ['prequalification_status' => $oldStatus, 'is_active' => $oldIsActive],
            'new_values' => ['prequalification_status' => 'suspended', 'rejection_reason' => $reason, 'is_active' => false],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get all qualified vendors that belong to any of the given categories.
     *
     * @param  array  $categoryIds  Category UUIDs to filter by
     * @return Collection<int, Vendor>
     */
    public function getQualifiedVendorsForCategories(array $categoryIds): Collection
    {
        return Vendor::qualified()
            ->active()
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->get();
    }
}
