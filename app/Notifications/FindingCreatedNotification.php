<?php

namespace App\Notifications;

use App\Models\Finding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FindingCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Finding $finding,
        public User $actor,
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
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $kodeKlausul = $this->finding->control?->kode_klausul ?? 'SMKI';
        $judulKontrol = $this->finding->control?->judul ?? 'Kontrol Keamanan Informasi';
        $kategoriLabel = ucfirst($this->finding->kategori);
        $deadlineStr = $this->finding->deadline ? $this->finding->deadline->format('d M Y') : 'Sesuai SLA 14 Hari';

        return (new MailMessage)
            ->subject("[SMKI] Temuan Audit Baru: {$kodeKlausul} ({$kategoriLabel})")
            ->view('emails.finding-created', [
                'recipientName' => $notifiable->name,
                'kodeKlausul' => $kodeKlausul,
                'judulKontrol' => $judulKontrol,
                'kategori' => $this->finding->kategori,
                'kategoriLabel' => $kategoriLabel,
                'deadlineStr' => $deadlineStr,
                'actorName' => $this->actor->name,
                'catatan' => $this->catatan,
                'actionUrl' => url("/admin/kepatuhan/temuan?id={$this->finding->id}"),
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
        $judulKontrol = $this->finding->control?->judul ?? 'Kontrol Keamanan Informasi';

        return [
            'type' => 'finding_created',
            'title' => "Temuan Audit Baru: {$kodeKlausul}",
            'message' => "Temuan baru diterbitkan untuk kontrol {$kodeKlausul} - {$judulKontrol}.",
            'finding_id' => $this->finding->id,
            'kategori' => $this->finding->kategori,
            'deadline' => $this->finding->deadline?->format('Y-m-d'),
            'catatan' => $this->catatan,
            'url' => "/temuan?id={$this->finding->id}",
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'severity' => $this->finding->kategori === 'major' ? 'danger' : ($this->finding->kategori === 'minor' ? 'warning' : 'info'),
        ];
    }
}
