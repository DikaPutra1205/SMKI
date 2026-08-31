<?php

namespace App\Console\Commands;

use App\Models\ChecklistSession;
use App\Models\Finding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smki:test-email
                            {--to= : Alamat email penerima uji coba}
                            {--template=1-1 : Kode template (1-1, 1-2a, 1-2b, 1-2c, 1-3a, 1-3b, 1-3c, 2-1, 4-1)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim email uji coba template notifikasi SMKI ke alamat tertentu tanpa mengubah database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recipient = $this->option('to') ?: config('mail.always_to') ?: config('mail.from.address');

        if (! $recipient) {
            $this->error('Tentukan alamat email penerima menggunakan opsi --to=contoh@email.com');

            return self::FAILURE;
        }

        $template = strtolower($this->option('template') ?: '1-1');

        $firstFinding = Finding::with('control')->first();
        $findingId = $firstFinding?->id ?? 8;
        $klausul = $firstFinding?->control?->kode_klausul ?? 'A.5.1';
        $judul = $firstFinding?->control?->judul ?? 'Kebijakan Keamanan Informasi';
        $findingActionUrl = url("/temuan?id={$findingId}");

        $firstSession = ChecklistSession::first();
        $sessionId = $firstSession?->id ?? 1;
        $checklistActionUrl = url("/admin/pic/checklist/{$sessionId}");

        $this->info("Mengirim email uji coba template [{$template}] ke [{$recipient}] (Target Finding ID: {$findingId})...");

        try {
            match ($template) {
                '1-1' => Mail::send('emails.finding-created', [
                    'recipientName' => 'Dika Putra (PIC Unit TI)',
                    'kodeKlausul' => $klausul,
                    'judulKontrol' => $judul,
                    'kategori' => 'major',
                    'kategoriLabel' => 'Mayor',
                    'deadlineStr' => now()->addDays(14)->format('d M Y'),
                    'actorName' => 'Admin Kepatuhan',
                    'catatan' => 'Dokumen kebijakan keamanan informasi belum ditandatangani oleh pimpinan manajemen puncak sesuai persyaratan klausul 5.1 ISO 27001.',
                    'actionUrl' => $findingActionUrl,
                ], function ($message) use ($recipient, $klausul) {
                    $message->to($recipient)->subject("[SMKI] Temuan Audit Baru (Mayor): {$klausul}");
                }),

                '1-2a', '1-2-a', '1-2' => Mail::send('emails.finding-status-pic-to-admin', [
                    'recipientName' => 'Admin Kepatuhan',
                    'kodeKlausul' => $klausul,
                    'judulKontrol' => $judul,
                    'unitName' => 'Pusat Teknologi Informasi (TI)',
                    'fromStatus' => 'in_progress',
                    'fromLabel' => 'Dalam Penanganan (In Progress)',
                    'toLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                    'toStatus' => 'resolved',
                    'actorName' => 'Dika Putra (PIC TI)',
                    'catatan' => 'Patch keamanan kernel Ubuntu telah diaplikasikan ke seluruh cluster server production dan laporan vulnerability scanning terbaru sudah diunggah.',
                    'actionUrl' => $findingActionUrl,
                ], function ($message) use ($recipient, $klausul) {
                    $message->to($recipient)->subject("[SMKI] Laporan Tindak Lanjut Temuan: {$klausul} (Selesai Ditindaklanjuti) - Pusat Teknologi Informasi (TI)");
                }),

                '1-2b', '1-2-b' => Mail::send('emails.finding-status-pic-to-admin', [
                    'recipientName' => 'Admin Kepatuhan',
                    'kodeKlausul' => $klausul,
                    'judulKontrol' => $judul,
                    'unitName' => 'Pusat Teknologi Informasi (TI)',
                    'fromStatus' => 'resolved',
                    'fromLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                    'toLabel' => 'Ditutup oleh PIC (Closed)',
                    'toStatus' => 'closed',
                    'actorName' => 'Dika Putra (PIC TI)',
                    'catatan' => 'Seluruh rekomendasi audit telah diselesaikan dan sistem telah beroperasi normal tanpa celah keamanan.',
                    'actionUrl' => $findingActionUrl,
                ], function ($message) use ($recipient, $klausul) {
                    $message->to($recipient)->subject("[SMKI] Laporan Tindak Lanjut Temuan: {$klausul} (Ditutup oleh PIC) - Pusat Teknologi Informasi (TI)");
                }),

                '1-2c', '1-2-c' => Mail::send('emails.finding-status-pic-to-admin', [
                    'recipientName' => 'Admin Kepatuhan',
                    'kodeKlausul' => $klausul,
                    'judulKontrol' => $judul,
                    'unitName' => 'Pusat Teknologi Informasi (TI)',
                    'fromStatus' => 'resolved',
                    'fromLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                    'toLabel' => 'Dalam Penanganan (In Progress)',
                    'toStatus' => 'in_progress',
                    'actorName' => 'Dika Putra (PIC TI)',
                    'catatan' => 'Ditemukan anomali konfigurasi tambahan saat testing ulang, sehingga status kami kembalikan ke Dalam Penanganan untuk perbaikan komprehensif.',
                    'actionUrl' => $findingActionUrl,
                ], function ($message) use ($recipient, $klausul) {
                    $message->to($recipient)->subject("[SMKI] Laporan Tindak Lanjut Temuan: {$klausul} (Dalam Penanganan) - Pusat Teknologi Informasi (TI)");
                }),

                '1-3a', '1-3-a' => Mail::send('emails.finding-status-admin-to-pic', [
                    'recipientName' => 'Dika Putra (PIC Unit TI)',
                    'kodeKlausul' => $klausul,
                    'judulKontrol' => $judul,
                    'fromStatus' => 'resolved',
                    'fromLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                    'toLabel' => 'Dalam Penanganan / Perlu Revisi (In Progress)',
                    'toStatus' => 'in_progress',
                    'actorName' => 'Admin Kepatuhan',
                    'catatan' => 'Laporan scanning kerentanan belum mencakup port database internal. Mohon lakukan scanning ulang dan lampirkan buktinya.',
                    'actionUrl' => $findingActionUrl,
                ], function ($message) use ($recipient, $klausul) {
                    $message->to($recipient)->subject("[SMKI] Hasil Verifikasi Temuan: {$klausul} (Perlu Revisi)");
                }),

                '1-3b', '1-3-b', '1-3' => Mail::send('emails.finding-status-admin-to-pic', [
                    'recipientName' => 'Dika Putra (PIC Unit TI)',
                    'kodeKlausul' => $klausul,
                    'judulKontrol' => $judul,
                    'fromStatus' => 'resolved',
                    'fromLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                    'toLabel' => 'Ditutup & Terverifikasi (Closed)',
                    'toStatus' => 'closed',
                    'actorName' => 'Admin Kepatuhan',
                    'catatan' => 'Bukti laporan scanning kerentanan dan log patching telah diperiksa dan dinyatakan memenuhi standar kepatuhan ISO 27001. Temuan resmi ditutup.',
                    'actionUrl' => $findingActionUrl,
                ], function ($message) use ($recipient, $klausul) {
                    $message->to($recipient)->subject("[SMKI] Hasil Verifikasi Temuan: {$klausul} (Disetujui & Ditutup)");
                }),

                '1-3c', '1-3-c' => Mail::send('emails.finding-status-admin-to-pic', [
                    'recipientName' => 'Dika Putra (PIC Unit TI)',
                    'kodeKlausul' => $klausul,
                    'judulKontrol' => $judul,
                    'fromStatus' => 'open',
                    'fromLabel' => 'Terbuka (Open)',
                    'toLabel' => 'Dalam Penanganan (In Progress)',
                    'toStatus' => 'in_progress',
                    'actorName' => 'Admin Kepatuhan',
                    'catatan' => 'Admin telah memulai asistensi teknis dan mengarahkan rencana penanganan mitigasi untuk unit Anda.',
                    'actionUrl' => $findingActionUrl,
                ], function ($message) use ($recipient, $klausul) {
                    $message->to($recipient)->subject("[SMKI] Hasil Verifikasi Temuan: {$klausul} (Dalam Penanganan)");
                }),

                '2-1', '2-1-checklist' => Mail::send('emails.checklist-rejected', [
                    'recipientName' => 'Dika Putra (PIC Unit TI)',
                    'kodeKlausul' => 'A.8.1',
                    'judulKontrol' => 'Inventarisasi Aset Pengguna & Informasi',
                    'catatanAdmin' => 'Berkas daftar inventaris aset yang diunggah belum mencantumkan klasifikasi informasi (Confidential/Internal/Public) dan penanggung jawab aset.',
                    'actorName' => 'Admin Kepatuhan',
                    'actionUrl' => $checklistActionUrl,
                ], function ($message) use ($recipient) {
                    $message->to($recipient)->subject('[SMKI] Penilaian Kontrol Tidak Patuh (Perlu Perbaikan): A.8.1');
                }),

                '4-1', '4-1-auth' => Mail::send('emails.auth-reset-password', [
                    'recipientName' => 'Dika Putra',
                    'resetUrl' => url('/reset-password?token=sample-secure-token-12345&email='.urlencode($recipient)),
                    'count' => 60,
                ], function ($message) use ($recipient) {
                    $message->to($recipient)->subject('[SMKI] Permintaan Atur Ulang Kata Sandi Akun');
                }),

                default => throw new \InvalidArgumentException("Template [{$template}] tidak dikenali."),
            };

            $this->info("✅ Email uji coba [{$template}] berhasil dikirim ke [{$recipient}].");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Gagal mengirim email: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
