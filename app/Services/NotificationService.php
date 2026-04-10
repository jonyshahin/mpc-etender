<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Centralized multi-channel notification dispatch.
 * All notifications in the system go through this service.
 */
class NotificationService
{
    /**
     * Dispatch a notification to a user across configured channels.
     *
     * @param  array<string, mixed>  $data  Template variable replacements
     * @param  array<NotificationChannel>|null  $channels  Override channels (defaults to all active template channels)
     */
    public function notifyUser(
        User $user,
        NotificationType $type,
        array $data = [],
        ?array $channels = null,
    ): Notification {
        $lang = $user->language_pref ?? 'en';

        return $this->dispatch($type, $data, $lang, $channels, userId: $user->id, email: $user->email);
    }

    /**
     * Dispatch a notification to a vendor across configured channels.
     */
    public function notifyVendor(
        Vendor $vendor,
        NotificationType $type,
        array $data = [],
        ?array $channels = null,
    ): Notification {
        $lang = $vendor->language_pref ?? 'ar';

        return $this->dispatch($type, $data, $lang, $channels, vendorId: $vendor->id, email: $vendor->email, phone: $vendor->phone, whatsapp: $vendor->whatsapp_number);
    }

    /**
     * Core dispatch logic: resolve template, create notification record, send per channel.
     */
    private function dispatch(
        NotificationType $type,
        array $data,
        string $lang,
        ?array $channels,
        ?string $userId = null,
        ?string $vendorId = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $whatsapp = null,
    ): Notification {
        $templates = NotificationTemplate::active()
            ->where('notification_type', $type)
            ->get();

        // Resolve title/body from first template or fallback
        $inAppTemplate = $templates->firstWhere('channel', NotificationChannel::InApp)
            ?? $templates->first();

        $titleEn = $this->interpolate($inAppTemplate?->subject_en ?? $type->value, $data);
        $titleAr = $this->interpolate($inAppTemplate?->subject_ar ?? $type->value, $data);
        $bodyEn = $this->interpolate($inAppTemplate?->body_template_en ?? '', $data);
        $bodyAr = $this->interpolate($inAppTemplate?->body_template_ar ?? '', $data);

        $notification = Notification::create([
            'user_id' => $userId,
            'vendor_id' => $vendorId,
            'notifiable_type' => $userId ? User::class : Vendor::class,
            'notifiable_id' => $userId ?? $vendorId,
            'notification_type' => $type,
            'title_en' => $titleEn,
            'title_ar' => $titleAr,
            'body_en' => $bodyEn,
            'body_ar' => $bodyAr,
            'data' => $data,
            'created_at' => now(),
        ]);

        // Determine channels to send on
        $sendChannels = $channels ?? $templates->pluck('channel')->unique()->all();

        foreach ($sendChannels as $channel) {
            $template = $templates->firstWhere('channel', $channel);
            $this->sendChannel($notification, $channel, $template, $data, $lang, $email, $phone, $whatsapp);
        }

        return $notification;
    }

    /**
     * Send a notification via a specific channel.
     */
    private function sendChannel(
        Notification $notification,
        NotificationChannel $channel,
        ?NotificationTemplate $template,
        array $data,
        string $lang,
        ?string $email,
        ?string $phone,
        ?string $whatsapp,
    ): void {
        $log = NotificationLog::create([
            'notification_id' => $notification->id,
            'channel' => $channel,
            'delivery_status' => DeliveryStatus::Queued,
            'retry_count' => 0,
        ]);

        try {
            match ($channel) {
                NotificationChannel::Email => $this->sendEmail($notification, $template, $data, $lang, $email, $log),
                NotificationChannel::Whatsapp => $this->sendWhatsApp($notification, $template, $data, $lang, $whatsapp, $log),
                NotificationChannel::Sms => $this->sendSms($notification, $template, $data, $lang, $phone, $log),
                NotificationChannel::InApp => $this->markInAppSent($log),
                NotificationChannel::Broadcast => $this->markInAppSent($log),
            };
        } catch (\Throwable $e) {
            $log->update([
                'delivery_status' => DeliveryStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
            Log::error("Notification dispatch failed on {$channel->value}", [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send via email using Laravel Mail.
     */
    private function sendEmail(
        Notification $notification,
        ?NotificationTemplate $template,
        array $data,
        string $lang,
        ?string $email,
        NotificationLog $log,
    ): void {
        if (! $email) {
            return;
        }

        $subject = $lang === 'ar'
            ? ($notification->title_ar ?: $notification->title_en)
            : $notification->title_en;

        $body = $lang === 'ar'
            ? ($notification->body_ar ?: $notification->body_en)
            : $notification->body_en;

        Mail::raw($body, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });

        $log->update([
            'delivery_status' => DeliveryStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * WhatsApp channel stub — ready for BSP integration.
     */
    private function sendWhatsApp(
        Notification $notification,
        ?NotificationTemplate $template,
        array $data,
        string $lang,
        ?string $whatsapp,
        NotificationLog $log,
    ): void {
        if (! $whatsapp) {
            return;
        }

        // Stub: log the message for future BSP integration
        Log::info('WhatsApp notification stub', [
            'to' => $whatsapp,
            'template_name' => $template?->whatsapp_template_name,
            'notification_id' => $notification->id,
        ]);

        $log->update([
            'delivery_status' => DeliveryStatus::Sent,
            'sent_at' => now(),
            'external_message_id' => 'stub-'.uniqid(),
        ]);
    }

    /**
     * SMS channel stub — ready for gateway integration.
     */
    private function sendSms(
        Notification $notification,
        ?NotificationTemplate $template,
        array $data,
        string $lang,
        ?string $phone,
        NotificationLog $log,
    ): void {
        if (! $phone) {
            return;
        }

        Log::info('SMS notification stub', [
            'to' => $phone,
            'notification_id' => $notification->id,
        ]);

        $log->update([
            'delivery_status' => DeliveryStatus::Sent,
            'sent_at' => now(),
            'external_message_id' => 'stub-'.uniqid(),
        ]);
    }

    /**
     * Mark in-app notification as sent (no external dispatch needed).
     */
    private function markInAppSent(NotificationLog $log): void
    {
        $log->update([
            'delivery_status' => DeliveryStatus::Delivered,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);
    }

    /**
     * Replace {{placeholder}} tokens in template strings.
     */
    private function interpolate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{{$key}}}", (string) $value, $template);
        }

        return $template;
    }
}
