<?php

namespace Database\Seeders;

use App\Models\Control;
use App\Models\Framework;
use Illuminate\Database\Seeder;

class ControlSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIso27001();
        $this->seedIso27701();
    }

    protected function seedIso27001(): void
    {
        $iso27001 = Framework::where('nama', 'ISO/IEC 27001')->first();

        if (! $iso27001) {
            $this->command->warn('Framework ISO/IEC 27001 tidak ditemukan.');

            return;
        }

        $controls = [
            // =========================================================
            // ANNEX A — A.5 Kontrol Organisasional (37 kontrol)
            // =========================================================
            ['kode_klausul' => 'A.5.1', 'judul' => 'Kebijakan untuk keamanan informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.2', 'judul' => 'Peran dan tanggung jawab keamanan informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.3', 'judul' => 'Pemisahan tugas (Segregation of duties)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.4', 'judul' => 'Tanggung jawab manajemen', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.5', 'judul' => 'Hubungan dengan otoritas berwenang', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.6', 'judul' => 'Hubungan dengan kelompok kepentingan khusus', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.7', 'judul' => 'Intelijen ancaman (Threat intelligence)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.8', 'judul' => 'Keamanan informasi dalam manajemen proyek', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.9', 'judul' => 'Inventarisasi aset informasi dan aset terkait lainnya', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.10', 'judul' => 'Penggunaan yang dapat diterima atas aset informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.11', 'judul' => 'Pengembalian aset', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.12', 'judul' => 'Klasifikasi informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.13', 'judul' => 'Pemberian label informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.14', 'judul' => 'Pengalihan informasi (Information transfer)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.15', 'judul' => 'Kontrol akses (Access control)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.16', 'judul' => 'Manajemen hak akses identitas', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.17', 'judul' => 'Informasi otentikasi (Authentication information)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.18', 'judul' => 'Hak akses istimewa (Privileged access rights)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.19', 'judul' => 'Pembatasan akses informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.20', 'judul' => 'Akses ke kode sumber (Source code access)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.21', 'judul' => 'Pengutamaan keamanan dalam hubungan pemasok', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.22', 'judul' => 'Penyelesaian keamanan informasi dalam perjanjian pemasok', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.23', 'judul' => 'Penyelesaian keamanan informasi dalam rantai pasok ICT', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.24', 'judul' => 'Pemantauan, peninjauan, dan manajemen layanan pemasok', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.25', 'judul' => 'Manajemen keamanan informasi dalam layanan awan (Cloud)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.26', 'judul' => 'Perencanaan dan persiapan manajemen insiden', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.27', 'judul' => 'Evaluasi dan penilaian kejadian keamanan informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.28', 'judul' => 'Respon terhadap insiden keamanan informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.29', 'judul' => 'Pembelajaran dari insiden keamanan informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.30', 'judul' => 'Kesiapan keamanan informasi untuk keberlangsungan bisnis', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.31', 'judul' => 'Persyaratan hukum, regulasi, dan kontrak', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.32', 'judul' => 'Hak kekayaan intelektual (Intellectual property rights)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.33', 'judul' => 'Perlindungan rekaman/arsip (Protection of records)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.34', 'judul' => 'Privasi dan perlindungan informasi identitas pribadi (PII)', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.35', 'judul' => 'Tinjauan independen atas keamanan informasi', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.36', 'judul' => 'Kepatuhan terhadap kebijakan dan standar keamanan', 'kategori' => 'organisasional', 'domain_peran' => null],
            ['kode_klausul' => 'A.5.37', 'judul' => 'Prosedur pengoperasian terdokumentasi', 'kategori' => 'organisasional', 'domain_peran' => null],

            // =========================================================
            // ANNEX A — A.6 Kontrol Orang (8 kontrol)
            // =========================================================
            ['kode_klausul' => 'A.6.1', 'judul' => 'Penyaringan latar belakang pegawai (Screening)', 'kategori' => 'orang', 'domain_peran' => null],
            ['kode_klausul' => 'A.6.2', 'judul' => 'Syarat dan ketentuan penyediaan tenaga kerja', 'kategori' => 'orang', 'domain_peran' => null],
            ['kode_klausul' => 'A.6.3', 'judul' => 'Kesadaran, pendidikan, dan pelatihan keamanan informasi', 'kategori' => 'orang', 'domain_peran' => null],
            ['kode_klausul' => 'A.6.4', 'judul' => 'Proses disiplin (Disciplinary process)', 'kategori' => 'orang', 'domain_peran' => null],
            ['kode_klausul' => 'A.6.5', 'judul' => 'Tanggung jawab setelah pemutusan/perubahan hubungan kerja', 'kategori' => 'orang', 'domain_peran' => null],
            ['kode_klausul' => 'A.6.6', 'judul' => 'Kerahasiaan atau perjanjian kerahasiaan (NDA)', 'kategori' => 'orang', 'domain_peran' => null],
            ['kode_klausul' => 'A.6.7', 'judul' => 'Bekerja jarak jauh (Remote working)', 'kategori' => 'orang', 'domain_peran' => null],
            ['kode_klausul' => 'A.6.8', 'judul' => 'Pelaporan kejadian keamanan informasi', 'kategori' => 'orang', 'domain_peran' => null],

            // =========================================================
            // ANNEX A — A.7 Kontrol Fisik (14 kontrol)
            // =========================================================
            ['kode_klausul' => 'A.7.1', 'judul' => 'Perimeter keamanan fisik', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.2', 'judul' => 'Pintu masuk/akses fisik', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.3', 'judul' => 'Pengamanan kantor, ruangan, dan fasilitas', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.4', 'judul' => 'Pemantauan keamanan fisik', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.5', 'judul' => 'Perlindungan terhadap ancaman fisik dan lingkungan', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.6', 'judul' => 'Bekerja di area aman', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.7', 'judul' => 'Meja bersih dan layar bersih (Clear desk & clear screen)', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.8', 'judul' => 'Penempatan dan perlindungan peralatan', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.9', 'judul' => 'Keamanan aset di luar area organisasi', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.10', 'judul' => 'Media penyimpanan (Storage media)', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.11', 'judul' => 'Fasilitas penunjang (Utilities)', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.12', 'judul' => 'Keamanan kabel data dan daya', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.13', 'judul' => 'Pemeliharaan peralatan', 'kategori' => 'fisik', 'domain_peran' => null],
            ['kode_klausul' => 'A.7.14', 'judul' => 'Pemusnahan atau pembuangan aset secara aman', 'kategori' => 'fisik', 'domain_peran' => null],

            // =========================================================
            // ANNEX A — A.8 Kontrol Teknologi (34 kontrol)
            // =========================================================
            ['kode_klausul' => 'A.8.1', 'judul' => 'Perangkat akhir pengguna (User endpoint devices)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.2', 'judul' => 'Hak akses hak istimewa pengguna', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.3', 'judul' => 'Pembatasan akses informasi', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.4', 'judul' => 'Akses ke kode sumber', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.5', 'judul' => 'Otentikasi aman (Secure authentication)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.6', 'judul' => 'Manajemen kapasitas', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.7', 'judul' => 'Perlindungan terhadap perangkat perusak (Malware)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.8', 'judul' => 'Manajemen kerentanan teknis (Vulnerability management)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.9', 'judul' => 'Manajemen konfigurasi', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.10', 'judul' => 'Penyapuan/Penghapusan informasi (Information deletion)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.11', 'judul' => 'Pengarsipan/Masking data (Data masking)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.12', 'judul' => 'Pencegahan kebocoran data (Data leakage prevention)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.13', 'judul' => 'Pencadangan informasi (Backup)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.14', 'judul' => 'Kelebihan kapasitas/Redundansi fasilitas pemrosesan', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.15', 'judul' => 'Pencatatan log (Logging)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.16', 'judul' => 'Pemantauan aktivitas (Monitoring activities)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.17', 'judul' => 'Sinkronisasi jam (Clock synchronization)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.18', 'judul' => 'Penggunaan program utilitas berhak akses tinggi', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.19', 'judul' => 'Pemasangan perangkat lunak pada sistem operasional', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.20', 'judul' => 'Keamanan jaringan (Network security)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.21', 'judul' => 'Keamanan layanan jaringan', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.22', 'judul' => 'Pemisahan jaringan (Network segregation)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.23', 'judul' => 'Penyaringan web (Web filtering)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.24', 'judul' => 'Penggunaan kriptografi', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.25', 'judul' => 'Siklus hidup pengembangan sistem yang aman (SDLC)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.26', 'judul' => 'Persyaratan keamanan aplikasi', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.27', 'judul' => 'Prinsip rekayasa sistem yang aman', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.28', 'judul' => 'Pengkodean aman (Secure coding)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.29', 'judul' => 'Pengujian keamanan dalam pengembangan dan penerimaan', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.30', 'judul' => 'Pengujian keamanan produk/aplikasi luar (Outsourced)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.31', 'judul' => 'Pemisahan lingkungan pengembangan, pengujian, dan produksi', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.32', 'judul' => 'Manajemen perubahan (Change management)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.33', 'judul' => 'Informasi pengujian (Test information)', 'kategori' => 'teknologi', 'domain_peran' => null],
            ['kode_klausul' => 'A.8.34', 'judul' => 'Perlindungan sistem informasi selama pengujian audit', 'kategori' => 'teknologi', 'domain_peran' => null],
        ];

        $now = now();
        $rows = array_map(fn ($c) => array_merge($c, [
            'framework_id' => $iso27001->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $controls);

        // Using updateOrCreate() instead of upsert() because the unique index on
        // (framework_id, kode_klausul) is now a partial index (WHERE deleted_at IS NULL).
        // SQLite does not support ON CONFLICT clauses that target partial indexes,
        // causing upsert() to throw a QueryException in test environments.
        foreach ($rows as $row) {
            Control::updateOrCreate(
                ['framework_id' => $row['framework_id'], 'kode_klausul' => $row['kode_klausul']],
                ['judul' => $row['judul'], 'kategori' => $row['kategori'], 'domain_peran' => $row['domain_peran'] ?? null, 'deskripsi' => $row['deskripsi'] ?? null, 'updated_at' => $row['updated_at']]
            );
        }

        $this->command->info('Berhasil menyemai '.count($rows).' kontrol ISO/IEC 27001:2022.');
    }

    protected function seedIso27701(): void
    {
        $iso27701 = Framework::where('nama', 'ISO/IEC 27701')->first();

        if (! $iso27701) {
            $this->command->warn('Framework ISO/IEC 27701 tidak ditemukan.');

            return;
        }

        $controls = [
            // =========================================================
            // 7.x PII Controller (21)
            // =========================================================
            ['kode_klausul' => '7.2.1', 'judul' => 'Tujuan dan pemberitahuan pengumpulan data pribadi (PII)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.2.2', 'judul' => 'Dasar hukum dan persetujuan (Consent)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.2.3', 'judul' => 'Penilaian Dampak Perlindungan Data (DPIA / AMDAL Data)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.2.4', 'judul' => 'Batasan pengumpulan PII secara minimal', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.2.5', 'judul' => 'Batasan pemrosesan, penyimpanan, dan penghapusan PII', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.2.6', 'judul' => 'Kewajiban akurasi dan pembaruan data PII', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.2.7', 'judul' => 'Tujuan identifikasi dan retensi PII', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.2.8', 'judul' => 'Catatan/Rekaman Aktivitas Pemrosesan Data (RoPA)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.2.9', 'judul' => 'Privasi secara bawaan dan terdesain (Privacy by Design)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.3.1', 'judul' => 'Pemberitahuan kewajiban kepada Subjek Data (PII Principal)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.3.2', 'judul' => 'Mekanisme pemenuhan Hak Subjek Data (DSR Portal)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.3.3', 'judul' => 'Penanganan permintaan perbaikan dan penghapusan data', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.3.4', 'judul' => 'Pemberitahuan kepada pihak ketiga atas perubahan data PII', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.3.5', 'judul' => 'Penyediaan salinan PII yang diproses (Portabilitas Data)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.3.6', 'judul' => 'Penanganan keberatan pemrosesan data PII', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.4.1', 'judul' => 'Pengamanan transmisi dan penyimpanan PII', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.4.2', 'judul' => 'Keamanan dalam pengungkapan PII kepada PII Processor', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.4.3', 'judul' => 'Pengikatan kontrak perjanjian pemrosesan data (DPA)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.4.4', 'judul' => 'Transfer PII lintas batas negara (Cross-border data transfer)', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.4.5', 'judul' => 'Pemberitahuan insiden kebocoran PII ke Otoritas Pengawas', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],
            ['kode_klausul' => '7.4.6', 'judul' => 'Pemberitahuan insiden kebocoran PII ke Subjek Data', 'kategori' => 'organisasional', 'domain_peran' => 'controller'],

            // =========================================================
            // 8.x PII Processor (9)
            // =========================================================
            ['kode_klausul' => '8.2.1', 'judul' => 'Mendukung kewajiban PII Controller dalam pemrosesan data', 'kategori' => 'teknologi', 'domain_peran' => 'processor'],
            ['kode_klausul' => '8.2.2', 'judul' => 'Pemrosesan PII hanya sesuai instruksi tertulis Controller', 'kategori' => 'teknologi', 'domain_peran' => 'processor'],
            ['kode_klausul' => '8.2.3', 'judul' => 'Persetujuan tertulis untuk pengangkatan Sub-processor', 'kategori' => 'teknologi', 'domain_peran' => 'processor'],
            ['kode_klausul' => '8.2.4', 'judul' => 'Pemberitahuan perubahan Sub-processor kepada Controller', 'kategori' => 'teknologi', 'domain_peran' => 'processor'],
            ['kode_klausul' => '8.2.5', 'judul' => 'Pengembalian, pemusnahan, atau penghapusan data PII', 'kategori' => 'teknologi', 'domain_peran' => 'processor'],
            ['kode_klausul' => '8.3.1', 'judul' => 'Kewajiban kerahasiaan personil yang mengelola PII', 'kategori' => 'teknologi', 'domain_peran' => 'processor'],
            ['kode_klausul' => '8.3.2', 'judul' => 'Mendukung pelaksanaan Hak Subjek Data dari Controller', 'kategori' => 'teknologi', 'domain_peran' => 'processor'],
            ['kode_klausul' => '8.4.1', 'judul' => 'Pemberitahuan insiden kebocoran PII kepada Controller', 'kategori' => 'teknologi', 'domain_peran' => 'processor'],
            ['kode_klausul' => '8.4.2', 'judul' => 'Penyediaan bukti kepatuhan dan audit kepada Controller', 'kategori' => 'teknologi', 'domain_peran' => 'processor'],
        ];

        $now = now();
        $rows = array_map(fn ($c) => array_merge($c, [
            'framework_id' => $iso27701->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $controls);

        // Using updateOrCreate() instead of upsert() for partial-index compatibility.
        // See comment in seedIso27001() for explanation.
        foreach ($rows as $row) {
            Control::updateOrCreate(
                ['framework_id' => $row['framework_id'], 'kode_klausul' => $row['kode_klausul']],
                ['judul' => $row['judul'], 'kategori' => $row['kategori'], 'domain_peran' => $row['domain_peran'] ?? null, 'deskripsi' => $row['deskripsi'] ?? null, 'updated_at' => $row['updated_at']]
            );
        }

        $this->command->info('Berhasil menyemai '.count($rows).' kontrol ISO/IEC 27701:2025.');
    }
}
