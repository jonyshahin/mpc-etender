<?php

namespace App\Models;

use App\Enums\NotificationType;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persisted in-app/multichannel notification addressed to a user or vendor.
 *
 * Relationships: user, vendor, logs.
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'vendor_id',
        'notifiable_type',
        'notifiable_id',
        'notification_type',
        'title_en',
        'title_ar',
        'body_en',
        'body_ar',
        'data',
        'read_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_type' => NotificationType::class,
            'data' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
