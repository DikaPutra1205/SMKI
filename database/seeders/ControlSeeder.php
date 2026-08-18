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
            // KLAUSUL 4-10 (Persyaratan Manajemen ISO 27001)
            // =========================================================
            ['kode_klausul' => '4.1',  'judul' => 'Memahami Organisasi dan Konteksnya',                         'kategori' => 'klausul_4_10', 'deskripsi' => 'Menentukan isu internal dan eksternal yang relevan dengan tujuan organisasi.'],
            ['kode_klausul' => '4.2',  'judul' => 'Memahami Kebutuhan Pihak yang Berkepentingan',              'kategori' => 'klausul_4_10', 'deskripsi' => 'Menentukan pihak yang berkepentingan dan persyaratan mereka.'],
            ['kode_klausul' => '4.3',  'judul' => 'Menentukan Ruang Lingkup SMKI',                             'kategori' => 'klausul_4_10', 'deskripsi' => 'Menetapkan batasan dan penerapan SMKI.'],
            ['kode_klausul' => '4.4',  'judul' => 'Sistem Manajemen Keamanan Informasi',                       'kategori' => 'klausul_4_10', 'deskripsi' => 'Membangun, menerapkan, memelihara, dan terus meningkatkan SMKI.'],
            ['kode_klausul' => '5.1',  'judul' => 'Kepemimpinan dan Komitmen',                                  'kategori' => 'klausul_4_10', 'deskripsi' => 'Manajemen puncak menunjukkan kepemimpinan dan komitmen terhadap SMKI.'],
            ['kode_klausul' => '5.2',  'judul' => 'Kebijakan Keamanan Informasi',                              'kategori' => 'klausul_4_10', 'deskripsi' => 'Menetapkan kebijakan keamanan informasi yang sesuai.'],
            ['kode_klausul' => '5.3',  'judul' => 'Peran, Tanggung Jawab, dan Wewenang Organisasi',            'kategori' => 'klausul_4_10', 'deskripsi' => 'Menetapkan dan mengkomunikasikan peran dan tanggung jawab keamanan informasi.'],
            ['kode_klausul' => '6.1',  'judul' => 'Tindakan untuk Mengatasi Risiko dan Peluang',               'kategori' => 'klausul_4_10', 'deskripsi' => 'Perencanaan untuk mengatasi risiko dan peluang yang ditentukan.'],
            ['kode_klausul' => '6.1.2', 'judul' => 'Penilaian Risiko Keamanan Informasi',                       'kategori' => 'klausul_4_10', 'deskripsi' => 'Mendefinisikan dan menerapkan proses penilaian risiko keamanan informasi.'],
            ['kode_klausul' => '6.1.3', 'judul' => 'Perlakuan Risiko Keamanan Informasi',                       'kategori' => 'klausul_4_10', 'deskripsi' => 'Mendefinisikan dan menerapkan proses perlakuan risiko keamanan informasi.'],
            ['kode_klausul' => '6.2',  'judul' => 'Tujuan Keamanan Informasi dan Perencanaan untuk Mencapainya', 'kategori' => 'klausul_4_10', 'deskripsi' => 'Menetapkan tujuan keamanan informasi pada fungsi dan tingkatan yang relevan.'],
            ['kode_klausul' => '6.3',  'judul' => 'Perencanaan Perubahan',                                      'kategori' => 'klausul_4_10', 'deskripsi' => 'Menentukan cara untuk melaksanakan perubahan pada SMKI secara terencana.'],
            ['kode_klausul' => '7.1',  'judul' => 'Sumber Daya',                                               'kategori' => 'klausul_4_10', 'deskripsi' => 'Menentukan dan menyediakan sumber daya yang dibutuhkan untuk SMKI.'],
            ['kode_klausul' => '7.2',  'judul' => 'Kompetensi',                                                 'kategori' => 'klausul_4_10', 'deskripsi' => 'Menentukan kompetensi yang diperlukan dari personel yang bekerja di bawah kendali organisasi.'],
            ['kode_klausul' => '7.3',  'judul' => 'Kepedulian',                                                 'kategori' => 'klausul_4_10', 'deskripsi' => 'Memastikan personel mengetahui kebijakan keamanan informasi dan kontribusi mereka.'],
            ['kode_klausul' => '7.4',  'judul' => 'Komunikasi',                                                 'kategori' => 'klausul_4_10', 'deskripsi' => 'Menentukan kebutuhan komunikasi internal dan eksternal terkait SMKI.'],
            ['kode_klausul' => '7.5',  'judul' => 'Informasi Terdokumentasi',                                   'kategori' => 'klausul_4_10', 'deskripsi' => 'SMKI harus mencakup informasi terdokumentasi yang dipersyaratkan oleh standar.'],
            ['kode_klausul' => '8.1',  'judul' => 'Perencanaan dan Pengendalian Operasional',                   'kategori' => 'klausul_4_10', 'deskripsi' => 'Merencanakan, menerapkan, mengendalikan, dan memelihara proses yang dibutuhkan.'],
            ['kode_klausul' => '8.2',  'judul' => 'Penilaian Risiko Keamanan Informasi (Operasional)',          'kategori' => 'klausul_4_10', 'deskripsi' => 'Melakukan penilaian risiko keamanan informasi secara berkala.'],
            ['kode_klausul' => '8.3',  'judul' => 'Perlakuan Risiko Keamanan Informasi (Operasional)',          'kategori' => 'klausul_4_10', 'deskripsi' => 'Menerapkan rencana perlakuan risiko keamanan informasi.'],
            ['kode_klausul' => '9.1',  'judul' => 'Pemantauan, Pengukuran, Analisis, dan Evaluasi',             'kategori' => 'klausul_4_10', 'deskripsi' => 'Mengevaluasi kinerja keamanan informasi dan efektivitas SMKI.'],
            ['kode_klausul' => '9.2',  'judul' => 'Audit Internal',                                             'kategori' => 'klausul_4_10', 'deskripsi' => 'Melakukan audit internal pada interval yang terencana.'],
            ['kode_klausul' => '9.3',  'judul' => 'Tinjauan Manajemen',                                         'kategori' => 'klausul_4_10', 'deskripsi' => 'Manajemen puncak meninjau SMKI pada interval yang terencana.'],
            ['kode_klausul' => '10.1', 'judul' => 'Peningkatan Berkelanjutan',                                  'kategori' => 'klausul_4_10', 'deskripsi' => 'Terus meningkatkan kesesuaian, kecukupan, dan efektivitas SMKI.'],
            ['kode_klausul' => '10.2', 'judul' => 'Ketidaksesuaian dan Tindakan Korektif',                      'kategori' => 'klausul_4_10', 'deskripsi' => 'Menangani ketidaksesuaian dan mengambil tindakan korektif.'],

            // =========================================================
            // ANNEX A — A.5 Kontrol Organisasi (37 kontrol)
            // =========================================================
            ['kode_klausul' => 'A.5.1',  'judul' => 'Kebijakan untuk Keamanan Informasi',                              'kategori' => 'annex_a', 'deskripsi' => 'Kebijakan keamanan informasi dan kebijakan khusus topik harus didefinisikan, disetujui oleh manajemen, diterbitkan, dikomunikasikan, dan diakui oleh personel yang relevan.'],
            ['kode_klausul' => 'A.5.2',  'judul' => 'Peran dan Tanggung Jawab Keamanan Informasi',                     'kategori' => 'annex_a', 'deskripsi' => 'Peran dan tanggung jawab keamanan informasi harus didefinisikan dan dialokasikan sesuai dengan kebutuhan organisasi.'],
            ['kode_klausul' => 'A.5.3',  'judul' => 'Pemisahan Tugas',                                                 'kategori' => 'annex_a', 'deskripsi' => 'Tugas dan area tanggung jawab yang saling bertentangan harus dipisahkan.'],
            ['kode_klausul' => 'A.5.4',  'judul' => 'Tanggung Jawab Manajemen',                                        'kategori' => 'annex_a', 'deskripsi' => 'Manajemen harus mewajibkan semua personel untuk menerapkan keamanan informasi sesuai kebijakan.'],
            ['kode_klausul' => 'A.5.5',  'judul' => 'Hubungan dengan Otoritas',                                        'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus membangun dan memelihara hubungan dengan otoritas yang relevan.'],
            ['kode_klausul' => 'A.5.6',  'judul' => 'Hubungan dengan Kelompok Minat Khusus',                           'kategori' => 'annex_a', 'deskripsi' => 'Membangun dan memelihara kontak dengan kelompok minat khusus atau forum keamanan spesialis.'],
            ['kode_klausul' => 'A.5.7',  'judul' => 'Intelijen Ancaman',                                               'kategori' => 'annex_a', 'deskripsi' => 'Informasi terkait ancaman keamanan informasi harus dikumpulkan dan dianalisis.'],
            ['kode_klausul' => 'A.5.8',  'judul' => 'Keamanan Informasi dalam Manajemen Proyek',                       'kategori' => 'annex_a', 'deskripsi' => 'Keamanan informasi harus diintegrasikan dalam manajemen proyek.'],
            ['kode_klausul' => 'A.5.9',  'judul' => 'Inventarisasi Informasi dan Aset Terkait Lainnya',                'kategori' => 'annex_a', 'deskripsi' => 'Inventaris informasi dan aset terkait lainnya, termasuk pemilik, harus dikembangkan dan dipelihara.'],
            ['kode_klausul' => 'A.5.10', 'judul' => 'Penggunaan yang Dapat Diterima atas Informasi dan Aset Terkait',  'kategori' => 'annex_a', 'deskripsi' => 'Aturan penggunaan informasi dan aset terkait yang dapat diterima harus diidentifikasi, didokumentasikan, dan diimplementasikan.'],
            ['kode_klausul' => 'A.5.11', 'judul' => 'Pengembalian Aset',                                               'kategori' => 'annex_a', 'deskripsi' => 'Personel dan pihak eksternal lainnya harus mengembalikan semua aset milik organisasi saat terminasi.'],
            ['kode_klausul' => 'A.5.12', 'judul' => 'Klasifikasi Informasi',                                           'kategori' => 'annex_a', 'deskripsi' => 'Informasi harus diklasifikasikan sesuai dengan kebutuhan keamanan informasi organisasi berdasarkan kerahasiaan, integritas, ketersediaan.'],
            ['kode_klausul' => 'A.5.13', 'judul' => 'Pelabelan Informasi',                                             'kategori' => 'annex_a', 'deskripsi' => 'Seperangkat prosedur pelabelan informasi yang tepat harus dikembangkan dan diterapkan sesuai skema klasifikasi informasi.'],
            ['kode_klausul' => 'A.5.14', 'judul' => 'Transfer Informasi',                                              'kategori' => 'annex_a', 'deskripsi' => 'Aturan, prosedur, atau perjanjian transfer informasi harus ada untuk semua jenis fasilitas transfer.'],
            ['kode_klausul' => 'A.5.15', 'judul' => 'Kontrol Akses',                                                   'kategori' => 'annex_a', 'deskripsi' => 'Aturan untuk mengontrol akses fisik dan logis ke informasi dan aset terkait lainnya harus ditetapkan dan diterapkan.'],
            ['kode_klausul' => 'A.5.16', 'judul' => 'Manajemen Identitas',                                             'kategori' => 'annex_a', 'deskripsi' => 'Siklus hidup penuh identitas harus dikelola.'],
            ['kode_klausul' => 'A.5.17', 'judul' => 'Informasi Autentikasi',                                           'kategori' => 'annex_a', 'deskripsi' => 'Alokasi informasi autentikasi harus dikendalikan oleh proses manajemen.'],
            ['kode_klausul' => 'A.5.18', 'judul' => 'Hak Akses',                                                       'kategori' => 'annex_a', 'deskripsi' => 'Hak akses ke informasi dan aset terkait lainnya harus disediakan, ditinjau, dimodifikasi, dan dihapus sesuai kebijakan kontrol akses.'],
            ['kode_klausul' => 'A.5.19', 'judul' => 'Keamanan Informasi dalam Hubungan Pemasok',                       'kategori' => 'annex_a', 'deskripsi' => 'Proses dan prosedur harus didefinisikan dan diterapkan untuk mengelola risiko keamanan informasi terkait penggunaan produk atau layanan pemasok.'],
            ['kode_klausul' => 'A.5.20', 'judul' => 'Mengatasi Keamanan Informasi dalam Perjanjian Pemasok',            'kategori' => 'annex_a', 'deskripsi' => 'Persyaratan keamanan informasi yang relevan harus ditetapkan dan disepakati dengan setiap pemasok.'],
            ['kode_klausul' => 'A.5.21', 'judul' => 'Mengelola Keamanan Informasi dalam Rantai Pasokan TIK',            'kategori' => 'annex_a', 'deskripsi' => 'Proses dan prosedur harus didefinisikan dan diterapkan untuk mengelola risiko keamanan informasi terkait rantai pasokan produk dan layanan TIK.'],
            ['kode_klausul' => 'A.5.22', 'judul' => 'Pemantauan, Peninjauan, dan Manajemen Perubahan Layanan Pemasok',  'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus secara teratur memantau, meninjau, mengevaluasi, dan mengelola perubahan dalam praktik keamanan informasi dan penyediaan layanan pemasok.'],
            ['kode_klausul' => 'A.5.23', 'judul' => 'Keamanan Informasi untuk Penggunaan Layanan Cloud',                'kategori' => 'annex_a', 'deskripsi' => 'Proses untuk akuisisi, penggunaan, pengelolaan, dan penghentian layanan cloud harus ditetapkan sesuai dengan persyaratan keamanan informasi organisasi.'],
            ['kode_klausul' => 'A.5.24', 'judul' => 'Perencanaan dan Persiapan Manajemen Insiden Keamanan Informasi',  'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus merencanakan dan mempersiapkan pengelolaan insiden keamanan informasi dengan menetapkan proses, peran, dan tanggung jawab.'],
            ['kode_klausul' => 'A.5.25', 'judul' => 'Penilaian dan Keputusan tentang Peristiwa Keamanan Informasi',    'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus menilai peristiwa keamanan informasi dan memutuskan apakah peristiwa tersebut harus diklasifikasikan sebagai insiden keamanan informasi.'],
            ['kode_klausul' => 'A.5.26', 'judul' => 'Respons terhadap Insiden Keamanan Informasi',                      'kategori' => 'annex_a', 'deskripsi' => 'Insiden keamanan informasi harus direspons sesuai prosedur yang terdokumentasi.'],
            ['kode_klausul' => 'A.5.27', 'judul' => 'Pembelajaran dari Insiden Keamanan Informasi',                    'kategori' => 'annex_a', 'deskripsi' => 'Pengetahuan yang diperoleh dari insiden keamanan informasi harus digunakan untuk memperkuat dan meningkatkan kontrol keamanan informasi.'],
            ['kode_klausul' => 'A.5.28', 'judul' => 'Pengumpulan Bukti',                                               'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus menetapkan dan menerapkan prosedur untuk identifikasi, pengumpulan, akuisisi, dan pelestarian bukti terkait peristiwa keamanan informasi.'],
            ['kode_klausul' => 'A.5.29', 'judul' => 'Keamanan Informasi Selama Gangguan',                               'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus merencanakan bagaimana mempertahankan keamanan informasi pada tingkat yang sesuai selama gangguan.'],
            ['kode_klausul' => 'A.5.30', 'judul' => 'Kesiapan TIK untuk Kelangsungan Bisnis',                           'kategori' => 'annex_a', 'deskripsi' => 'Kesiapan TIK harus direncanakan, diterapkan, dipelihara, dan diuji berdasarkan tujuan kelangsungan bisnis dan persyaratan kelangsungan TIK.'],
            ['kode_klausul' => 'A.5.31', 'judul' => 'Persyaratan Hukum, Peraturan, dan Kontrak',                       'kategori' => 'annex_a', 'deskripsi' => 'Persyaratan hukum, perundang-undangan, peraturan, dan kontrak yang relevan dengan keamanan informasi harus diidentifikasi, didokumentasikan, dan selalu diperbarui.'],
            ['kode_klausul' => 'A.5.32', 'judul' => 'Hak Kekayaan Intelektual',                                        'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus menerapkan prosedur yang tepat untuk melindungi hak kekayaan intelektual.'],
            ['kode_klausul' => 'A.5.33', 'judul' => 'Perlindungan Catatan',                                             'kategori' => 'annex_a', 'deskripsi' => 'Catatan harus dilindungi dari kehilangan, kehancuran, pemalsuan, akses tidak sah, dan pelepasan tidak sah.'],
            ['kode_klausul' => 'A.5.34', 'judul' => 'Privasi dan Perlindungan Informasi Identitas Pribadi (PII)',       'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus mengidentifikasi dan memenuhi persyaratan privasi dan perlindungan PII sesuai undang-undang dan peraturan yang berlaku.'],
            ['kode_klausul' => 'A.5.35', 'judul' => 'Tinjauan Independen tentang Keamanan Informasi',                   'kategori' => 'annex_a', 'deskripsi' => 'Pendekatan organisasi dalam mengelola keamanan informasi dan penerapannya harus ditinjau secara independen pada interval yang direncanakan.'],
            ['kode_klausul' => 'A.5.36', 'judul' => 'Kepatuhan terhadap Kebijakan dan Standar Keamanan Informasi',     'kategori' => 'annex_a', 'deskripsi' => 'Manajer harus secara teratur meninjau kepatuhan pemrosesan informasi dan prosedur dalam bidang tanggung jawabnya dengan kebijakan keamanan informasi.'],
            ['kode_klausul' => 'A.5.37', 'judul' => 'Prosedur Operasi yang Terdokumentasi',                            'kategori' => 'annex_a', 'deskripsi' => 'Prosedur operasi untuk fasilitas pemrosesan informasi harus didokumentasikan dan tersedia untuk pengguna yang membutuhkan.'],

            // =========================================================
            // ANNEX A — A.6 Kontrol Personel (8 kontrol)
            // =========================================================
            ['kode_klausul' => 'A.6.1',  'judul' => 'Penyaringan Personel',                                            'kategori' => 'annex_a', 'deskripsi' => 'Pemeriksaan verifikasi latar belakang pada semua kandidat harus dilakukan sebelum bergabung.'],
            ['kode_klausul' => 'A.6.2',  'judul' => 'Syarat dan Ketentuan Ketenagakerjaan',                            'kategori' => 'annex_a', 'deskripsi' => 'Perjanjian kerja harus menyatakan tanggung jawab personel dan organisasi untuk keamanan informasi.'],
            ['kode_klausul' => 'A.6.3',  'judul' => 'Kesadaran, Pendidikan, dan Pelatihan Keamanan Informasi',         'kategori' => 'annex_a', 'deskripsi' => 'Personel harus menerima pelatihan kesadaran keamanan informasi yang sesuai dan pembaruan rutin.'],
            ['kode_klausul' => 'A.6.4',  'judul' => 'Proses Disipliner',                                               'kategori' => 'annex_a', 'deskripsi' => 'Proses disipliner harus diformalkan dan dikomunikasikan untuk menangani tindakan pelanggaran keamanan informasi.'],
            ['kode_klausul' => 'A.6.5',  'judul' => 'Tanggung Jawab Setelah Pemutusan atau Perubahan Hubungan Kerja',  'kategori' => 'annex_a', 'deskripsi' => 'Tanggung jawab dan kewajiban keamanan informasi yang tetap berlaku setelah pemutusan atau perubahan hubungan kerja harus didefinisikan dan ditegakkan.'],
            ['kode_klausul' => 'A.6.6',  'judul' => 'Perjanjian Kerahasiaan atau Non-Disclosure (NDA)',                'kategori' => 'annex_a', 'deskripsi' => 'Perjanjian kerahasiaan atau non-disclosure yang mencerminkan kebutuhan organisasi harus diidentifikasi, ditinjau secara berkala, dan ditandatangani.'],
            ['kode_klausul' => 'A.6.7',  'judul' => 'Kerja Jarak Jauh (Remote Working)',                               'kategori' => 'annex_a', 'deskripsi' => 'Langkah-langkah keamanan harus diterapkan ketika personel melakukan kerja jarak jauh.'],
            ['kode_klausul' => 'A.6.8',  'judul' => 'Pelaporan Peristiwa Keamanan Informasi',                          'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus menyediakan mekanisme bagi personel untuk melaporkan peristiwa keamanan informasi yang diamati atau dicurigai secara tepat waktu.'],

            // =========================================================
            // ANNEX A — A.7 Kontrol Fisik (14 kontrol)
            // =========================================================
            ['kode_klausul' => 'A.7.1',  'judul' => 'Perimeter Keamanan Fisik',                                        'kategori' => 'annex_a', 'deskripsi' => 'Perimeter keamanan harus didefinisikan dan digunakan untuk melindungi area yang berisi informasi dan aset terkait lainnya.'],
            ['kode_klausul' => 'A.7.2',  'judul' => 'Entri Fisik',                                                     'kategori' => 'annex_a', 'deskripsi' => 'Area aman harus dilindungi oleh kontrol entri dan titik akses yang tepat.'],
            ['kode_klausul' => 'A.7.3',  'judul' => 'Pengamanan Kantor, Ruangan, dan Fasilitas',                       'kategori' => 'annex_a', 'deskripsi' => 'Keamanan fisik untuk kantor, ruangan, dan fasilitas harus dirancang dan diterapkan.'],
            ['kode_klausul' => 'A.7.4',  'judul' => 'Pemantauan Keamanan Fisik',                                       'kategori' => 'annex_a', 'deskripsi' => 'Premis harus terus dipantau untuk mendeteksi akses fisik yang tidak sah.'],
            ['kode_klausul' => 'A.7.5',  'judul' => 'Perlindungan Terhadap Ancaman Fisik dan Lingkungan',              'kategori' => 'annex_a', 'deskripsi' => 'Perlindungan terhadap ancaman fisik dan lingkungan harus dirancang dan diterapkan.'],
            ['kode_klausul' => 'A.7.6',  'judul' => 'Bekerja di Area Aman',                                            'kategori' => 'annex_a', 'deskripsi' => 'Langkah-langkah keamanan untuk bekerja di area aman harus dirancang dan diterapkan.'],
            ['kode_klausul' => 'A.7.7',  'judul' => 'Meja Bersih dan Layar Bersih (Clear Desk & Clear Screen)',        'kategori' => 'annex_a', 'deskripsi' => 'Aturan meja bersih untuk kertas dan media penyimpanan yang dapat dilepas serta aturan layar bersih harus didefinisikan dan ditegakkan.'],
            ['kode_klausul' => 'A.7.8',  'judul' => 'Penempatan dan Perlindungan Peralatan',                           'kategori' => 'annex_a', 'deskripsi' => 'Peralatan harus ditempatkan secara aman dan dilindungi dari bahaya lingkungan dan akses tidak sah.'],
            ['kode_klausul' => 'A.7.9',  'judul' => 'Keamanan Aset di Luar Lokasi',                                    'kategori' => 'annex_a', 'deskripsi' => 'Aset di luar lokasi harus dilindungi dengan mempertimbangkan berbagai risiko bekerja di luar lokasi organisasi.'],
            ['kode_klausul' => 'A.7.10', 'judul' => 'Media Penyimpanan',                                               'kategori' => 'annex_a', 'deskripsi' => 'Media penyimpanan harus dikelola melalui siklus hidup akuisisi, penggunaan, transportasi, dan pembuangan.'],
            ['kode_klausul' => 'A.7.11', 'judul' => 'Utilitas Pendukung',                                              'kategori' => 'annex_a', 'deskripsi' => 'Fasilitas pemrosesan informasi harus dilindungi dari kegagalan daya dan gangguan lain yang disebabkan oleh kegagalan utilitas pendukung.'],
            ['kode_klausul' => 'A.7.12', 'judul' => 'Keamanan Kabel',                                                  'kategori' => 'annex_a', 'deskripsi' => 'Kabel yang membawa daya, data, atau mendukung layanan informasi harus dilindungi dari intersepsi, interferensi, atau kerusakan.'],
            ['kode_klausul' => 'A.7.13', 'judul' => 'Pemeliharaan Peralatan',                                          'kategori' => 'annex_a', 'deskripsi' => 'Peralatan harus dipelihara dengan benar untuk memastikan ketersediaan dan integritasnya.'],
            ['kode_klausul' => 'A.7.14', 'judul' => 'Pembuangan Aman atau Penggunaan Kembali Peralatan',               'kategori' => 'annex_a', 'deskripsi' => 'Item peralatan yang berisi media penyimpanan harus diverifikasi untuk memastikan bahwa data sensitif telah dihapus secara aman sebelum dibuang.'],

            // =========================================================
            // ANNEX A — A.8 Kontrol Teknologi (34 kontrol)
            // =========================================================
            ['kode_klausul' => 'A.8.1',  'judul' => 'Perangkat Pengguna Akhir (User Endpoint Devices)',                'kategori' => 'annex_a', 'deskripsi' => 'Informasi yang disimpan pada, diproses oleh, atau dapat diakses melalui perangkat titik akhir pengguna harus dilindungi.'],
            ['kode_klausul' => 'A.8.2',  'judul' => 'Hak Akses Istimewa (Privileged Access Rights)',                   'kategori' => 'annex_a', 'deskripsi' => 'Alokasi dan penggunaan hak akses istimewa harus dibatasi dan dikendalikan secara ketat.'],
            ['kode_klausul' => 'A.8.3',  'judul' => 'Pembatasan Akses Informasi',                                      'kategori' => 'annex_a', 'deskripsi' => 'Akses ke informasi dan fasilitas pemrosesan informasi harus dibatasi sesuai dengan kebijakan kontrol akses.'],
            ['kode_klausul' => 'A.8.4',  'judul' => 'Akses ke Kode Sumber (Source Code)',                              'kategori' => 'annex_a', 'deskripsi' => 'Akses baca dan tulis ke kode sumber aplikasi, alat pengembangan, dan pustaka perangkat lunak harus dikelola dengan tepat.'],
            ['kode_klausul' => 'A.8.5',  'judul' => 'Autentikasi Aman',                                                'kategori' => 'annex_a', 'deskripsi' => 'Teknologi dan prosedur autentikasi aman harus diterapkan berdasarkan penilaian risiko akses.'],
            ['kode_klausul' => 'A.8.6',  'judul' => 'Manajemen Kapasitas',                                             'kategori' => 'annex_a', 'deskripsi' => 'Penggunaan sumber daya harus dipantau dan disesuaikan sejalan dengan persyaratan kapasitas saat ini dan yang diharapkan.'],
            ['kode_klausul' => 'A.8.7',  'judul' => 'Perlindungan Terhadap Malware',                                   'kategori' => 'annex_a', 'deskripsi' => 'Perlindungan terhadap malware harus diterapkan dan didukung oleh kesadaran pengguna yang tepat.'],
            ['kode_klausul' => 'A.8.8',  'judul' => 'Manajemen Kerentanan Teknis',                                     'kategori' => 'annex_a', 'deskripsi' => 'Informasi tentang kerentanan teknis sistem informasi harus diperoleh, dievaluasi, dan langkah mitigasi yang tepat harus diambil.'],
            ['kode_klausul' => 'A.8.9',  'judul' => 'Manajemen Konfigurasi',                                           'kategori' => 'annex_a', 'deskripsi' => 'Konfigurasi perangkat keras, perangkat lunak, layanan, dan jaringan harus ditetapkan, didokumentasikan, dan dipantau.'],
            ['kode_klausul' => 'A.8.10', 'judul' => 'Penghapusan Informasi',                                           'kategori' => 'annex_a', 'deskripsi' => 'Informasi yang disimpan dalam sistem informasi, perangkat, atau media penyimpanan lain harus dihapus ketika tidak lagi diperlukan.'],
            ['kode_klausul' => 'A.8.11', 'judul' => 'Masking Data (Penyelubungan Data)',                               'kategori' => 'annex_a', 'deskripsi' => 'Masking data harus digunakan sesuai dengan kebijakan kontrol akses dan persyaratan hukum yang berlaku.'],
            ['kode_klausul' => 'A.8.12', 'judul' => 'Pencegahan Kebocoran Data (Data Leakage Prevention)',              'kategori' => 'annex_a', 'deskripsi' => 'Langkah-langkah pencegahan kebocoran data harus diterapkan pada sistem, jaringan, dan perangkat lain yang memproses informasi sensitif.'],
            ['kode_klausul' => 'A.8.13', 'judul' => 'Pencadangan Informasi (Backup)',                                   'kategori' => 'annex_a', 'deskripsi' => 'Salinan cadangan informasi, perangkat lunak, dan sistem harus dibuat dan diuji secara teratur sesuai kebijakan pencadangan.'],
            ['kode_klausul' => 'A.8.14', 'judul' => 'Redundansi Fasilitas Pemrosesan Informasi',                        'kategori' => 'annex_a', 'deskripsi' => 'Fasilitas pemrosesan informasi harus diterapkan dengan redundansi yang cukup untuk memenuhi persyaratan ketersediaan.'],
            ['kode_klausul' => 'A.8.15', 'judul' => 'Pencatatan Log (Logging)',                                        'kategori' => 'annex_a', 'deskripsi' => 'Log yang mencatat aktivitas pengguna, pengecualian, kesalahan, dan peristiwa keamanan harus dibuat, disimpan, dan ditinjau secara berkala.'],
            ['kode_klausul' => 'A.8.16', 'judul' => 'Aktivitas Pemantauan (Monitoring)',                               'kategori' => 'annex_a', 'deskripsi' => 'Jaringan, sistem, dan aplikasi harus dipantau untuk perilaku anomali dan peristiwa keamanan informasi yang potensial.'],
            ['kode_klausul' => 'A.8.17', 'judul' => 'Sinkronisasi Jam',                                                'kategori' => 'annex_a', 'deskripsi' => 'Jam sistem informasi yang digunakan oleh organisasi harus disinkronkan ke sumber waktu referensi yang disetujui.'],
            ['kode_klausul' => 'A.8.18', 'judul' => 'Penggunaan Program Utilitas Istimewa',                            'kategori' => 'annex_a', 'deskripsi' => 'Penggunaan program utilitas yang dapat melampaui kontrol sistem dan aplikasi harus dibatasi dan dipantau dengan ketat.'],
            ['kode_klausul' => 'A.8.19', 'judul' => 'Instalasi Perangkat Lunak pada Sistem Operasional',               'kategori' => 'annex_a', 'deskripsi' => 'Prosedur dan langkah-langkah harus diterapkan untuk mengelola instalasi perangkat lunak pada sistem operasional secara aman.'],
            ['kode_klausul' => 'A.8.20', 'judul' => 'Keamanan Jaringan',                                               'kategori' => 'annex_a', 'deskripsi' => 'Jaringan dan perangkat jaringan harus diamankan, dikelola, dan dikendalikan untuk melindungi informasi dalam sistem dan aplikasi.'],
            ['kode_klausul' => 'A.8.21', 'judul' => 'Keamanan Layanan Jaringan',                                       'kategori' => 'annex_a', 'deskripsi' => 'Mekanisme keamanan, tingkat layanan, dan persyaratan layanan semua layanan jaringan harus diidentifikasi, diterapkan, dan dipantau.'],
            ['kode_klausul' => 'A.8.22', 'judul' => 'Segregasi Jaringan',                                              'kategori' => 'annex_a', 'deskripsi' => 'Kelompok layanan informasi, pengguna, dan sistem informasi harus dipisahkan dalam jaringan organisasi.'],
            ['kode_klausul' => 'A.8.23', 'judul' => 'Penyaringan Web',                                                 'kategori' => 'annex_a', 'deskripsi' => 'Akses ke situs web eksternal harus dikelola untuk mengurangi eksposur terhadap konten berbahaya.'],
            ['kode_klausul' => 'A.8.24', 'judul' => 'Penggunaan Kriptografi',                                          'kategori' => 'annex_a', 'deskripsi' => 'Aturan untuk penggunaan kriptografi yang efektif, termasuk manajemen kunci kriptografi, harus didefinisikan dan diterapkan.'],
            ['kode_klausul' => 'A.8.25', 'judul' => 'Siklus Hidup Pengembangan yang Aman',                             'kategori' => 'annex_a', 'deskripsi' => 'Aturan untuk pengembangan perangkat lunak dan sistem yang aman harus ditetapkan dan diterapkan.'],
            ['kode_klausul' => 'A.8.26', 'judul' => 'Persyaratan Keamanan Aplikasi',                                   'kategori' => 'annex_a', 'deskripsi' => 'Persyaratan keamanan informasi harus diidentifikasi, ditentukan, dan disetujui saat mengembangkan atau memperoleh aplikasi.'],
            ['kode_klausul' => 'A.8.27', 'judul' => 'Prinsip Rekayasa Sistem yang Aman',                               'kategori' => 'annex_a', 'deskripsi' => 'Prinsip rekayasa sistem yang aman harus ditetapkan, didokumentasikan, dipelihara, dan diterapkan pada setiap upaya rekayasa sistem informasi.'],
            ['kode_klausul' => 'A.8.28', 'judul' => 'Pengkodean yang Aman',                                            'kategori' => 'annex_a', 'deskripsi' => 'Prinsip pengkodean yang aman harus diterapkan pada pengembangan perangkat lunak.'],
            ['kode_klausul' => 'A.8.29', 'judul' => 'Pengujian Keamanan dalam Pengembangan dan Penerimaan',             'kategori' => 'annex_a', 'deskripsi' => 'Proses pengujian keamanan harus didefinisikan dan diterapkan dalam siklus hidup pengembangan.'],
            ['kode_klausul' => 'A.8.30', 'judul' => 'Pengembangan yang Dialihdayakan',                                 'kategori' => 'annex_a', 'deskripsi' => 'Organisasi harus mengarahkan, memantau, dan meninjau aktivitas yang terkait dengan pengembangan sistem yang dialihdayakan.'],
            ['kode_klausul' => 'A.8.31', 'judul' => 'Pemisahan Lingkungan Pengembangan, Pengujian, dan Produksi',       'kategori' => 'annex_a', 'deskripsi' => 'Lingkungan pengembangan, pengujian, dan produksi harus diidentifikasi dan diamankan.'],
            ['kode_klausul' => 'A.8.32', 'judul' => 'Manajemen Perubahan',                                             'kategori' => 'annex_a', 'deskripsi' => 'Perubahan fasilitas pemrosesan informasi dan sistem informasi harus tunduk pada prosedur manajemen perubahan.'],
            ['kode_klausul' => 'A.8.33', 'judul' => 'Informasi Pengujian',                                             'kategori' => 'annex_a', 'deskripsi' => 'Informasi pengujian harus dipilih, dilindungi, dan dikelola dengan tepat.'],
            ['kode_klausul' => 'A.8.34', 'judul' => 'Perlindungan Sistem Informasi Selama Pengujian Audit',             'kategori' => 'annex_a', 'deskripsi' => 'Pengujian audit dan aktivitas verifikasi lainnya yang melibatkan penilaian sistem operasional harus direncanakan dan disepakati antara penguji dan manajemen yang tepat.'],
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
                ['judul' => $row['judul'], 'kategori' => $row['kategori'], 'deskripsi' => $row['deskripsi'] ?? null, 'updated_at' => $row['updated_at']]
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
            // KLAUSUL 5: Persyaratan PIMS Khusus terkait ISO/IEC 27001
            // =========================================================
            ['kode_klausul' => '5.2.1', 'judul' => 'Memahami Konteks Organisasi terkait Pemrosesan Data Pribadi (PII)', 'kategori' => 'klausul_4_10', 'deskripsi' => 'Menentukan faktor internal dan eksternal yang memengaruhi peran organisasi sebagai Pengendali atau Pemroses Data Pribadi.'],
            ['kode_klausul' => '5.2.2', 'judul' => 'Memahami Kebutuhan dan Ekspektasi Subjek Data & Regulator PDP',      'kategori' => 'klausul_4_10', 'deskripsi' => 'Mengidentifikasi persyaratan hukum PDP, otoritas pengawas, dan hak-hak subjek data pribadi.'],
            ['kode_klausul' => '5.2.3', 'judul' => 'Menentukan Ruang Lingkup Sistem Manajemen Informasi Privasi (PIMS)', 'kategori' => 'klausul_4_10', 'deskripsi' => 'Menetapkan batas operasional dan sistem pemrosesan data pribadi yang dicakup dalam PIMS.'],
            ['kode_klausul' => '5.3.1', 'judul' => 'Kepemimpinan dan Komitmen terhadap Perlindungan Data Pribadi',      'kategori' => 'klausul_4_10', 'deskripsi' => 'Manajemen puncak memastikan kepatuhan terhadap prinsip pelindungan data pribadi.'],
            ['kode_klausul' => '5.3.2', 'judul' => 'Kebijakan Privasi dan Perlindungan Data Pribadi',                   'kategori' => 'klausul_4_10', 'deskripsi' => 'Menetapkan kebijakan privasi organisasi yang selaras dengan regulasi UU PDP.'],
            ['kode_klausul' => '5.3.3', 'judul' => 'Penunjukan Petugas Perlindungan Data (Data Protection Officer / DPO)', 'kategori' => 'klausul_4_10', 'deskripsi' => 'Menetapkan peran dan tanggung jawab DPO/Pejabat Pelindungan Data Pribadi.'],
            ['kode_klausul' => '5.4.1', 'judul' => 'Penilaian Dampak Perlindungan Data (DPIA / Privacy Impact Assessment)', 'kategori' => 'klausul_4_10', 'deskripsi' => 'Melakukan penilaian risiko privasi dan dampak pemrosesan data pribadi berisiko tinggi.'],
            ['kode_klausul' => '5.4.2', 'judul' => 'Rencana Perlakuan Risiko Informasi Privasi',                        'kategori' => 'klausul_4_10', 'deskripsi' => 'Menyusun langkah mitigasi dan perlakuan risiko privasi berdasarkan hasil DPIA.'],
            ['kode_klausul' => '5.5.1', 'judul' => 'Kompetensi dan Pelatihan Kesadaran Privasi Karyawan',               'kategori' => 'klausul_4_10', 'deskripsi' => 'Memastikan personel memahami prinsip tata kelola dan perlindungan data pribadi.'],
            ['kode_klausul' => '5.6.1', 'judul' => 'Pengendalian Operasional Pemrosesan Data Pribadi',                  'kategori' => 'klausul_4_10', 'deskripsi' => 'Mengoperasikan kontrol pemrosesan data pribadi sesuai prosedur yang terdokumentasi.'],
            ['kode_klausul' => '5.7.1', 'judul' => 'Evaluasi Kinerja dan Audit Internal PIMS',                          'kategori' => 'klausul_4_10', 'deskripsi' => 'Melakukan audit internal berkala terhadap efektivitas penerapan sistem manajemen privasi.'],
            ['kode_klausul' => '5.8.1', 'judul' => 'Peningkatan Berkelanjutan dan Penanganan Insiden Privasi',          'kategori' => 'klausul_4_10', 'deskripsi' => 'Melakukan tindakan korektif atas temuan ketidaksesuaian atau insiden kebocoran data.'],

            // =========================================================
            // KLAUSUL 6: Panduan PIMS Tambahan terkait ISO/IEC 27002
            // =========================================================
            ['kode_klausul' => '6.2.1', 'judul' => 'Inventarisasi dan Pemetaan Aliran Data Pribadi (Data Flow Mapping)', 'kategori' => 'annex_a', 'deskripsi' => 'Mendokumentasikan siklus hidup data pribadi: perolehan, penyimpanan, pengolahan, hingga pemusnahan.'],
            ['kode_klausul' => '6.3.1', 'judul' => 'Pemberian dan Pembatasan Hak Akses Data Pribadi Berbasis Peran',     'kategori' => 'annex_a', 'deskripsi' => 'Membatasi akses ke data pribadi hanya bagi personel yang berwenang (Need-to-Know Principle).'],
            ['kode_klausul' => '6.4.1', 'judul' => 'Enkripsi dan Kriptografi Data Pribadi (At-Rest dan In-Transit)',     'kategori' => 'annex_a', 'deskripsi' => 'Menerapkan enkripsi kuat pada penyimpanan basis data dan transmisi jaringan data pribadi.'],
            ['kode_klausul' => '6.5.1', 'judul' => 'Penyelubungan, Anonimisasi, dan Pseudonimisasi Data Pribadi',       'kategori' => 'annex_a', 'deskripsi' => 'Menerapkan teknik masking dan de-identifikasi untuk melindungi identitas subjek data.'],
            ['kode_klausul' => '6.6.1', 'judul' => 'Pencatatan Log Akses dan Pemrosesan Data Pribadi',                  'kategori' => 'annex_a', 'deskripsi' => 'Merekam jejak audit setiap aktivitas akses, perubahan, dan ekspor data pribadi.'],
            ['kode_klausul' => '6.7.1', 'judul' => 'Prosedur Pemberitahuan Kegagalan Perlindungan Data (Data Breach)',    'kategori' => 'annex_a', 'deskripsi' => 'Mekanisme pemberitahuan insiden kebocoran data kepada otoritas pengawas dan subjek data maksimal 3x24 jam.'],

            // =========================================================
            // KLAUSUL 7 (Annex A): Kontrol Khusus Pengendali Data Pribadi (PII Controllers)
            // =========================================================
            ['kode_klausul' => '7.2.1', 'judul' => 'Identifikasi Dasar Hukum Pemrosesan Data Pribadi (Legal Basis)',     'kategori' => 'annex_a', 'deskripsi' => 'Menetapkan dasar pemrosesan yang sah (persetujuan, kewajiban kontrak, kewajiban hukum, kepentingan vital).'],
            ['kode_klausul' => '7.2.2', 'judul' => 'Mekanisme Persetujuan Eksplisit (Consent Management)',              'kategori' => 'annex_a', 'deskripsi' => 'Menyediakan mekanisme persetujuan tertulis/rekam jejak elektronik yang sah dan dapat ditarik kembali.'],
            ['kode_klausul' => '7.2.3', 'judul' => 'Minimisasi Pengumpulan Data Pribadi (Data Minimization)',           'kategori' => 'annex_a', 'deskripsi' => 'Hanya mengumpulkan data pribadi yang benar-benar relevan dan terbatas sesuai tujuan.'],
            ['kode_klausul' => '7.3.1', 'judul' => 'Penyampaian Pemberitahuan Privasi (Privacy Notice)',                 'kategori' => 'annex_a', 'deskripsi' => 'Menyediakan informasi yang jelas, transparan, dan mudah dipahami mengenai tujuan pemrosesan data.'],
            ['kode_klausul' => '7.3.2', 'judul' => 'Pemenuhan Hak Subjek Data: Hak Akses dan Ralat Data',               'kategori' => 'annex_a', 'deskripsi' => 'Memfasilitasi permohonan subjek data untuk melihat dan memperbarui data pribadi miliknya.'],
            ['kode_klausul' => '7.3.3', 'judul' => 'Pemenuhan Hak Subjek Data: Penghapusan dan Pemusnahan (Erasure)',    'kategori' => 'annex_a', 'deskripsi' => 'Melaksanakan penghapusan dan pemusnahan data pribadi saat masa retensi berakhir atau persetujuan ditarik.'],
            ['kode_klausul' => '7.4.1', 'judul' => 'Penerapan Privasi Sejak Perancangan (Privacy by Design & Default)',  'kategori' => 'annex_a', 'deskripsi' => 'Mengintegrasikan perlindungan privasi sejak awal perancangan sistem informasi dan aplikasi.'],
            ['kode_klausul' => '7.4.2', 'judul' => 'Jadwal Retensi dan Pemusnahan Aman Data Pribadi',                   'kategori' => 'annex_a', 'deskripsi' => 'Menetapkan batas waktu penyimpanan dan prosedur pemusnahan data pribadi yang aman.'],
            ['kode_klausul' => '7.5.1', 'judul' => 'Pengendalian Transfer Data Pribadi Lintas Batas Negara',             'kategori' => 'annex_a', 'deskripsi' => 'Memastikan negara penerima memiliki standar perlindungan data yang setara atau klausul kontrak standar.'],
            ['kode_klausul' => '7.5.2', 'judul' => 'Perjanjian Pemrosesan Data Pribadi dengan Pihak Ketiga (DPA)',       'kategori' => 'annex_a', 'deskripsi' => 'Mengikat pihak ketiga dengan Data Processing Agreement (DPA) yang memuat klausul kepatuhan PDP.'],

            // =========================================================
            // KLAUSUL 8 (Annex B): Kontrol Khusus Pemroses Data Pribadi (PII Processors)
            // =========================================================
            ['kode_klausul' => '8.2.1', 'judul' => 'Pemrosesan Data Hanya Berdasarkan Instruksi Pengendali',            'kategori' => 'annex_a', 'deskripsi' => 'Pemroses dilarang menggunakan data pribadi di luar instruksi tertulis yang diberikan Pengendali.'],
            ['kode_klausul' => '8.3.1', 'judul' => 'Kewajiban Membantu Pengendali dalam Pemenuhan Hak Subjek Data',     'kategori' => 'annex_a', 'deskripsi' => 'Menyediakan fitur teknis untuk membantu Pengendali merespons permintaan subjek data.'],
            ['kode_klausul' => '8.4.1', 'judul' => 'Pengembalian atau Pemusnahan Data setelah Layanan Berakhir',         'kategori' => 'annex_a', 'deskripsi' => 'Mengembalikan semua data pribadi kepada Pengendali atau menghapusnya secara aman setelah kontrak selesai.'],
            ['kode_klausul' => '8.5.1', 'judul' => 'Persetujuan Tertulis Sebelum Melibatkan Sub-Pemroses (Sub-Processor)', 'kategori' => 'annex_a', 'deskripsi' => 'Mendapatkan persetujuan dari Pengendali Data sebelum menunjuk sub-pemroses pihak ketiga.'],
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
                ['judul' => $row['judul'], 'kategori' => $row['kategori'], 'deskripsi' => $row['deskripsi'] ?? null, 'updated_at' => $row['updated_at']]
            );
        }

        $this->command->info('Berhasil menyemai '.count($rows).' kontrol ISO/IEC 27701:2019 (PIMS & PDP).');
    }
}
