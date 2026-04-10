<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\VendorDocStatus;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorDocument>
 */
class VendorDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'document_type' => fake()->randomElement(DocumentType::cases()),
            'title' => fake()->words(3, true),
            'file_path' => 'vendor-docs/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(50000, 5000000),
            'mime_type' => 'application/pdf',
            'issue_date' => fake()->dateTimeBetween('-2 years', '-6 months'),
            'expiry_date' => fake()->dateTimeBetween('+1 month', '+2 years'),
            'status' => VendorDocStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ];
    }
}
