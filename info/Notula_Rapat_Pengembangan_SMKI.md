# Notula Rapat: Pengembangan Sistem Manajemen Keamanan Informasi (SMKI)

**Agenda:** Klarifikasi Kebutuhan Sistem (Requirement Gathering) & Tindak Lanjut Fase Development
**Peserta Rapat:**
*   **Perwakilan Komdigi (BPSDM):** Bu Kaut (PIC Kepatuhan & Keamanan Informasi)
*   **Dosen PNJ:** Bu Anita, Bu Eis, Bu Elin
*   **Tim Mahasiswa:** Juen, Falih, Dika, Rafif, Raihan

---

## Poin Diskusi Utama

**1. Modul Manajemen Temuan & Risiko**
*   **Temuan Audit:** Ditemukan oleh Auditor (Internal/Eksternal), namun yang menginput ke dalam sistem adalah pengelola data (PIC / Admin Kepatuhan).
*   **Risiko Dasar (Risk Register):** Dikelola dan dinilai oleh Manajer Risiko atau Koordinator SMKI.

**2. Sistem Pelaporan Otomatis (Reporting)**
*   Laporan tidak bisa disamaratakan dan harus memiliki *template* PDF yang berbeda sesuai dengan peran pengguna.
*   **Admin Kepatuhan:** Membutuhkan laporan singkat (laporan cepat).
*   **Koordinator SMKI:** Membutuhkan laporan eksekutif.
*   **Auditor:** Membutuhkan laporan resmi.

**3. Hak Akses & Konfigurasi (RBAC - Role Based Access Control)**
*   Diperlukan pembagian peran yang sangat granular (Superadmin, Admin Kepatuhan, Koordinator SMKI, Auditor).
*   Peran dan hak akses (izin) harus dapat diubah, ditambah, atau dikurangi secara dinamis oleh Superadmin untuk menangani fleksibilitas tugas di masa depan.

**4. Dokumentasi Teknis & Desain Antarmuka (UI/UX)**
*   **Desain UI:** Preferensi warna merujuk pada identitas visual Komdigi (Kementerian Komunikasi dan Digital), yaitu dominan **Biru dan Putih**.
*   **Dokumentasi:** Tim mahasiswa diwajibkan menyusun dokumentasi teknis, *Data Flow Diagram*, manual penggunaan sistem, dan arsitektur teknologi yang digunakan (termasuk akses *repository* Git).

**5. Target Timeline (Tenggat Waktu)**
*   Tahap *development* sudah bisa dimulai (Fase 1).
*   Semua pengembangan fitur dan laporan harus selesai di bulan **Oktober**.
*   Presentasi akhir dan perilisan sistem ditargetkan pada akhir Oktober atau awal November.

---

## Tindak Lanjut (Action Items)

| Penanggung Jawab (PIC) | Tugas / Tindak Lanjut |
| :--- | :--- |
| **Bu Kaut (Komdigi)** | Menyediakan dan membagikan *template* laporan PDF untuk masing-masing *role* (Admin, Koordinator, Auditor). |
| **Bu Kaut (Komdigi)** | Mengirimkan *file spreadsheet* terkait standar ISO 27001 dan ISO 27701 (Klausul, Kontrol, Peta Risiko). |
| **Bu Kaut (Komdigi)** | Memberikan referensi dokumen SLA (*Service Level Agreement*). |
| **Tim Mahasiswa** | Merancang skema *database* dan membuat *Role-Based Access Control* (RBAC) yang dinamis. |
| **Tim Mahasiswa** | Menyesuaikan UI/UX sistem dengan preferensi warna biru-putih khas Komdigi. |
| **Tim Mahasiswa** | Memulai proses *coding/development* Fase 1 untuk dikejar target rilis sebelum Oktober berakhir. |
| **Semua Pihak** | Merencanakan agenda *catch-up* / uji coba sistem selanjutnya (estimasi sekitar pertengahan bulan depan). |
