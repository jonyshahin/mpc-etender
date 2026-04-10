<?php

namespace Database\Factories;

use App\Models\DocumentAccessLog;
use App\Models\TenderDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentAccessLog>
 */
class DocumentAccessLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'vendor_id' => null,
            'document_type' => TenderDocument::class,
            'document_id' => fake()->uuid(),
            'action' => fake()->randomElement(['viewed', 'downloaded', 'printed']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'accessed_at' => now(),
        ];
    }
}
