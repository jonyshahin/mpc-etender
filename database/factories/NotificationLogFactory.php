<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'notification_id' => Notification::factory(),
            'channel' => fake()->randomElement(NotificationChannel::cases()),
            'delivery_status' => DeliveryStatus::Queued,
            'external_message_id' => null,
            'error_message' => null,
            'retry_count' => 0,
            'sent_at' => null,
            'delivered_at' => null,
        ];
    }
}
