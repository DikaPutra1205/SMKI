# 📖 Dokumentasi API Backend — Sistem Kepatuhan Digital SMKI (ISO 27001 & ISO 27701)

Dokumentasi ini disusun untuk mempermudah tim **Frontend (FE-A / FE-B)** dan rekan tim Backend dalam mengintegrasikan antarmuka dengan Backend API Laravel.

---

## 🌐 Informasi Dasar

- **Base URL Lokal (Herd):** `http://smki-backend.test/api`
- **Base URL Lokal (Artisan Serve):** `http://localhost:8000/api`
- **Header Standar (Wajib disertakan):**
  ```http
  Accept: application/json
  Content-Type: application/json
  ```
  *(Kecuali untuk endpoint upload file, gunakan `multipart/form-data`)*

---

## 📦 Format Standar JSON Response

Semua endpoint mengembalikan struktur JSON konsisten:

### Response Sukses:
```json
{
  "status": "success",
  "message": "Data berhasil diambil / disimpan",
  "data": { ... }
}
```

### Response Error / Validasi:
```json
{
  "status": "error",
  "message": "Pesan kegagalan",
  "errors": {
    "field_name": [
      "Pesan validasi error..."
    ]
  }
}
```

---

## 👥 Akun Uji Coba (Test Users & Roles)

Gunakan `id` atau `email` user berikut untuk simulasi pengujian hak akses peran:

| ID | Role | Email | Nama Pengguna | Satuan Kerja / Unit |
|---|---|---|---|---|
| `2` | `superadmin` | `superadmin@smki.test` | Super Admin | Kementerian Komunikasi dan Digital |
| `1` | `admin_kepatuhan` | `admin@smki.test` | Admin Kepatuhan | Biro Hukum dan Kerjasama |
| `3` | `koordinator_smki` | `koordinator@smki.test` | Koordinator SMKI | Biro Perencanaan |
| `4` | `auditor` | `auditor@smki.test` | Auditor Eksternal / Internal | Inspektorat Jenderal |
| `5` | `pic` | `pic@smki.test` | PIC Biro TI | Biro Teknologi Informasi (ID: 3) |

*Password default semua akun pengujian:* `password`

---

## 📋 DAFTAR ENDPOINT LENGKAP

---

### 1. Sesi Audit & Asesmen Checklist (`/checklist-sessions`)

#### A. Mengambil Daftar Sesi Audit
`GET /api/checklist-sessions`

**Query Parameters (Opsional):**
- `unit_id` (integer) — Filter berdasarkan Satker
- `framework_id` (integer) — Filter framework standar
- `periode` (string) — Filter berdasarkan periode (contoh: `?periode=Q1 2025` atau `?periode=Semester 1 2026`)
- `status` (string: `draft` | `in_progress` | `submitted` | `verified` | `closed`)
- `auditor_id` (integer) — Filter auditor penanggung jawab
- `search` (string) — Cari nama sesi, periode, atau konteks penilaian
- `trashed` (string: `only` | `with`)
- `all` (boolean) — Tanpa pagination
- `per_page` (integer, default 15)

#### B. Membuat Sesi Audit Baru (Auto-Provisioning Klausul Kontrol)
`POST /api/checklist-sessions`

**Request Body (JSON):**
```json
{
  "nama_sesi": "Audit Internal SMKI Semester 1 2026",
  "periode": "Semester 1 2026",
  "konteks_penilaian": "Penilaian mandiri unit kerja Biro TI dan layanan cloud publik",
  "unit_id": 3,
  "framework_id": 1,
  "auditor_id": 4,
  "start_date": "2026-03-01",
  "end_date": "2026-03-31",
  "status": "in_progress",
  "catatan": "Sesi audit berkala semester 1."
}
```
*Catatan: Sistem secara otomatis membuat (auto-provision) seluruh entri klausul checklist kontrol untuk unit dan sesi tersebut.*

#### C. Detail Sesi Audit (Termasuk Klausul & Summary Statistik)
`GET /api/checklist-sessions/{id}`

#### D. Update Sesi Audit
`PUT /api/checklist-sessions/{id}`

