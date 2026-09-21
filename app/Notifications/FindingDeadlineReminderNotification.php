<?php

namespace App\Notifications;

use App\Models\Finding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FindingDeadlineReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Finding $finding,
        public int $daysRemaining,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $kodeKlausul = $this->finding->control?->kode_klausul ?? 'SMKI';
        $judulKontrol = $this->finding->control?->judul ?? 'Kontrol Keamanan Informasi';
        $deadlineStr = $this->finding->deadline ? $this->finding->deadline->format('d M Y') : '-';
        $isOverdue = $this->daysRemaining < 0;
        $hLabel = $isOverdue ? 'Overdue' : "H-{$this->daysRemaining}";

        return (new MailMessage)
            ->subject("[SMKI] Tenggat Temuan {$hLabel}: {$kodeKlausul}")
            ->view('emails.finding-deadline-reminder', [
                'recipientName' => $notifiable->name,
                'kodeKlausul' => $kodeKlausul,
                'judulKontrol' => $judulKontrol,
                'kategori' => $this->finding->kategori,
                'deadlineStr' => $deadlineStr,
                'daysRemaining' => $this->daysRemaining,
                'isOverdue' => $isOverdue,
                'hLabel' => $hLabel,
                'actionUrl' => url("/temuan?id={$this->finding->id}"),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $kodeKlausul = $this->finding->control?->kode_klausul ?? 'SMKI';
        $isOverdue = $this->daysRemaining < 0;
        $hLabel = $isOverdue ? 'Overdue' : "H-{$this->daysRemaining}";

        return [
            'type' => 'finding_deadline_reminder',
            'title' => "Tenggat Temuan {$hLabel}: {$kodeKlausul}",
            'message' => $isOverdue
                ? "Temuan {$kodeKlausul} melewati tenggat."
                : "Temuan {$kodeKlausul} tenggat {$hLabel}.",
            'finding_id' => $this->finding->id,
            'kategori' => $this->finding->kategori,
            'deadline' => $this->finding->deadline?->format('Y-m-d'),
            'days_remaining' => $this->daysRemaining,
            'url' => "/temuan?id={$this->finding->id}",
            'severity' => $isOverdue ? 'danger' : 'warning',
        ];
    }
}
