<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code,
        public int $expireMinutes = 5
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * The code is deliberately kept out of the subject line so it never leaks
     * into lock-screen previews, mailbox listings, or server mail logs.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[SMKI] Kode Verifikasi Atur Ulang Kata Sandi')
            ->view('emails.auth-reset-otp', [
                'recipientName' => $notifiable->name,
                'code' => $this->code,
                'count' => $this->expireMinutes,
            ]);
    }
}
