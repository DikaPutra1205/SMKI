<?php

namespace App\Notifications;

use App\Models\ChecklistEntry;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChecklistEntryRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ChecklistEntry $entry,
        public User $actor,
        public ?string $catatanAdmin = null
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
        $kodeKlausul = $this->entry->control?->kode_klausul ?? 'SMKI';
        $judulKontrol = $this->entry->control?->judul ?? 'Kontrol Keamanan Informasi';

        return (new MailMessage)
            ->subject("[SMKI] Penilaian Kontrol Tidak Patuh (Perlu Perbaikan): {$kodeKlausul}")
            ->view('emails.checklist-rejected', [
                'recipientName' => $notifiable->name,
                'kodeKlausul' => $kodeKlausul,
                'judulKontrol' => $judulKontrol,
                'catatanAdmin' => $this->catatanAdmin,
                'actorName' => $this->actor->name,
                'actionUrl' => url("/pic/checklist/{$this->entry->session_id}"),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $kodeKlausul = $this->entry->control?->kode_klausul ?? 'SMKI';
        $judulKontrol = $this->entry->control?->judul ?? 'Kontrol Keamanan Informasi';

        return [
            'type' => 'checklist_rejected',
            'title' => "Penilaian Kontrol Tidak Patuh: {$kodeKlausul}",
            'message' => "Entri checklist kontrol {$kodeKlausul} - {$judulKontrol} dinyatakan Tidak Patuh.",
            'entry_id' => $this->entry->id,
            'session_id' => $this->entry->session_id,
            'control_id' => $this->entry->control_id,
            'catatan_admin' => $this->catatanAdmin,
            'url' => "/pic/checklist/{$this->entry->session_id}",
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'severity' => 'danger',
        ];
    }
}