#### E. Submit Sesi Audit oleh PIC Unit
`POST /api/checklist-sessions/{id}/submit`
*Mengubah status sesi menjadi `submitted` agar siap diverifikasi auditor.*

#### F. Verifikasi & Tutup Sesi Audit (Lock / Freeze Snapshot)
`PATCH /api/checklist-sessions/{id}/verify`

**Request Body (JSON):**
```json
{
  "status": "closed",
  "catatan": "Semua klausul selesai diverifikasi. Hasil audit ditutup dan dikunci.",
  "auditor_id": 4
}
```

#### G. Soft Delete & Restore Sesi
- `DELETE /api/checklist-sessions/{id}`
- `POST /api/checklist-sessions/{id}/restore`

---

### 2. Checklist Kepatuhan (`/checklist-entries`)

#### A. Mengambil Daftar Checklist (dengan Filter Dinamis & Server-Side Pagination)
`GET /api/checklist-entries`

**Query Parameters (Opsional):**
- `session_id` (integer) — Filter berdasarkan Sesi Audit tertentu
- `unit_id` (integer) — Filter berdasarkan Satker (contoh: `?unit_id=3`)
- `status` (string: `compliant` | `partial` | `non_compliant` | `na`)
- `bulan` (integer 1-12) — Filter periode bulan (contoh: `?bulan=8`)
- `tahun` (integer) — Filter periode tahun (contoh: `?tahun=2026`)
- `kategori` (string: `annex_a` | `klausul_4_10`)
- `framework_id` (integer: `1` untuk ISO 27001, `2` untuk ISO 27701)
- `is_verified` (boolean: `true` / `false`) — Filter status verifikasi Admin
- `trashed` (string: `only` / `with`) — Filter data soft-deleted
- `search` (string) — Cari nomor klausul atau nama kontrol
- `all` (boolean: `true`) — Ambil semua data tanpa pagination
- `per_page` (integer, default 20) — Jumlah item per halaman

**Contoh Response:**
```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "last_page": 6,
    "total": 118,
    "per_page": 20,
    "data": [
      {
        "id": 1,
        "control_id": 1,
        "unit_id": 3,
        "pic_id": 5,
        "admin_id": 1,
        "status": "compliant",
        "catatan": "Kebijakan keamanan informasi telah ditandatangani.",
        "catatan_admin": "Bukti SK Kebijakan valid.",
        "tanggal_input": "2026-08-16T00:00:00.000000Z",
        "tanggal_verifikasi": "2026-08-16T00:00:00.000000Z",
        "control": {
          "id": 1,
          "framework_id": 1,
          "kode_klausul": "4.1",
          "judul": "Memahami Organisasi dan Konteksnya",
          "kategori": "klausul_4_10"
        },
        "unit": { "id": 3, "nama": "Biro Teknologi Informasi" },
        "pic": { "id": 5, "name": "PIC Biro TI" },
        "admin": { "id": 1, "name": "Admin Kepatuhan" },
        "active_evidence": {
          "id": 1,
          "version_number": 2,
          "label_revisi": "Dokumen Pembaruan (Revisi ke-2)",
          "file_url": "bukti/1/sk_kebijakan_v2.pdf",
          "is_active": true
        }
      }
    ]
  }
}
```

#### B. Detail Checklist
`GET /api/checklist-entries/{id}`
> Menampilkan detail lengkap klausul kontrol, catatan PIC, hasil verifikasi Admin, dan seluruh riwayat dokumen revisi bukti.

#### C. Update Penilaian & Unggah Bukti Sekaligus (Unified Action)
`POST /api/checklist-entries/{id}` *(dengan `_method: PUT` untuk form-data)* atau `PUT /api/checklist-entries/{id}`

**Payload (Form Data / JSON):**
- `status` (string: `compliant` | `partial` | `non_compliant` | `na`)
- `catatan` (string, opsional)
- `bukti_file` (file binary, opsional) — Jika disertakan, sistem otomatis mengunggah ke S3 dan menambah nomor revisi baru tanpa menghapus riwayat lama.
- `uploaded_by` (integer user ID, opsional)

