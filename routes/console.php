<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Otomatis generate checklist untuk seluruh satuan kerja pada tanggal 1 tiap bulan pukul 00:00
Schedule::command('smki:generate-monthly-checklist')->monthlyOn(1, '00:00');

Artisan::command('notify:test {userId?}', function ($userId = null) {
    $user = $userId ? User::find($userId) : User::first();
    if (! $user) {
        $this->error('User tidak ditemukan.');

        return;
    }

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['database'];
        }

        public function toDatabase(object $notifiable): array
        {
            return [
                'type' => 'finding_created',
                'title' => 'Temuan Audit Baru: A.5.1 (Uji Coba)',
                'message' => 'Admin Kepatuhan menerbitkan temuan audit baru yang memerlukan tindak lanjut segera.',
                'finding_id' => 1,
                'kategori' => 'major',
                'deadline' => now()->addDays(14)->format('Y-m-d'),
                'catatan' => 'Uji coba notifikasi real-time via Supabase!',
                'url' => '/admin/kepatuhan/dashboard',
                'actor_id' => 2,
                'actor_name' => 'Admin Kepatuhan',
                'severity' => 'danger',
            ];
        }
    };

    $user->notify($notification);
    $this->info("Notifikasi uji coba berhasil dikirimkan ke {$user->name} (ID: {$user->id})!");
})->purpose('Send a test real-time notification to a user');
