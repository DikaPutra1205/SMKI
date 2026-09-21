# Role Permissions

Matrix of permissions per role, based on the current application behavior. Each row is a
permission key (`module.action`) granted role-wise. Access control is enforced with
authenticated-route checks plus role checks in application services; this table reflects the
effective outcome of those checks.

Legend: ✓ = allowed, ✗ = not allowed

Rule: for `pic`, every ✓ grant is limited to records owned by the PIC's own unit (`users.unit_id`) or by the PIC themselves (e.g. `user.profileview`).

| Permission | superadmin | admin_kepatuhan | koordinator_smki | auditor | pic |
| --- | :-: | :-: | :-: | :-: | :-: |
| **dashboard** | | | | | |
| dashboard.read | ✓ | ✓ | ✓ | ✓ | ✓ |
| dashboard.recent-activities | ✓ | ✓ | ✓ | ✓ | ✗ |
| **checklist** | | | | | |
| checklist.view | ✓ | ✓ | ✓ | ✓ | ✓ |
| checklist.read | ✓ | ✓ | ✓ | ✓ | ✓ |
| checklist.create | ✓ | ✓ | ✗ | ✗ | ✗ |
| checklist.update | ✓ | ✓ | ✗ | ✗ | ✓ |
| checklist.verify | ✓ | ✓ | ✗ | ✗ | ✗ |
| checklist.bulk-verify | ✓ | ✓ | ✗ | ✗ | ✗ |
| checklist.delete | ✓ | ✓ | ✗ | ✗ | ✗ |
| checklist.restore | ✓ | ✓ | ✗ | ✗ | ✗ |
| checklist.generate-monthly | ✓ | ✓ | ✗ | ✗ | ✓ |
| **checklist-session** | | | | | |
| checklist-session.view | ✓ | ✓ | ✗ | ✗ | ✗ |
| checklist-session.read | ✓ | ✓ | ✓ | ✓ | ✓ |
| checklist-session.create | ✓ | ✓ | ✗ | ✗ | ✗ |
| checklist-session.update | ✓ | ✓ | ✗ | ✗ | ✓ |
| checklist-session.delete | ✓ | ✓ | ✗ | ✗ | ✗ |
| checklist-session.restore | ✓ | ✓ | ✗ | ✗ | ✗ |
| **control** | | | | | |
| control.view | ✓ | ✓ | ✗ | ✗ | ✗ |
| control.read | ✓ | ✓ | ✓ | ✓ | ✓ |
| control.create | ✓ | ✓ | ✗ | ✗ | ✗ |
| control.update | ✓ | ✓ | ✗ | ✗ | ✗ |
| control.delete | ✓ | ✓ | ✗ | ✗ | ✗ |
| control.export | ✓ | ✓ | ✗ | ✗ | ✗ |
| control.import | ✓ | ✓ | ✗ | ✗ | ✗ |
| **framework** | | | | | |
| framework.view | ✓ | ✓ | ✗ | ✗ | ✗ |
| framework.read | ✓ | ✓ | ✓ | ✓ | ✓ |
| framework.create | ✓ | ✓ | ✗ | ✗ | ✗ |
| framework.update | ✓ | ✓ | ✗ | ✗ | ✗ |
| framework.delete | ✓ | ✓ | ✗ | ✗ | ✗ |
| **evidence** | | | | | |
| evidence.read | ✓ | ✓ | ✓ | ✓ | ✓ |
| evidence.upload | ✓ | ✓ | ✗ | ✗ | ✓ |
| evidence.delete | ✓ | ✓ | ✗ | ✗ | ✓ |
| evidence.restore | ✓ | ✓ | ✗ | ✗ | ✓ |
| **finding** | | | | | |
| finding.view | ✓ | ✓ | ✓ | ✓ | ✓ |
| finding.read | ✓ | ✓ | ✓ | ✓ | ✓ |
| finding.create | ✓ | ✓ | ✗ | ✗ | ✗ |
| finding.update | ✓ | ✓ | ✗ | ✗ | ✓ |
| finding.update-status | ✓ | ✓ | ✗ | ✗ | ✓ |
| finding.delete | ✓ | ✓ | ✗ | ✗ | ✗ |
| **risk** | | | | | |
| risk.view | ✓ | ✓ | ✓ | ✓ | ✓ |
| risk.read | ✓ | ✓ | ✓ | ✓ | ✓ |
| risk.create | ✓ | ✓ | ✓ | ✗ | ✗ |
| risk.update | ✓ | ✓ | ✓ | ✗ | ✓ |
| risk.delete | ✓ | ✓ | ✓ | ✗ | ✗ |
| **work-unit** | | | | | |
| work-unit.view | ✓ | ✗ | ✗ | ✗ | ✗ |
| work-unit.read | ✓ | ✓ | ✓ | ✓ | ✓ |
| work-unit.create | ✓ | ✗ | ✗ | ✗ | ✗ |
| work-unit.update | ✓ | ✗ | ✗ | ✗ | ✗ |
| work-unit.delete | ✓ | ✗ | ✗ | ✗ | ✗ |
| **audit-log** | | | | | |
| audit-log.view | ✓ | ✓ | ✓ | ✓ | ✗ |
| audit-log.read | ✓ | ✓ | ✓ | ✓ | ✗ |
| **report** | | | | | |
| report.read | ✓ | ✓ | ✓ | ✓ | ✗ |
| report.export | ✓ | ✓ | ✓ | ✓ | ✗ |

