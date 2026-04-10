<?php

namespace App\Models;

use App\Enums\VendorStatus;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * External supplier account. Authenticates via the `vendor` guard.
 *
 * Relationships: documents, categories, bids, notifications, qualifiedBy, awards.
 */
class Vendor extends Authenticatable
{
    /** @use HasFactory<VendorFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_name',
        'company_name_ar',
        'trade_license_no',
        'contact_person',
        'email',
        'password',
        'phone',
        'whatsapp_number',
        'address',
        'city',
        'country',
        'website',
        'prequalification_status',
        'qualified_at',
        'qualified_by',
        'rejection_reason',
        'language_pref',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'prequalification_status' => VendorStatus::class,
            'password' => 'hashed',
            'qualified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'vendor_categories')
            ->using(Concerns\UuidPivot::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function qualifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qualified_by');
    }

    // ── Scopes ──

    public function scopeQualified($query)
    {
        return $query->where('prequalification_status', VendorStatus::Qualified);
    }

    public function scopeInCategory($query, string $categoryId)
    {
        return $query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
