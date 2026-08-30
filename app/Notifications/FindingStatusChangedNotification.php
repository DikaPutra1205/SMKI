<?php

namespace App\Notifications;

use App\Models\Finding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FindingStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Finding $finding,
        public User $actor,
        public ?string $fromStatus,
        public string $toStatus,
        public string $catatan = ''
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
     * Human-readable label for status.
     */
    protected function statusLabel(?string $status): string
    {
        return match ($status) {
            'open' => 'Terbuka (Open)',
            'in_progress' => 'Dalam Penanganan (In Progress)',
            'resolved' => 'Selesai Ditindaklanjuti (Resolved)',
            'closed' => 'Ditutup & Terverifikasi (Closed)',
            default => $status ?? 'Awal',
        };
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $kodeKlausul = $this->finding->control?->kode_klausul ?? 'SMKI';
        $fromLabel = $this->statusLabel($this->fromStatus);
        $toLabel = $this->statusLabel($this->toStatus);

        return (new MailMessage)
            ->subject("[SMKI] Pembaruan Status Temuan: {$kodeKlausul} -> {$toLabel}")
            ->greeting("Halo, {$notifiable->name}")
            ->line("Status temuan audit untuk kontrol {$kodeKlausul} telah diperbarui oleh {$this->actor->name}.")
            ->line("Perubahan Status: {$fromLabel} ➔ {$toLabel}")
            ->when(! empty($this->catatan), fn ($mail) => $mail->line("Catatan Tindak Lanjut: {$this->catatan}"))
            ->action('Lihat Detail Temuan', url("/temuan?id={$this->finding->id}"))
            ->line('Silakan periksa detail temuan dan riwayat status di sistem SMKI.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $kodeKlausul = $this->finding->control?->kode_klausul ?? 'SMKI';
        $toLabel = $this->statusLabel($this->toStatus);

        $severity = match ($this->toStatus) {
            'closed' => 'success',
            'resolved' => 'info',
            'in_progress' => 'warning',
            default => 'danger',
        };

        return [
            'type' => 'finding_status_changed',
            'title' => "Status Temuan Diperbarui: {$kodeKlausul}",
            'message' => "Status temuan {$kodeKlausul} diubah menjadi {$toLabel} oleh {$this->actor->name}.",
            'finding_id' => $this->finding->id,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'catatan' => $this->catatan,
            'url' => "/temuan?id={$this->finding->id}",
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'severity' => $severity,
        ];
    }
}
