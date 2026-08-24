<?php

namespace Database\Seeders;

use App\Models\Control;
use App\Models\Risk;
use Illuminate\Database\Seeder;

class RiskSeeder extends Seeder
{
    public function run(): void
    {
        // Get controls
        $controlVuln = Control::where('kode_klausul', 'A.8.8')->first() ?? Control::first();
        $controlCrypto = Control::where('kode_klausul', 'A.8.24')->first() ?? Control::skip(1)->first() ?? Control::first();
        $controlAccess = Control::where('kode_klausul', 'A.5.15')->first() ?? Control::skip(2)->first() ?? Control::first();
        $controlPhysical = Control::where('kode_klausul', 'A.7.2')->first() ?? Control::skip(3)->first() ?? Control::first();
        $controlDlp = Control::where('kode_klausul', 'A.8.12')->first() ?? Control::skip(4)->first() ?? Control::first();
        $controlTraining = Control::where('kode_klausul', 'A.6.3')->first() ?? Control::skip(5)->first() ?? Control::first();
        $controlAsset = Control::where('kode_klausul', 'A.5.9')->first() ?? Control::skip(6)->first() ?? Control::first();
        $controlIncident = Control::where('kode_klausul', 'A.5.24')->first() ?? Control::skip(7)->first() ?? Control::first();

        $risksData = [
            [
                'control_id' => $controlVuln?->id,
                'level_risiko' => Risk::LEVEL_CRITICAL,
                'pemilik_risiko' => 'Kepala Biro Teknologi Informasi',
                'rencana_mitigasi' => 'Penerapan patch management otomatis, automated vulnerability scanning mingguan, serta penetration testing berkala setiap semester.',
                'status' => Risk::STATUS_OPEN,
            ],
            [
                'control_id' => $controlCrypto?->id,
                'level_risiko' => Risk::LEVEL_CRITICAL,
                'pemilik_risiko' => 'Koordinator Keamanan Siber & Jaringan',
                'rencana_mitigasi' => 'Peningkatan standar enkripsi ke TLS 1.3 pada seluruh endpoint API publik dan penerapan rotasi kunci enkripsi database secara otomatis.',
                'status' => Risk::STATUS_OPEN,
            ],
            [
                'control_id' => $controlAccess?->id,
                'level_risiko' => Risk::LEVEL_HIGH,
                'pemilik_risiko' => 'Sub-Bagian Infrastruktur TI',
                'rencana_mitigasi' => 'Implementasi Multi-Factor Authentication (MFA) wajib berbasis hardware token/authenticator app untuk seluruh akun privileged dan akses SSH server.',
                'status' => Risk::STATUS_OPEN,
            ],
            [
                'control_id' => $controlPhysical?->id,
                'level_risiko' => Risk::LEVEL_HIGH,
                'pemilik_risiko' => 'Bagian Umum & Pengamanan Gedung',
                'rencana_mitigasi' => 'Instalasi sistem akses pintu biometrik pada ruang server dan pemantauan CCTV 24/7 dengan penyimpanan rekaman minimal 90 hari.',
                'status' => Risk::STATUS_MITIGATED,
            ],
            [
                'control_id' => $controlDlp?->id,
                'level_risiko' => Risk::LEVEL_MEDIUM,
                'pemilik_risiko' => 'Sub-Bagian Aplikasi & Basis Data',
                'rencana_mitigasi' => 'Konfigurasi proteksi pencegahan kebocoran data (Data Loss Prevention - DLP) pada gateway email dan pembatasan transfer file ke media eksternal.',
                'status' => Risk::STATUS_OPEN,
            ],
            [
                'control_id' => $controlTraining?->id,
                'level_risiko' => Risk::LEVEL_MEDIUM,
                'pemilik_risiko' => 'Biro Sumber Daya Manusia',
                'rencana_mitigasi' => 'Penyelenggaraan pelatihan keamanan siber wajib tahunan dan simulasi serangan phishing berkala ke seluruh unit kerja setiap kuartal.',
                'status' => Risk::STATUS_MITIGATED,
            ],
            [
                'control_id' => $controlAsset?->id,
                'level_risiko' => Risk::LEVEL_LOW,
                'pemilik_risiko' => 'Sub-Bagian Tata Usaha & Perlengkapan',
                'rencana_mitigasi' => 'Risiko diterima (Risk Accepted) dengan pengendalian kompensasi berupa inventarisasi dan rekonsiliasi aset berkala setahun sekali.',
                'status' => Risk::STATUS_ACCEPTED,
            ],
            [
                'control_id' => $controlIncident?->id,
                'level_risiko' => Risk::LEVEL_LOW,
                'pemilik_risiko' => 'Tim Tanggap Insiden Siber (CSIRT)',
                'rencana_mitigasi' => 'Penyusunan Standard Operating Procedure (SOP) eskalasi insiden dan simulasi table-top exercise penanganan kebocoran data tahunan.',
                'status' => Risk::STATUS_MITIGATED,
            ],
        ];

        foreach ($risksData as $data) {
            if ($data['control_id']) {
                Risk::updateOrCreate(
                    [
                        'control_id' => $data['control_id'],
                        'pemilik_risiko' => $data['pemilik_risiko'],
                    ],
                    $data
                );
            }
        }

        $this->command->info('Berhasil menyemai data register risiko (Risks) untuk pengujian Fase 1.');
    }
}
