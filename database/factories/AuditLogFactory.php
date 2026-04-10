<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'vendor_id' => null,
            'auditable_type' => Tender::class,
            'auditable_id' => fake()->uuid(),
            'action' => fake()->randomElement(AuditAction::cases()),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }
}
