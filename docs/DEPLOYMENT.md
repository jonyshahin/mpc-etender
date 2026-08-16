# Deployment

> Target: Laravel Cloud.

## Sections

- Environment variables
- Worker / queue configuration (Horizon)
- Reverb WebSocket service
- Post-deploy checklist

_TBD._

## Going live: clearing demo data

`php artisan mpc:reset-data` removes every transactional record so real vendor
data can be entered against a clean system, while preserving the reference data
the application needs to function.

| Kept | Removed |
|------|---------|
| `roles`, `permissions`, `role_permissions` | vendors + their documents, categories and change requests |
| `categories` | tenders, BOQ sections/items, tender documents, addenda, clarifications |
| `system_settings` | bids, bid pricing, bid documents |
| `notification_templates` | evaluation criteria, committees, scores, reports |
| `users` (all internal MPC staff) | approval requests and decisions, awards |
| `projects` (unless `--with-projects`) | notifications, notification logs, audit and access logs |

### Options

| Flag | Effect |
|------|--------|
| `--dry-run` | Print the row counts that would be deleted; change nothing |
| `--with-files` | Also delete the uploaded files on S3 behind the removed rows |
| `--with-projects` | Also delete `projects` and `user_project` assignments |
| `--keep-audit` | Retain `audit_logs` and `document_access_logs` |
| `--reseed` | Re-run the reference seeders — **overwrites** tuned settings, edited category names and custom role grants with their defaults. Prompts first. Only use when the reference tables are empty |
| `--force` | Skip the confirmation prompt — **required on Laravel Cloud** |

`--force` is mandatory in production: the command uses Laravel's
`ConfirmableTrait`, which refuses to run unattended in a production environment
without it.

### Recommended sequence

```bash
# 1. Preview — always do this first.
php artisan mpc:reset-data --dry-run

# 2. Take a database snapshot in the Laravel Cloud dashboard. There is no undo.

# 3. Apply, including the orphaned S3 objects.
php artisan mpc:reset-data --with-files --force
```

The command deletes inside a transaction, in an order that satisfies every
foreign key with constraint checking left on, then verifies that the target
tables are empty and the reference tables are not. It exits non-zero if either
check fails.

### Before entering real data

- **Change the seeded admin password.** `AdminUserSeeder` creates
  `admin@mpc-group.com` with the literal password `password`. Rotate it before
  the system holds real procurement data.
- **Confirm notification templates exist.** `NotificationService` resolves its
  send channels from `notification_templates`; with an empty table the channel
  list is empty and **no email, WhatsApp or SMS is sent at all** — silently,
  with no error. `php artisan db:seed` covers this, and `mpc:reset-data` warns
  if the table is empty when it finishes.
- **If you use `--with-projects`, reassign users before creating tenders.**
  `Tender\TenderController::index` scopes the tender list to the projects a user
  is assigned to via the `user_project` pivot. With no assignments, every tender
  is invisible to every user — super admin included — and nothing reports an
  error. Create the project and assign users under `/admin/projects` first. The
  command prints this warning when the flag is used.
- **`--with-files` clears referenced objects, not the whole bucket.** It reads
  every `file_path` / `path` / `letter_file_path` value *before* deleting rows
  and removes exactly those objects, so nothing a live record needs is ever
  touched. It cannot find objects that were already orphaned — uploads whose
  transaction rolled back (TECH-DEBT-06), addendum attachments and
  category-request evidence, neither of which any code path deletes today.
  Those sit under `vendors/`, `tenders/`, `vendor-category-requests/`,
  `reports/` and `bid-docs/`. If you want a genuinely empty bucket, sweep those
  prefixes manually after the reset — once it has run, no database row
  references anything under them.
- **`--keep-audit` leaves dangling references.** Audit rows are polymorphic
  (`auditable_type` / `auditable_id`) with no foreign key, so retained rows will
  point at tenders and bids that no longer exist. The `vendor_id` column is
  nulled automatically. Keep them for compliance history, not for traceability.
