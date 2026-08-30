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
        $judulKontrol = $this->finding->control?->judul ?? 'Kontrol Keamanan Informasi';
        $unitName = $this->finding->unit?->nama ?? 'Unit Kerja Terkait';
        $fromLabel = $this->statusLabel($this->fromStatus);
        $toLabel = $this->statusLabel($this->toStatus);

        $isPicReporting = $this->actor->isPic();

        if ($isPicReporting) {
            // Scenario 1.2: PIC updates status -> Sent to Admin Kepatuhan
            return (new MailMessage)
                ->subject("[SMKI] Laporan Tindak Lanjut Temuan: {$kodeKlausul} ({$toLabel}) - {$unitName}")
                ->view('emails.finding-status-pic-to-admin', [
                    'recipientName' => $notifiable->name,
                    'kodeKlausul' => $kodeKlausul,
                    'judulKontrol' => $judulKontrol,
                    'unitName' => $unitName,
                    'fromStatus' => $this->fromStatus,
                    'fromLabel' => $fromLabel,
                    'toLabel' => $toLabel,
                    'toStatus' => $this->toStatus,
                    'actorName' => $this->actor->name,
                    'catatan' => $this->catatan,
                    'actionUrl' => url("/admin/kepatuhan/temuan?id={$this->finding->id}"),
                ]);
        }

        // Scenario 1.3: Admin updates/verifies status -> Sent to PIC
        $decisionLabel = $this->toStatus === 'closed' ? 'Disetujui & Ditutup' : 'Perlu Revisi';

        return (new MailMessage)
            ->subject("[SMKI] Hasil Verifikasi Temuan: {$kodeKlausul} ({$decisionLabel})")
            ->view('emails.finding-status-admin-to-pic', [
                'recipientName' => $notifiable->name,
                'kodeKlausul' => $kodeKlausul,
                'judulKontrol' => $judulKontrol,
                'fromStatus' => $this->fromStatus,
                'fromLabel' => $fromLabel,
                'toLabel' => $toLabel,
                'toStatus' => $this->toStatus,
                'actorName' => $this->actor->name,
                'catatan' => $this->catatan,
                'actionUrl' => url("/admin/pic/temuan?id={$this->finding->id}"),
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
