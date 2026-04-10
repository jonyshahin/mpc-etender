<?php

namespace Database\Factories;

use App\Enums\TenderDocType;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderDocument>
 */
class TenderDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'uploaded_by' => User::factory(),
            'title' => fake()->words(3, true),
            'file_path' => 'tender-docs/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(100000, 10000000),
            'mime_type' => 'application/pdf',
            'doc_type' => fake()->randomElement(TenderDocType::cases()),
            'version' => 1,
            'is_current' => true,
        ];
    }
}
