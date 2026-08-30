<?php

namespace Database\Seeders;

use App\Models\Control;
use App\Models\Finding;
use App\Models\FindingStatusHistory;
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
                'histories' => [
                    [
                        'user_id' => $admin?->id,
                        'from_status' => null,
                        'to_status' => Finding::STATUS_OPEN,
                        'catatan' => 'Temuan diterbitkan oleh Admin Kepatuhan setelah audit kerentanan infrastruktur server.',
                        'created_at' => Carbon::now()->subDays(10),
                    ],
                ],
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
                'histories' => [
                    [
                        'user_id' => $admin?->id,
                        'from_status' => null,
                        'to_status' => Finding::STATUS_OPEN,
                        'catatan' => 'Ditemukan belum adanya penegakan MFA untuk akun privileged.',
                        'created_at' => Carbon::now()->subDays(5),
                    ],
                    [
                        'user_id' => $pic?->id,
                        'from_status' => Finding::STATUS_OPEN,
                        'to_status' => Finding::STATUS_IN_PROGRESS,
                        'catatan' => 'PIC Biro TI memulai integrasi modul TOTP/MFA pada SSO internal.',
                        'created_at' => Carbon::now()->subDays(3),
                    ],
                ],
            ],
            [
                'control_id' => $controlTraining?->id,
                'unit_id' => $unitSDM?->id,
                'pic_id' => $pic?->id,
                'admin_id' => $admin?->id,
                'kategori' => Finding::KATEGORI_MINOR,
                'status' => Finding::STATUS_RESOLVED,
                // Resolved finding awaiting admin verification
                'deadline' => Carbon::now()->addDays(7)->format('Y-m-d'),
                'catatan_admin' => 'Pelatihan kesadaran keamanan informasi telah diselenggarakan secara daring untuk seluruh pegawai baru, tingkat partisipasi mencapai 94%. Menunggu verifikasi Admin.',
                'tanggal_verifikasi' => null,
                'histories' => [
                    [
                        'user_id' => $admin?->id,
                        'from_status' => null,
                        'to_status' => Finding::STATUS_OPEN,
                        'catatan' => 'Tingkat kepesertaan pelatihan keamanan masih di bawah standar 80%.',
                        'created_at' => Carbon::now()->subDays(12),
                    ],
                    [
                        'user_id' => $pic?->id,
                        'from_status' => Finding::STATUS_OPEN,
                        'to_status' => Finding::STATUS_IN_PROGRESS,
                        'catatan' => 'Menjadwalkan ulang webinar security awareness dan rekap presensi LMS.',
                        'created_at' => Carbon::now()->subDays(8),
                    ],
                    [
                        'user_id' => $pic?->id,
                        'from_status' => Finding::STATUS_IN_PROGRESS,
                        'to_status' => Finding::STATUS_RESOLVED,
                        'catatan' => 'Pelatihan telah selesai dilaksanakan dengan tingkat kepesertaan 94%. Bukti sertifikat dan absensi telah diunggah.',
                        'created_at' => Carbon::now()->subDays(1),
                    ],
                ],
            ],
            [
                'control_id' => $controlPhysical?->id,
                'unit_id' => $unitSDM?->id,
                'pic_id' => $pic?->id,
                'admin_id' => $admin?->id,
                'kategori' => Finding::KATEGORI_MINOR,
                'status' => Finding::STATUS_IN_PROGRESS,
                'deadline' => Carbon::now()->addDays(21)->format('Y-m-d'),
                'catatan_admin' => 'Buku log pengunjung ruang server fisik sedang distandarisasi ke sistem digital e-logbook.',
                'tanggal_verifikasi' => null,
                'histories' => [
                    [
                        'user_id' => $admin?->id,
                        'from_status' => null,
                        'to_status' => Finding::STATUS_OPEN,
                        'catatan' => 'Buku log manual tidak terisi lengkap pada shift malam.',
                        'created_at' => Carbon::now()->subDays(4),
                    ],
                    [
                        'user_id' => $pic?->id,
                        'from_status' => Finding::STATUS_OPEN,
                        'to_status' => Finding::STATUS_IN_PROGRESS,
                        'catatan' => 'Menerapkan SOP baru dan menyiapkan tablet logbook digital di depan pintu server.',
                        'created_at' => Carbon::now()->subDays(2),
                    ],
                ],
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
                'histories' => [
                    [
                        'user_id' => $admin?->id,
                        'from_status' => null,
                        'to_status' => Finding::STATUS_OPEN,
                        'catatan' => 'Revisi kebijakan SMKI tahunan belum ditandatangani.',
                        'created_at' => Carbon::now()->subDays(20),
                    ],
                    [
                        'user_id' => $pic?->id,
                        'from_status' => Finding::STATUS_OPEN,
                        'to_status' => Finding::STATUS_IN_PROGRESS,
                        'catatan' => 'Draf revisi diajukan ke bagian Legal/Hukum.',
                        'created_at' => Carbon::now()->subDays(14),
                    ],
                    [
                        'user_id' => $pic?->id,
                        'from_status' => Finding::STATUS_IN_PROGRESS,
                        'to_status' => Finding::STATUS_RESOLVED,
                        'catatan' => 'SK Direksi tentang Kebijakan SMKI telah terbit dan didistribusikan.',
                        'created_at' => Carbon::now()->subDays(4),
                    ],
                    [
                        'user_id' => $admin?->id,
                        'from_status' => Finding::STATUS_RESOLVED,
                        'to_status' => Finding::STATUS_CLOSED,
                        'catatan' => 'Dokumen SK telah diverifikasi sah. Temuan ditutup resmi.',
                        'created_at' => Carbon::now()->subDays(2),
                    ],
                ],
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
                'histories' => [
                    [
                        'user_id' => $admin?->id,
                        'from_status' => null,
                        'to_status' => Finding::STATUS_OPEN,
                        'catatan' => 'Rekomendasi observasi uji coba restore backup semesteran.',
                        'created_at' => Carbon::now()->subDays(1),
                    ],
                ],
            ],
        ];

        foreach ($findingsData as $data) {
            if ($data['control_id'] && $data['unit_id']) {
                $histories = $data['histories'] ?? [];
                unset($data['histories']);

                $finding = Finding::updateOrCreate(
                    [
                        'control_id' => $data['control_id'],
                        'unit_id' => $data['unit_id'],
                        'kategori' => $data['kategori'],
                    ],
                    $data
                );

                // Seed status histories if not existing
                if ($finding->histories()->count() === 0 && ! empty($histories)) {
                    foreach ($histories as $hist) {
                        FindingStatusHistory::create([
                            'finding_id' => $finding->id,
                            'user_id' => $hist['user_id'] ?? $admin?->id,
                            'from_status' => $hist['from_status'],
                            'to_status' => $hist['to_status'],
                            'catatan' => $hist['catatan'],
                            'created_at' => $hist['created_at'] ?? now(),
                            'updated_at' => $hist['created_at'] ?? now(),
                        ]);
                    }
                }
            }
        }

        $this->command->info('Berhasil menyemai data temuan audit (Findings) dengan 4 status siklus hidup dan riwayat lengkap.');
    }
}
