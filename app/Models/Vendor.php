<?php

namespace App\Models;

use App\Enums\VendorStatus;
use App\Notifications\VendorResetPasswordNotification;
use Database\Factories\VendorFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
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
class Vendor extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<VendorFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, HasUuids, Notifiable;

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
        'must_change_password',
    ];

    /**
     * Never serialised. Reveal deliberately, never by default.
     *
     * `qualified_by` names the MPC user who signed this vendor off — internal
     * staffing, not the vendor's own record — and it was reaching them: the
     * profile page passed the whole model into Inertia, so every column bar
     * the password went over the wire.
     *
     * This hides the raw column only. The `qualifiedBy` relation still
     * serialises under the same `qualified_by` key, because relations are
     * filtered by their own name before being snake_cased — which is what lets
     * the admin vendor page keep rendering the reviewer's name. VendorTest
     * pins both halves of that.
     *
     * The vendor's other account state — prequalification_status, is_active,
     * last_login_at — is genuinely theirs, so it stays serialisable and the
     * profile controller projects the parts it means to show.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'qualified_by',
    ];

    protected function casts(): array
    {
        return [
            'prequalification_status' => VendorStatus::class,
            'password' => 'hashed',
            'qualified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Whether this vendor may bid at all.
     *
     * The tender-level checks — category match, deadline, no existing bid —
     * live in BidPolicy::create. This is the account-level half, and it is
     * what the dashboard and profile both report, so the two cannot disagree
     * about whether the vendor is in business.
     */
    public function canBid(): bool
    {
        return $this->prequalification_status === VendorStatus::Qualified
            && $this->is_active;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new VendorResetPasswordNotification($token));
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

    public function categoryRequests(): HasMany
    {
        return $this->hasMany(VendorCategoryRequest::class);
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
