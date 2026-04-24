<?php

namespace App\Notifications;

use App\Models\VendorCategoryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorCategoryRequestApproved extends Notification
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
            ->subject(__('mail.vendor_category_request_approved.subject'))
            ->greeting(__('mail.vendor_category_request_approved.greeting', [
                'name' => $notifiable->contact_person ?: $notifiable->company_name,
            ]))
            ->line(__('mail.vendor_category_request_approved.line1'))
            ->line(__('mail.vendor_category_request_approved.comments', [
                'comments' => $this->request->reviewer_comments ?: '—',
            ]))
            ->action(
                __('mail.vendor_category_request_approved.action'),
                url(route('vendor.category-requests.show', $this->request->id, false))
            );
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'vendor_category_request_approved',
            'request_id' => $this->request->id,
            'comments' => $this->request->reviewer_comments,
        ];
    }
}