#### D. Verifikasi oleh Admin Kepatuhan
`PATCH /api/checklist-entries/{id}/verify`
**Request Body:**
```json
{
  "admin_id": 1,
  "status": "compliant",
  "catatan_admin": "Dokumen bukti sah dan terverifikasi sesuai klausul."
}
```

---

### 3. Dokumen Bukti Kepatuhan (`/checklist-entries/{id}/evidences` & `/evidences`)

#### A. Riwayat Versi Bukti Kontrol
`GET /api/checklist-entries/{checklistEntryId}/evidences`
> Mengembalikan seluruh riwayat dokumen bukti (*Revisi ke-1*, *Revisi ke-2*, dst.), termasuk berkas yang pernah di-soft delete (*withTrashed*).

#### B. Unggah Versi Bukti Baru
`POST /api/checklist-entries/{checklistEntryId}/evidences`
**Content-Type:** `multipart/form-data`
- `bukti_file` (file binary, maks 10MB, format PDF/DOCX/JPG/PNG)
- `uploaded_by` (integer user ID)

#### C. Hapus Bukti (Soft Delete)
`DELETE /api/evidences/{id}`

#### D. Pulihkan Bukti (Restore)
`POST /api/evidences/{id}/restore`

---

### 4. Temuan Ketidaksesuaian (`/findings`)

- `GET /api/findings` — Daftar temuan (filter: `?status=open|in_progress|closed`, `?unit_id=`, `?kategori=major|minor|observasi`).
- `POST /api/findings` — Menerbitkan temuan baru:
  ```json
  {
    "control_id": 1,
    "unit_id": 3,
    "pic_id": 5,
    "kategori": "minor",
    "deadline": "2026-09-30",
    "catatan_admin": "Perlu perbaruan dokumen SOP penanganan insiden."
  }
  ```
- `GET /api/findings/{id}` — Detail temuan.
- `PUT /api/findings/{id}` — Update data temuan.
- `PATCH /api/findings/{id}/status` — Update status siklus temuan (`open` / `in_progress` / `closed`). Saat `closed`, tanggal verifikasi otomatis terisi `now()`.
- `DELETE /api/findings/{id}` — Hapus temuan (soft delete).

---

### 5. Register Risiko (`/risks`)

- `GET /api/risks` — Daftar risiko (filter: `?tingkat_risiko=rendah|sedang|tinggi|kritis`, `?unit_id=`).
- `POST /api/risks` — Tambah identifikasi risiko baru:
  ```json
  {
    "control_id": 53,
    "unit_id": 3,
    "pic_id": 5,
    "deskripsi_risiko": "Risiko kegagalan enkripsi database server cloud",
    "kemungkinan": 3,
    "dampak": 4,
    "rencana_mitigasi": "Implementasi AWS KMS dan rotasi kunci 90 hari"
  }
  ```
  *(Kolom `tingkat_risiko` dihitung otomatis oleh Backend: 1-5 Rendah, 6-11 Sedang, 12-19 Tinggi, 20-25 Kritis)*.
- `GET /api/risks/{id}` — Detail risiko.
- `PUT /api/risks/{id}` — Update risiko & mitigasi.
- `DELETE /api/risks/{id}` — Hapus risiko.

---

### 6. Master Data Framework & Kontrol

- `GET /api/frameworks` — Daftar standar ISO (ISO 27001:2022 & ISO 27701:2019).
- `POST /api/frameworks` — Tambah framework baru.
- `GET /api/controls` — Seluruh klausul kontrol (filter: `?framework_id=`, `?kategori=annex_a|klausul_4_10`).
- `GET /api/frameworks/{id}/controls` — Kontrol spesifik untuk framework tertentu.
- `POST /api/controls` — Tambah klausul kontrol baru.

---

### 7. Master Data Satuan Kerja / Unit (`/work-units`)

- `GET /api/work-units` — Daftar semua unit kerja / biro.
- `GET /api/work-units-tree` — Struktur hirarki organisasi induk & sub-unit kerja.
- `POST /api/work-units` — Tambah unit kerja.