### PDF Report Generator Access & Data Scoping Rules

The PDF Export endpoint (`GET /reports/export-pdf?type=...`) enforces granular report template authorization and work unit data scoping:

- **`superadmin`**: Full access to all report types (`quick-summary`, `executive`, `audit-ready`) across all Satuan Kerja.
- **`admin_kepatuhan`**: Access to `quick-summary` report type ONLY across all Satuan Kerja. Accessing `executive` returns 403 Forbidden.
- **`auditor`**: Access to both `quick-summary` and `executive` report types across all Satuan Kerja.
- **`koordinator_smki`**: Access to `executive` report type ONLY across all Satuan Kerja. Accessing `quick-summary` returns 403 Forbidden.
- **`pic`**: No report export access (403 Forbidden).

Available Report Types:
1. `quick-summary`: Detailed multi-page compliance progress report (with charts, domain breakdowns, high-priority controls, and detail tables).
2. `executive`: Short 1-page executive summary report.
3. `audit-ready`: Formal audit evidence report (currently placeholder 501 Not Implemented).

| **user** | | | | | |
| user.profileview | ✓ | ✓ | ✓ | ✓ | ✓ |
| user.managementview | ✓ | ✗ | ✓ | ✓ | ✗ |
| user.read | ✓ | ✗ | ✓ | ✓ | ✗ |
| user.create | ✓ | ✗ | ✗ | ✗ | ✗ |
| user.update | ✓ | ✗ | ✗ | ✗ | ✗ |
| user.delete | ✓ | ✗ | ✗ | ✗ | ✗ |
| **role** | | | | | |
| role.managementview | ✓ | ✗ | ✗ | ✗ | ✗ |
| role.create | ✓ | ✗ | ✗ | ✗ | ✗ |
| role.update | ✓ | ✗ | ✗ | ✗ | ✗ |
| role.delete | ✓ | ✗ | ✗ | ✗ | ✗ |

## Manajemen Sesi Checklist (Admin screen)

Route: `GET /admin/kepatuhan/sessions` (menu item "Manajemen Sesi Checklist", gated by
`checklist-session.view`). This screen is the manual/backup UI for generating and managing
`checklist_sessions` + their `checklist_entries` — previously only reachable via the
`smki:generate-monthly-checklist` artisan command.

- **superadmin, admin_kepatuhan** — full CRUD. Create ("Buat Sesi") auto-generates the session
  by `(unit_id, framework_id, periode)` and seeds one `ChecklistEntry` per `Control` with
  `pic_id = null` (mirrors the artisan command). Edit/Delete/Restore also available.
- **koordinator_smki, auditor** — no access to this screen (no `checklist-session.view`);
  they view checklist entries read-only via "Verifikasi Checklists"
  (`GET /admin/kepatuhan/checklist/verify`).
- **pic** — no access to this screen (no `checklist-session.view`); PICs keep their own
  `/checklist` data via `checklist-session.read`/`update`.

Mutation routes: `admin.kepatuhan.checklist-sessions.{store,update,destroy,restore}`
(`Web\ChecklistSessionController`). `store` = `generate()` (command-mirroring). The PIC create
flow remains at `admin.pic.checklist.store` (`Web\ChecklistSessionController::store`).
