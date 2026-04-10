<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(),
            'channel' => fake()->randomElement(NotificationChannel::cases()),
            'notification_type' => fake()->randomElement(NotificationType::cases()),
            'subject_en' => fake()->sentence(),
            'subject_ar' => null,
            'body_template_en' => fake()->paragraph(),
            'body_template_ar' => fake()->paragraph(),
            'whatsapp_template_name' => null,
            'is_active' => true,
        ];
    }
}
