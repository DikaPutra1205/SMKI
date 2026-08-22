<?php

namespace Database\Seeders;

use App\Models\Control;
use App\Models\Finding;
use App\Models\User;
use App\Models\WorkUnit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FindingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@smki.test')->first()
            ?? User::whereHas('role', fn ($q) => $q->where('name', 'admin_kepatuhan'))->first()
            ?? User::first();

        $pic = User::where('email', 'pic@smki.test')->first()
            ?? User::whereHas('role', fn ($q) => $q->where('name', 'pic'))->first()
            ?? $admin;

        $biroTI = WorkUnit::where('nama', 'like', '%Teknologi Informasi%')->first();
        $biroSDM = WorkUnit::where('nama', 'like', '%SDM%')->first();
        $biroHukum = WorkUnit::where('nama', 'like', '%Hukum%')->first();
        $biroKeuangan = WorkUnit::where('nama', 'like', '%Keuangan%')->first();

        // Fallback unit if not found
        $defaultUnit = WorkUnit::first();
        $unitTI = $biroTI ?? $defaultUnit;
        $unitSDM = $biroSDM ?? $defaultUnit;
        $unitHukum = $biroHukum ?? $defaultUnit;
        $unitKeuangan = $biroKeuangan ?? $defaultUnit;

        // Get controls
        $controlVuln = Control::where('kode_klausul', 'A.8.8')->first() ?? Control::first();
        $controlAccess = Control::where('kode_klausul', 'A.5.15')->first() ?? Control::skip(1)->first() ?? Control::first();
        $controlPolicy = Control::where('kode_klausul', 'A.5.1')->first() ?? Control::skip(2)->first() ?? Control::first();
        $controlPhysical = Control::where('kode_klausul', 'A.7.2')->first() ?? Control::skip(3)->first() ?? Control::first();
        $controlTraining = Control::where('kode_klausul', 'A.6.3')->first() ?? Control::skip(4)->first() ?? Control::first();
        $controlBackup = Control::where('kode_klausul', 'A.8.13')->first() ?? Control::skip(5)->first() ?? Control::first();
        $controlDoc = Control::where('kode_klausul', '7.5')->first() ?? Control::skip(6)->first() ?? Control::first();

        $findingsData = [
            [
                'control_id' => $controlVuln?->id,
                'unit_id' => $unitTI?->id,
                'pic_id' => $pic?->id,
                'admin_id' => $admin?->id,
                'kategori' => Finding::KATEGORI_MAJOR,
                'status' => Finding::STATUS_OPEN,
                // Overdue 3 days ago to trigger red SLA alert
                'deadline' => Carbon::now()->subDays(3)->format('Y-m-d'),
                'catatan_admin' => 'Ditemukan 4 kerentanan berisiko tinggi pada server aplikasi utama yang belum dilakukan patching selama lebih dari 60 hari. Harap segera terapkan patch keamanan dan laporkan hasil vulnerability scan ulang.',
                'tanggal_verifikasi' => null,
            ],
            [
                'control_id' => $controlAccess?->id,
                'unit_id' => $unitTI?->id,
                'pic_id' => $pic?->id,
                'admin_id' => $admin?->id,
                'kategori' => Finding::KATEGORI_MAJOR,
                'status' => Finding::STATUS_IN_PROGRESS,
                // Urgent deadline in 2 days to trigger yellow near-deadline SLA alert
                'deadline' => Carbon::now()->addDays(2)->format('Y-m-d'),
                'catatan_admin' => 'Autentikasi Multi-Faktor (MFA) belum diwajibkan bagi seluruh pengguna dengan hak akses administratif database dan server produksi. Implementasi MFA sedang dalam proses konfigurasi SSO.',
                'tanggal_verifikasi' => null,
            ],
            [
                'control_id' => $controlTraining?->id,
                'unit_id' => $unitSDM?->id,
                'pic_id' => $pic?->id,
                'admin_id' => $admin?->id,
                'kategori' => Finding::KATEGORI_MINOR,
                'status' => Finding::STATUS_OPEN,
                // Normal deadline in 14 days
                'deadline' => Carbon::now()->addDays(14)->format('Y-m-d'),
                'catatan_admin' => 'Tingkat kepesertaan sosialisasi dan pelatihan kesadaran keamanan informasi (Security Awareness) untuk pegawai baru pada triwulan ini masih di bawah target 80%.',
                'tanggal_verifikasi' => null,
            ],
            [
                'control_id' => $controlPhysical?->id,
                'unit_id' => $unitSDM?->id,
                'pic_id' => $pic?->id,
                'admin_id' => $admin?->id,
                'kategori' => Finding::KATEGORI_MINOR,
                'status' => Finding::STATUS_IN_PROGRESS,
                // Safe deadline in 21 days
                'deadline' => Carbon::now()->addDays(21)->format('Y-m-d'),
                'catatan_admin' => 'Buku log pengunjung ruang server fisik belum ditandatangani oleh staf pengawas secara lengkap pada shift malam.',
                'tanggal_verifikasi' => null,
            ],
            [
                'control_id' => $controlPolicy?->id,
                'unit_id' => $unitHukum?->id,
                'pic_id' => $pic?->id,
                'admin_id' => $admin?->id,
                'kategori' => Finding::KATEGORI_OBSERVASI,
                'status' => Finding::STATUS_CLOSED,
                // Closed finding with verification date
                'deadline' => Carbon::now()->subDays(15)->format('Y-m-d'),
                'catatan_admin' => 'Dokumen kebijakan keamanan informasi telah ditinjau ulang oleh pimpinan dan ditandatangani secara resmi. Tindakan korektif telah diverifikasi selesai dan sesuai standar.',
                'tanggal_verifikasi' => Carbon::now()->subDays(2),
            ],
            [
                'control_id' => $controlBackup?->id,
                'unit_id' => $unitKeuangan?->id,
                'pic_id' => $pic?->id,
                'admin_id' => $admin?->id,
                'kategori' => Finding::KATEGORI_OBSERVASI,
                'status' => Finding::STATUS_OPEN,
                // Deadline in 30 days
                'deadline' => Carbon::now()->addDays(30)->format('Y-m-d'),
                'catatan_admin' => 'Perlu dilakukan uji pemulihan berkala (restoration drill) untuk data backup sistem keuangan setiap semester untuk memastikan integritas data cadangan.',
                'tanggal_verifikasi' => null,
            ],
        ];

        foreach ($findingsData as $data) {
            if ($data['control_id'] && $data['unit_id']) {
                Finding::updateOrCreate(
                    [
                        'control_id' => $data['control_id'],
                        'unit_id' => $data['unit_id'],
                        'kategori' => $data['kategori'],
                    ],
                    $data
                );
            }
        }

        $this->command->info('Berhasil menyemai data temuan audit (Findings) untuk pengujian Fase 1.');
    }
}
