<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class VendorResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('vendor.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $name = $notifiable->contact_person ?: $notifiable->company_name;

        return (new MailMessage)
            ->subject(__('mail.vendor_reset_subject'))
            ->greeting(__('mail.vendor_reset_greeting', ['name' => $name]))
            ->line(__('mail.vendor_reset_line1'))
            ->action(__('mail.vendor_reset_button'), $url)
            ->line(__('mail.vendor_reset_expiry', ['minutes' => config('auth.passwords.vendors.expire')]))
            ->line(__('mail.vendor_reset_ignore'));
    }
}
