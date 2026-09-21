<?php

namespace App\Notifications;

use App\Models\ChecklistSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChecklistUnfilledReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ChecklistSession $session,
        public int $unfilledCount,
        public int $totalCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[SMKI] Checklist {$this->session->periode} belum lengkap ({$this->unfilledCount} dari {$this->totalCount} entri)")
            ->view('emails.checklist-unfilled-reminder', [
                'recipientName' => $notifiable->name,
                'periode' => $this->session->periode,
                'unitName' => $this->session->unit?->nama ?? 'Unit Anda',
                'unfilledCount' => $this->unfilledCount,
                'totalCount' => $this->totalCount,
                'actionUrl' => url("/admin/pic/checklist/{$this->session->id}"),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'checklist_unfilled_reminder',
            'title' => "Checklist {$this->session->periode} belum lengkap",
            'message' => "{$this->unfilledCount} dari {$this->totalCount} entri checklist periode {$this->session->periode} belum diisi.",
            'session_id' => $this->session->id,
            'periode' => $this->session->periode,
            'unit_id' => $this->session->unit_id,
            'unfilled_count' => $this->unfilledCount,
            'total_count' => $this->totalCount,
            'url' => "/admin/pic/checklist/{$this->session->id}",
            'severity' => 'warning',
        ];
    }
}
