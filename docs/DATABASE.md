# Database Reference

> Populated during Sprint 0 Step 11 from migration headers.

## Conventions

- All tables use UUID primary keys (`HasUuids` trait).
- All tables have `created_at` and `updated_at`, except `audit_logs` and `document_access_logs` (`created_at` only — append-only).
- Status/type columns are stored as `string(30)`; the matching PHP enum in `app/Enums/` is the source of truth.

## Tables

_TBD — to be filled in after Step 6 (migrations) and Step 11 (docs update)._
