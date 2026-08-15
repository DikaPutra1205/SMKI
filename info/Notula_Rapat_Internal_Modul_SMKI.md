# Notula Rapat Internal: Pembahasan Bukti Temuan & Modul Sistem SMKI

**Agenda:** Review Catatan Fitur, Diskusi Tabel Temuan, dan Rencana Kelanjutan Proyek  
**Peserta:**  
*   **Dosen PNJ:** Bu Anita, Bu Eis
*   **Tim Mahasiswa:** Juen, Falih, Dika, Rafif, Raihan

---

## Ringkasan Poin Diskusi

**1. Alur Temuan & Risiko (Audit Trail)**
*   **Self-Assessment:** Dilakukan oleh Auditee (PIC Satker).
*   **Temuan Terbuka (Open Findings):** Dicatat dan ditemukan oleh **Auditor** (bukan PIC/Admin Kepatuhan). Auditor melakukan verifikasi langsung ke lapangan.
*   **Risiko:** Auditor (dan/atau Koordinator SMKI) yang akan mengidentifikasi tingkat risiko berdasarkan temuan yang ada.

**2. Struktur Database (Schema)**
*   Disepakati untuk menyatukan beberapa fungsionalitas dalam satu tabel agar tidak *redundant* (tumpang tindih).
*   *File Evidence* (Bukti Kepatuhan) harus ditambahkan atribut **"Versi" (Version Control)**. Ini karena dalam satu indikator, PIC mungkin mengunggah perbaikan dokumen lebih dari satu kali jika sebelumnya dinyatakan "Tidak Sesuai" oleh Auditor.
*   ID atau *Primary Key* antar entitas dalam skema ISO 27001 (Klausul -> Kontrol -> Sub-Kontrol) harus diselaraskan.

**3. Format Pelaporan (Reporting) & Ekspor Data**
*   Laporan PDF harus dapat di-*generate* langsung dari sistem.
*   **Admin Kepatuhan:** Membutuhkan **Laporan Cepat (Quick Report)** untuk melihat rangkuman kesesuaian.
*   **Koordinator:** Membutuhkan **Laporan Eksekutif** (Dashboard / *Summary* tingkat atas).
*   **Auditor:** Membutuhkan **Laporan Resmi** (Audit Report) yang komprehensif.

**4. Integrasi Sistem (SSO / Out of Scope)**
*   Integrasi *Single Sign-On* (SSO) dengan portal Digiteam / Portal Komdigi diputuskan **TIDAK MASUK** dalam cakupan (Out of Scope) fase ini. Akan dikerjakan pada tahun atau fase pengembangan berikutnya.

**5. Pembagian Hak Akses (RBAC) & Akses Data (Read-Only)**
*   Disepakati perlunya pengaturan akses *Granular RBAC* per unit/modul kerja.
*   Auditor memiliki hak untuk menilai dan membuat temuan, namun untuk beberapa dokumen referensi, statusnya hanya *Read-Only* (hanya bisa membaca, tidak bisa mengubah).

**6. Target Waktu & Rencana Kerja**
*   **Development:** Dimulai segera setelah skema *database* disetujui. Tim mahasiswa akan membagi tugas (*Frontend, Backend, Database*).
*   **Timeline:** Seluruh modul laporan (*Reporting*) wajib diselesaikan dan dipresentasikan pada bulan **Oktober**.

---

## Tindak Lanjut (Action Items)

| Penanggung Jawab | Tindak Lanjut |
| :--- | :--- |
| **Juen & Tim** | Merombak arsitektur tabel *Database* (menyatukan log temuan & menambahkan atribut *Version* pada lampiran bukti). |
| **Tim Mahasiswa** | Merancang *User Interface* (UI) untuk *form* pengisian *evidence* beserta *history* versinya. |
| **Tim Mahasiswa** | Membangun fitur *generate* PDF dengan 3 *template* berbeda (Cepat, Eksekutif, Resmi). |
| **Bu Anita / Dosen** | Mengoordinasikan jadwal pertemuan (uji coba / *showcase*) berikutnya dengan pihak Komdigi (Bu Kaut). |
| **Semua Anggota Tim** | Menyelesaikan fase pengerjaan modul hingga tuntas sebelum jadwal presentasi di akhir September/Oktober. |
