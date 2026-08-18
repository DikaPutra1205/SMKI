# 🗄️ Skema Basis Data (Database Schema) — SMKI Backend

Sistem Kepatuhan Digital SMKI menggunakan basis data relasional **PostgreSQL (Supabase)** dengan 10 tabel inti.

---

## 📊 Diagram Entitas Relasi (ERD)

```mermaid
erDiagram
    frameworks ||--o{ controls : "memiliki klausul"
    frameworks ||--o{ checklist_sessions : "acuan standar sesi"
    work_units ||--o{ work_units : "parent-child"
    work_units ||--o{ users : "tempat penugasan"
    work_units ||--o{ checklist_sessions : "unit sasaran audit"
    users ||--o{ checklist_sessions : "dibuat oleh / auditor"
    checklist_sessions ||--o{ checklist_entries : "memiliki item evaluasi"
    controls ||--o{ checklist_entries : "dievaluasi"
    work_units ||--o{ checklist_entries : "satker pemilik"
    users ||--o{ checklist_entries : "pic input"
    users ||--o{ checklist_entries : "admin verifikator"
    checklist_entries ||--o{ compliance_evidences : "memiliki versi bukti"
    users ||--o{ compliance_evidences : "diunggah oleh"
    controls ||--o{ findings : "terkait klausul"
    work_units ||--o{ findings : "satker temuan"
    controls ||--o{ risks : "mitigasi kontrol"
    work_units ||--o{ risks : "satker risiko"
    audit_logs ||--o{ users : "pelaku perubahan"
```

---

## 📋 Rincian 10 Tabel Inti

### 1. `frameworks` (Standar SMKI)
- `id` (PK, BigInt)
- `nama` (String, e.g. `ISO/IEC 27001`, `ISO/IEC 27701`)
- `versi` (String, e.g. `2022`, `2019`)
- `url_file` (String, nullable)
- `created_at`, `updated_at`, `deleted_at`

### 2. `controls` (Klausul & Kontrol Standar)
- `id` (PK, BigInt)
- `framework_id` (FK -> `frameworks.id`)
- `kode_klausul` (String, e.g. `4.1`, `A.5.1`, `7.2.1`)
- `judul` (String)
- `deskripsi` (Text, nullable)
- `kategori` (String: `klausul_4_10`, `annex_a`)
- **Index Unik:** `(framework_id, kode_klausul)`

### 3. `work_units` (Satuan Kerja / Biro Organisasi)
- `id` (PK, BigInt)
- `parent_id` (FK -> `work_units.id`, nullable untuk Root)
- `nama` (String, e.g. `Biro Teknologi Informasi`, `Biro Hukum`)
- `created_at`, `updated_at`, `deleted_at`

### 4. `users` (Pengguna & Hak Akses Peran)
- `id` (PK, BigInt)
- `name` (String)
- `email` (String, Unique)
- `password` (String, Hash)
- `role` (Enum: `superadmin`, `admin_kepatuhan`, `koordinator_smki`, `auditor`, `pic`)
- `unit_id` (FK -> `work_units.id`, nullable)

### 5. `checklist_sessions` (Sesi Audit & Self-Assessment Formal)
- `id` (PK, BigInt)
- `nama_sesi` (String, e.g. `Audit Internal SMKI Semester 1 2026`)
- `periode` (String, e.g. `Q1 2025`, `Semester 1 2026`, `2025`)
- `konteks_penilaian` (Text, nullable, e.g. `Penilaian mandiri unit kerja dan lingkup layanan cloud`)
- `unit_id` (FK -> `work_units.id`)
- `framework_id` (FK -> `frameworks.id`, nullable)
- `created_by` (FK -> `users.id`, nullable)
- `auditor_id` (FK -> `users.id`, nullable)
- `start_date` (Date, nullable)
- `end_date` (Date, nullable)
- `status` (Enum: `draft`, `in_progress`, `submitted`, `verified`, `closed`)
- `catatan` (Text, nullable)
- `created_at`, `updated_at`, `deleted_at`
- **Index Performa:** `(unit_id, status)`, `(unit_id, periode)`, `(framework_id)`, `(start_date, end_date)`

### 6. `checklist_entries` (Lembar Evaluasi Kepatuhan Klausul)
- `id` (PK, BigInt)
- `session_id` (FK -> `checklist_sessions.id`, nullable untuk backward compatibility)
- `control_id` (FK -> `controls.id`)
- `unit_id` (FK -> `work_units.id`)
- `pic_id` (FK -> `users.id`)
- `admin_id` (FK -> `users.id`, nullable)
- `status` (Enum: `compliant`, `partial`, `non_compliant`, `na`)
- `catatan` (Text, catatan PIC)
- `catatan_admin` (Text, catatan verifikator Admin)
- `tanggal_input` (Timestamp)
- `tanggal_verifikasi` (Timestamp, nullable)
- **Index Performa:** `(session_id, control_id)`, `(session_id, status)`, `(unit_id, status)`, `(unit_id, control_id)`, `(tanggal_input)`

### 7. `compliance_evidences` (Berkas Dokumen Bukti & Multi-Versi)
- `id` (PK, BigInt)
- `checklist_entry_id` (FK -> `checklist_entries.id`)
- `uploaded_by` (FK -> `users.id`)
- `file_url` (String, path Supabase Storage S3)
- `version_number` (Integer: 1, 2, 3...)
- `is_active` (Boolean: true untuk versi terkini)
- `uploaded_at` (Timestamp)
- `deleted_at` (Timestamp, soft delete)

### 8. `findings` (Temuan Audit / Ketidaksesuaian)
- `id` (PK, BigInt)
- `control_id` (FK -> `controls.id`)
- `unit_id` (FK -> `work_units.id`)
- `pic_id` (FK -> `users.id`)
- `admin_id` (FK -> `users.id`, nullable)
- `kategori` (Enum: `major`, `minor`, `observasi`)
- `status` (Enum: `open`, `in_progress`, `closed`)
- `deadline` (Date, nullable)
- `catatan_admin` (Text, nullable)
- `tanggal_verifikasi` (Timestamp, nullable)

### 9. `risks` (Register Risiko Keamanan Informasi)
- `id` (PK, BigInt)
- `control_id` (FK -> `controls.id`)
- `unit_id` (FK -> `work_units.id`)
- `pic_id` (FK -> `users.id`)
- `deskripsi_risiko` (Text)
- `kemungkinan` (Integer, skala 1-5)
- `dampak` (Integer, skala 1-5)
- `tingkat_risiko` (Enum: `rendah`, `sedang`, `tinggi`, `kritis`)
- `rencana_mitigasi` (Text, nullable)

### 10. `audit_logs` (Jejak Rekam Digital Sistem - Anti Tamper)
- `id` (PK, BigInt)
- `user_id` (FK -> `users.id`, nullable)
- `action` (String: `created`, `updated`, `deleted`, `restored`)
- `auditable_type` (String, Polymorphic model class)
- `auditable_id` (BigInt, ID record model)
- `old_values` (JSON, nullable)
- `new_values` (JSON, nullable)
- `ip_address` (String, nullable)
- `user_agent` (String, nullable)
- `created_at` (Timestamp)
