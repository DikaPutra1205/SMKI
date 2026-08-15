# 🚀 Panduan Setup & Instalasi — SMKI Backend

Panduan ini berisi langkah-langkah menjalankan project Backend Laravel di lingkungan lokal maupun server staging.

---

## 🛠️ Prasyarat Lingkungan (Prerequisites)

- **PHP:** >= 8.2 (dengan ekstensi `pdo_pgsql`, `pgsql`, `curl`, `mbstring`, `openssl`)
- **Composer:** >= 2.x
- **Basis Data:** PostgreSQL (disarankan Supabase Database)
- **Object Storage:** Supabase Storage (S3 Protocol)
- **Local Dev Server:** Laravel Herd / Laravel Artisan

---

## ⚙️ Langkah-Langkah Instalasi

### 1. Clone Repository
```bash
git clone <URL_REPO_GITHUB_ANDA>
cd smki-backend
```

### 2. Install Dependency PHP
```bash
composer install
```

### 3. Konfigurasi Environment (`.env`)
Salin file kredensial tim atau contoh template:
```bash
cp .env.example .env
```
Lalu isi konfigurasi database dan S3 sesuai file kredensial rahasia yang dibagikan.

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Jalankan Migrasi & Seeder Master Data
```bash
php artisan migrate --seed
```
*Perintah ini akan membuat 9 tabel database dan mengisi seluruh data master klausul ISO 27001 (118 kontrol), ISO 27701 (32 kontrol), 20 satuan kerja, dan 5 akun pengguna uji coba.*

### 6. Jalankan Server Lokal
- Jika menggunakan **Laravel Herd**: Langsung akses `http://smki-backend.test/api`
- Jika menggunakan **PHP Artisan**:
  ```bash
  php artisan serve
  ```
  *(Akses pada `http://127.0.0.1:8000/api`)*

---

## ⏰ Otomatisasi Checklist Bulanan (Cron & CLI)

Untuk men-generate lembar checklist kepatuhan untuk seluruh kontrol dan seluruh satuan kerja di awal bulan:
```bash
php artisan smki:generate-monthly-checklist
```
Perintah ini sudah terdaftar di penjadwalan otomatis Laravel (`routes/console.php`) untuk berjalan di awal setiap bulan (`0 0 1 * *`).
