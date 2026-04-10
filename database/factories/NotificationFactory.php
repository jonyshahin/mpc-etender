<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'vendor_id' => null,
            'notifiable_type' => Tender::class,
            'notifiable_id' => fake()->uuid(),
            'notification_type' => fake()->randomElement(NotificationType::cases()),
            'title_en' => fake()->sentence(),
            'title_ar' => null,
            'body_en' => fake()->paragraph(),
            'body_ar' => null,
            'data' => null,
            'read_at' => null,
            'created_at' => now(),
        ];
    }
}
