<?php

namespace App\Services;

use App\Enums\VendorStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Handle vendor registration and prequalification workflow.
 */
class VendorService
{
    /**
     * Register a new vendor from the public registration form.
     *
     * @param  array  $data  Validated registration data
     * @return Vendor The created vendor with pending status
     */
    public function register(array $data): Vendor
    {
        return DB::transaction(function () use ($data) {
            $categoryIds = $data['category_ids'] ?? [];
            unset($data['category_ids'], $data['password_confirmation']);

            $data['password'] = Hash::make($data['password']);
            $data['prequalification_status'] = VendorStatus::Pending;
            $data['is_active'] = true;

            $vendor = Vendor::create($data);

            if ($categoryIds) {
                $vendor->categories()->attach($categoryIds);
            }

            return $vendor;
        });
    }

    /**
     * Approve a vendor's prequalification.
     */
    public function prequalify(Vendor $vendor, User $reviewer): void
    {
        $vendor->update([
            'prequalification_status' => VendorStatus::Qualified,
            'qualified_at' => now(),
            'qualified_by' => $reviewer->id,
            'rejection_reason' => null,
        ]);

        AuditLog::create([
            'user_id' => $reviewer->id,
            'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id,
            'action' => 'approved',
            'old_values' => ['prequalification_status' => 'pending'],
            'new_values' => ['prequalification_status' => 'qualified'],
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
            'old_values' => ['prequalification_status' => $vendor->getOriginal('prequalification_status')],
            'new_values' => ['prequalification_status' => 'suspended', 'rejection_reason' => $reason],
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
