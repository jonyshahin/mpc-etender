# Database Reference

## Conventions

- All tables use UUID primary keys (`HasUuids` trait).
- All tables have `created_at` and `updated_at`, except `audit_logs`, `document_access_logs`, `activity_logs` (`created_at` only — append-only) and `system_settings` (`updated_at` only).
- Status/type columns are stored as `string(30)`; the matching PHP enum in `app/Enums/` is the source of truth.
- See `docs/DATA_DICTIONARY.md` for full column specifications.

## Tables (35)

| # | Table | Purpose |
|---|-------|---------|
| 1 | `roles` | Named role assignments for MPC users (RBAC) |
| 2 | `permissions` | Granular capability flags grouped by module |
| 3 | `role_permissions` | Many-to-many pivot binding permissions to roles |
| 4 | `users` | MPC internal staff accounts with role-based access and 2FA |
| 5 | `projects` | Construction projects under which tenders are issued |
| 6 | `user_project` | Pivot assigning users to projects with a project-scoped role |
| 7 | `categories` | Hierarchical work-category taxonomy for tenders and vendors |
| 8 | `vendors` | External supplier accounts (separate auth guard) |
| 9 | `vendor_documents` | Prequalification documents uploaded by vendors with review status |
| 10 | `vendor_categories` | Pivot mapping vendors to qualified categories |
| 11 | `tenders` | Tender records with deadlines and envelope configuration |
| 12 | `tender_categories` | Pivot mapping tenders to work categories |
| 13 | `tender_documents` | Specifications, drawings, and attachments with versioning |
| 14 | `boq_sections` | Top-level sections of a tender's bill of quantities |
| 15 | `boq_items` | Priced line items belonging to a BOQ section |
| 16 | `addenda` | Official tender amendments issued after publication |
| 17 | `clarifications` | Vendor questions and MPC answers attached to a tender |
| 18 | `bids` | Vendor submissions; pricing encrypted at rest until opening |
| 19 | `bid_boq_prices` | Per-line-item unit and total prices in a bid |
| 20 | `bid_documents` | Technical and financial attachments uploaded with a bid |
| 21 | `evaluation_criteria` | Weighted scoring criteria per tender, grouped by envelope |
| 22 | `evaluation_committees` | Groups of evaluators assigned to score a tender |
| 23 | `committee_members` | Pivot of users on an evaluation committee with a role |
| 24 | `evaluation_scores` | Individual evaluator scores for a bid against a criterion |
| 25 | `evaluation_reports` | Aggregated ranking and recommendation from committee scores |
| 26 | `approval_requests` | Requests for management approval against an evaluation report |
| 27 | `approval_decisions` | Decisions recorded by approvers |
| 28 | `awards` | Contract award linking a winning bid to a vendor |
| 29 | `notifications` | Persisted in-app/multichannel notifications |
| 30 | `notification_logs` | Per-channel delivery records and retry state |
| 31 | `notification_templates` | Bilingual templates per channel and notification type |
| 32 | `audit_logs` | Append-only audit trail (no update/delete) |
| 33 | `document_access_logs` | Append-only log of document views/downloads/prints |
| 34 | `activity_logs` | Lightweight per-actor activity feed for dashboards |
| 35 | `system_settings` | Key/value system configuration grouped by domain |

## Supporting tables (from starter kit / packages)

- `password_reset_tokens` — password reset flow (created in users migration)
- `sessions` — session storage (created in users migration)
- `cache` / `cache_locks` — cache backend tables
- `jobs` / `job_batches` / `failed_jobs` — queue backend tables
- `pulse_*` — Laravel Pulse monitoring tables
