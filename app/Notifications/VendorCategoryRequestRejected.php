<?php

namespace App\Notifications;

use App\Models\VendorCategoryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorCategoryRequestRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VendorCategoryRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.vendor_category_request_rejected.subject'))
            ->greeting(__('mail.vendor_category_request_rejected.greeting', [
                'name' => $notifiable->contact_person ?: $notifiable->company_name,
            ]))
            ->line(__('mail.vendor_category_request_rejected.line1'))
            ->line(__('mail.vendor_category_request_rejected.comments', [
                'comments' => $this->request->reviewer_comments,
            ]))
            ->action(
                __('mail.vendor_category_request_rejected.action'),
                url(route('vendor.category-requests.show', $this->request->id, false))
            );
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'vendor_category_request_rejected',
            'request_id' => $this->request->id,
            'comments' => $this->request->reviewer_comments,
        ];
    }
}
