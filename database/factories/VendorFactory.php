<?php

namespace Database\Factories;

use App\Enums\VendorStatus;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $companies = [
            'Al-Rashid Construction Co.',
            'Baghdad Steel Works',
            'Mesopotamia Electrical Ltd.',
            'Tigris Plumbing Services',
            'Euphrates HVAC Systems',
            'Babylon Finishing Group',
            'Samarra Engineering Corp.',
            'Diyala Heavy Industries',
        ];

        return [
            'company_name' => fake()->randomElement($companies).' '.fake()->unique()->numerify('###'),
            'company_name_ar' => null,
            'trade_license_no' => fake()->numerify('TL-########'),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->phoneNumber(),
            'whatsapp_number' => fake()->optional()->e164PhoneNumber(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'country' => 'Iraq',
            'website' => fake()->optional()->url(),
            'prequalification_status' => VendorStatus::Pending,
            'qualified_at' => null,
            'qualified_by' => null,
            'rejection_reason' => null,
            'language_pref' => 'ar',
            'is_active' => true,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function qualified(): static
    {
        return $this->state(fn () => [
            'prequalification_status' => VendorStatus::Qualified,
            'qualified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'prequalification_status' => VendorStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
