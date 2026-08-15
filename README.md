# 🏛️ Sistem Kepatuhan Digital SMKI — Backend API
### Standar Keamanan Informasi (ISO/IEC 27001:2022) & Perlindungan Data Pribadi (ISO/IEC 27701:2019)
**Kementerian Komunikasi dan Digital Republik Indonesia (Komdigi)**

---

## 📌 Ringkasan Proyek

Repositori ini adalah Backend RESTful API untuk **Sistem Kepatuhan Digital SMKI**. Sistem ini dirancang untuk memfasilitasi evaluasi berkala kepatuhan standar keamanan informasi dan perlindungan data pribadi lintas satuan kerja di lingkungan Komdigi secara terotomatisasi, transparan, dan terukur.

---

## 🛠️ Tumpukan Teknologi (Tech Stack)

- **Framework Utama:** Laravel 12 (PHP 8.2+)
- **Basis Data:** PostgreSQL (Supabase Cloud Database)
- **Penyimpanan Berkas (Object Storage):** Supabase Storage (S3-Compatible Protocol)
- **Arsitektur API:** RESTful JSON API dengan Eager Loading & Database Indexing (B-Tree)
- **Jejak Audit:** Automated Polymorphic Model Observer (`SmkiObserver`)

---

## 📂 Struktur Dokumentasi (Docs)

Seluruh dokumentasi teknis, kontrak endpoint, dan panduan arsitektur telah dirapikan ke dalam folder **[`docs/`](docs/)**:

- 📖 **[Dokumentasi API Lengkap](docs/API_DOCUMENTATION.md)** — Daftar seluruh 42 endpoint JSON, parameter filter, query search, payload form-data, dan format response.
- 🗄️ **[Skema Basis Data (ERD & Tables)](docs/DATABASE_SCHEMA.md)** — Diagram relasi entitas dan struktur 9 tabel database PostgreSQL.
- 🚀 **[Panduan Instalasi & Setup](docs/SETUP_GUIDE.md)** — Langkah instalasi dependensi, migrasi basis data, dan konfigurasi lingkungan.

---

## ⚙️ Panduan Singkat Menjalankan Project

```bash
# 1. Install dependencies PHP
composer install

# 2. Salin environment configuration
cp .env.example .env
# (Isi konfigurasi database & S3 sesuai file kredensial tim)

# 3. Generate App Key
php artisan key:generate

# 4. Jalankan Migrasi dan Seeder Master Data (ISO 27001 & 27701)
php artisan migrate --seed

# 5. Jalankan Local Server
php artisan serve
```

---

## ⏰ Otomatisasi Checklist Bulanan (Monthly Automation)

Sistem dilengkapi dengan perintah CLI dan penjadwalan otomatis untuk menerbitkan lembar checklist kontrol baru untuk seluruh satuan kerja di awal setiap bulan:
```bash
php artisan smki:generate-monthly-checklist
```

---

## 👥 Pengguna Uji Coba (Test Users)

Semua akun pengujian di bawah ini sudah tersedia secara otomatis setelah menjalankan `php artisan db:seed`:

| Role | Email | Nama Pengguna | Satuan Kerja / Unit |
|---|---|---|---|
| `superadmin` | `superadmin@smki.test` | Super Admin | Kementerian Komunikasi dan Digital |
| `admin_kepatuhan` | `admin@smki.test` | Admin Kepatuhan | Biro Hukum dan Kerjasama |
| `koordinator_smki` | `koordinator@smki.test` | Koordinator SMKI | Biro Perencanaan |
| `auditor` | `auditor@smki.test` | Auditor Internal / Eksternal | Inspektorat Jenderal |
| `pic` | `pic@smki.test` | PIC Biro TI | Biro Teknologi Informasi |

*Password default semua user:* `password`
