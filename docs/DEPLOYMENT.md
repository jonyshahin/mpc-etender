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

## Exporting vendors and their documents

`php artisan mpc:export-vendors` packs every vendor and their prequalification
documents into a zip and prints a short-lived download link to it:

```
vendors-export-YYYYMMDD-HHMMSS.zip
├── vendors.csv          one row per vendor (contact details, status, categories, document count)
├── documents.csv        one row per document: the zip and file it went to, or why not
├── Acme Steel/
│   ├── Trade License - Licence 2026.pdf
│   └── Insurance - Liability cover.pdf
└── Babylon Concrete/    every vendor gets a folder, even with no documents
```

Only prequalification documents (`vendor_documents`) are included — not bid
documents and not category-request evidence. The CSVs are UTF-8 with a BOM so
Excel shows Arabic correctly, and their times are in `MPC_TIMEZONE`
(Asia/Baghdad), as the app shows them.

| Flag | Effect |
|------|--------|
| `--by=email` | **Required.** The MPC user taking the export. They must be able to open vendor documents in the app today: active, an `admin` or `super_admin` role, a verified email and `vendors.view`. Every exported file is written to `document_access_logs` (action `exported`) and every vendor gets a `vendor_exported` audit row against this user, both marked as coming from `artisan mpc:export-vendors` |
| `--dry-run` | Print how many vendors and documents there are, their recorded size, how many zip parts that makes and the local disk it needs; write and record nothing |
| `--part-size=250` | Largest size of each zip, in MB. A bigger export is split into `…-part1.zip`, `…-part2.zip`, …; extract them all into one folder. A vendor is kept in one part unless it alone is bigger than the limit |
| `--expires=30` | Minutes the download links stay valid (1–1440) |
| `--clean` | Delete the export from storage and exit |

```bash
# 1. See what it will contain, and check "Local disk needed" against "Local disk free".
php artisan mpc:export-vendors --by=you@mpc-group.com --dry-run

# 2. Export, then open the printed link(s) within their lifetime.
php artisan mpc:export-vendors --by=you@mpc-group.com

# 3. Once downloaded, remove the zips from the bucket.
php artisan mpc:export-vendors --clean
```

- **It runs on a live web instance, whose local disk is small** (512 MB per GB
  of RAM on Laravel Cloud). Each part is streamed to local disk, uploaded and
  deleted before the next starts, so the disk needed is about one part (or the
  whole export, if smaller) plus two of the largest document — not the whole
  export. The command refuses to start when the instance has less than that
  free; lower `--part-size` and run it again.
- **If Cloud stops it at the 30-minute limit**, nothing is logged and no link is
  printed. Partly uploaded zips may remain in the bucket and a work folder on
  the instance; the next run or `--clean` removes both. The run's lock expires
  35 minutes after it started, so until then a new run (or `--clean`) reports
  that another export is running.
- **The zips stay in the bucket until you remove them.** They sit under
  `exports/vendors/` in the private bucket; the links expiring does not delete
  them. Each run replaces the previous export, and `--clean` removes it.
- **A document whose file is gone from storage does not stop the export.** It is
  listed as `missing` in `documents.csv`, named in the command's output, and not
  logged as exported. A file that is there but **cannot be read** (permissions,
  network) is listed as `read error` instead and makes the command exit
  non-zero — run it again. A document the vendor deletes while the export runs
  is listed as `deleted during export`.
- **Logging is all or nothing.** The access and audit rows are written in one
  transaction, only once every zip is uploaded. If anything fails before that,
  the zips already uploaded are removed again; if they cannot be, the command
  names them and says to run `--clean`.
- **Extract with Windows Explorer, `Expand-Archive` or 7-Zip.** Windows'
  built-in `tar.exe` skips any file or folder whose name is not plain English
  (Arabic company names, for instance) — a limitation of that tool, not of the
  zip.

## Upload limits

POLICY-01 caps user-uploaded documents at `App\Rules\PdfFile::MAX_KB` — **100 MB**,
PDF only. That constant is the single source of truth: the FormRequests read it,
`FileUploadService` enforces it, `app.blade.php` injects it as
`window.__maxUploadBytes__` for the browser-side checks, and every "up to :size"
string is filled from it. Raising the cap means editing that one constant.

**The application rule is only ever as generous as the PHP settings underneath
it.** PHP rejects an oversized request before Laravel is reached, so a 100 MB
rule on top of a 2 MB `upload_max_filesize` accepts nothing.

| setting | needs to be | why |
| --- | --- | --- |
| `upload_max_filesize` | ≥ 100M | per-file ceiling |
| `post_max_size` | > `upload_max_filesize` | applies to the **sum** of files in one request — the tender wizard posts several documents together, plus the form fields around them |
| `max_execution_time` | generous | a 100 MB body streams to S3 inside the request |

`public/.user.ini` sets these for PHP-FPM and CGI. Other SAPIs ignore it, and it
cannot touch anything in front of PHP:

- **nginx** defaults `client_max_body_size` to 1 MB and returns its own 413.
- **Laravel Cloud** applies its own request-body limit; set the PHP values in the
  environment's settings rather than relying on `.user.ini` alone.
- **Cloudflare** caps request bodies by plan (100 MB on Free/Pro). A 100 MB PDF
  plus multipart overhead sits right on that boundary.

Verify with a real upload of a near-limit file rather than assuming — each layer
fails differently and only the last one produces a message the user can read.

When `post_max_size` is exceeded, PHP discards the body before any validation
rule can see it, so the failure surfaces as a bare 413 rather than a field
error. `bootstrap/app.php` renders `PostTooLargeException` as the same
"file exceeds :size" message the rule would have given, falling back to a plain
413 body when the session has not started (`ValidatePostSize` runs ahead of it in
the global middleware stack).

BOQ template imports (xlsx/csv) are capped separately at 5 MB in `BoqController`
and deliberately bypass POLICY-01 — those are data imports, not document storage.
