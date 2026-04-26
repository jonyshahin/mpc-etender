# Open issues / tech debt

Local development: see [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) — the
Docker stack repros wizard-flow bugs (BUG-15, BUG-22) without deploy
round-trips.

A lightweight log of cross-cutting issues that don't fit a single PR.
For per-bug detail see commit history (search `BUG-NN` in messages).

## Open bugs

- **BUG-22 — Tender wizard silently drops invalid documents on publish.**
  Repro: as admin, run the tender create wizard. In the documents step, attach a non-PDF file (e.g. .jpg). Click Save & Publish. The tender publishes successfully, but the document does NOT upload — silently dropped, no validation error toast, no warning. POLICY-01 enforces PDF-only at the FormRequest layer, so the upload validation IS firing — but the wizard's submit handler treats partial-success as full-success and proceeds. Same class as BUG-15 (wizard publish silent failure on aggregate validation). Likely the wizard POSTs documents in a separate sub-request after tender creation, and that sub-request's validation rejection isn't caught by the wizard's promise chain or useForm error handler. High priority — the whole POLICY-01 enforcement story is undermined if the wizard silently bypasses it.

- **BUG-24 — audit_logs schema inadequate for MySQL strict mode (HIGH severity).**
  6 tests pass under sqlite but fail under MySQL strict mode with `SQLSTATE[22001]: Data too long for column 'auditable_id'`. The audit log is the project's "immutable 7-year record" per the project plan; silent column truncation in production would be a compliance hole. Sqlite tolerates oversize values, MySQL truncates — meaning local tests have been hiding this since the audit_logs table was introduced.
  Action items:
  1. Run on prod: `SELECT COUNT(*) FROM audit_logs WHERE LENGTH(auditable_id) >= 250` — see how often we've been at the truncation boundary.
  2. Audit which fields could exceed 255 chars (UUIDs are 36 — well under — but composite keys, JSON-encoded payloads, or URL-style paths could blow it).
  3. Widen `auditable_id` and any other narrow columns to TEXT or VARCHAR(1024) via forward-compat migration.
  4. While we're in the table: audit `auditable_type`, `action`, `ip_address`, `user_agent` for similar issues.
  5. Add a test that explicitly creates a long auditable_id under MySQL to prove the fix and prevent regression.

- **BUG-23 — Addenda tab on admin tender detail page has no "Issue Addendum" button or upload zone.**
  Repro: as admin, navigate to a Published, non-cancelled, non-past-deadline tender's detail page (e.g. MBP-T006 "Two Envelope Test Bug 18"). Click the Addenda tab. Result: empty state "No addenda have been issued for this tender." with no button or affordance to issue one. There is no way to upload an addendum from the UI even though StoreAddendumRequest, the route, and the file-upload pattern all exist on the backend (verified via POLICY-01 audit which swapped the addendum FormRequest to PdfFile rule). Likely a missing UI control on resources/js/pages/tender/Show.tsx in the Addenda tab section — either the "Issue Addendum" button was never rendered, was removed, or is gated by a permission check that's wrong. Backend works, frontend doesn't expose it. Medium-high priority — addenda are a procurement requirement (clarifications + corrections to published tenders pre-deadline), and this gap forces admins to bypass the system entirely.

- **BUG-25 — Vendor portal UI strings not translated when locale=ar (translation key coverage gap).**
  Reproduced on Docker stack 2026-04-26: Fatima (language_pref=ar) logs into /vendor/login. RTL layout flips correctly (sidebar moves to right, Arabic locale toggle visible at bottom-right, dir=rtl applied) — confirming locale resolution and layout direction both work. However, all UI strings stay in English: "Welcome, Erbil MEP Solutions", "Open Tenders", sidebar items (Dashboard, My Bids, Notifications, Profile, Documents, Categories, Category Requests), section headers (Recent Bids, Prequalification Status), and empty states (No bids have been submitted). This is NOT a layout/RTL bug — it's a translation key coverage gap on the vendor portal. Sibling effort to the partial i18n work shipped in commit 37482d6 (toast notifications only). Severity: medium-high — vendor portal is the supplier-facing surface, and Arabic-first vendors will see a half-translated UI.

  Action items:
  1. Audit `resources/js/pages/vendor/**` and `resources/js/layouts/vendor/**` for hardcoded English strings (look for JSX text content not wrapped in `t('...')`).
  2. Cross-check `lang/en.json` vs `lang/ar.json` for missing keys in the `vendor.*` and `dashboard.*` namespaces.
  3. Apply Strategy A pattern (DB stores slug, React renders via `t('scope.${key}.label')`) consistently. The screenshot shows tender titles are also English-only, but those are seeded fixture descriptions (not real tender content) — that's a seeder choice, not a translation bug.
  4. Add a Pest test that switches a user's `language_pref` to `'ar'` and asserts at least one Arabic-specific string appears on `/vendor/dashboard`, to catch regressions.

  Note: this work is part of a broader i18n sweep that was previously scoped under a prompt named `PROMPT_I18N_COMPLETE_TRANSLATION.md` (referenced in `MPC_eTender_Release_Notes_v2_0.docx` but not yet checked into the repo). When tackling BUG-25, lift that prompt's scope into a project doc (e.g. `docs/I18N_SWEEP_PLAN.md`) so it survives outside Word docs.

- **BUG-26 — Addendum deadline extension does not cascade to opening date.**
  High severity. On tender `MBP-T008` (production verification, April 27, 2026), issuing an addendum with "Extend Deadline" checked updates `submission_deadline` but leaves `opening_date` untouched, producing an invalid state where `submission_deadline > opening_date` — bids cannot be opened until after they are submitted. Blocks Step 7 of the test plan (vendor bid submission against `MBP-T008` will hit this state). Action items:

  1. In `AddendumController::store` (and the corresponding service/action), when `extend_deadline` is checked recalculate `opening_date` so it remains strictly after the new submission deadline.
  2. Add a configurable `min_hours_between_deadline_and_opening` setting (default 24h) and enforce it in `AddendumRequest` and `UpdateTenderRequest`.
  3. In the "Issue Addendum" form, when "Extend Deadline" is checked expose a second field for the new opening date, pre-filled with `new_deadline + buffer`.
  4. Backfill check: Tinker one-off to identify any existing tenders where `submission_deadline >= opening_date` and flag them for procurement officer review.
  5. Pest tests: (a) addendum extending deadline keeps opening date strictly after the new deadline; (b) validation error when `opening_date <= submission_deadline`.

- **BUG-27 — Admin dashboard is empty; scope decision pending.**
  Medium severity. `/admin/dashboard` currently has no content. Need a scope decision before implementation. Proposed split to avoid overlap with `/dashboard` (personal) and `/dashboard/portfolio` (cross-project KPIs):

  - `/admin/dashboard` → system health & governance: total users by role, active vs suspended vendors, pending vendor prequalifications queue, recent audit log entries (last 50), failed jobs from Horizon, queue depth, storage usage, recent role/permission changes.

  Pragmatic Phase 1 alternative: embed the portfolio view in the admin dashboard for now and split when system-health metrics are ready. Sponsor / PM to confirm direction before development begins. Estimated effort 2–3 days once scope is locked.

- **BUG-28 — Verify 2FA implementation end-to-end.**
  Medium severity. Admin → Settings shows "2FA mandatory for internal users" toggle as enabled, but no internal user has been forced through 2FA enrollment during testing — the toggle may be cosmetic. Investigation checklist:

  1. Confirm `users` table has `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` columns.
  2. Confirm Laravel Fortify is installed and `Features::twoFactorAuthentication()` is enabled in `config/fortify.php`.
  3. Confirm an `EnsureTwoFactorAuthenticated` middleware (or equivalent) is registered on internal routes.
  4. Locate the user-facing enrollment flow (QR code page, recovery codes display, confirmation step).
  5. Verify the Settings toggle actually drives enforcement rather than being decorative.
  6. Confirm scope is correctly limited to internal users only — vendor portal must be unaffected.
  7. Add Pest tests covering enrollment and login challenge flows.

  Acceptance criteria: when toggle is ON, internal users without 2FA configured are redirected to enrollment on next login and cannot access the dashboard until enrolled. When OFF, 2FA is optional but available. Vendor portal unaffected by the toggle in either case.

## Open bugs (cosmetic / non-blocking)

- **BUG-21 — Parent Category dropdown shows stray stepper arrows on /admin/categories.**
  Below the "Parent Category" Select on the categories admin page, a vertical up/down arrow control renders unexpectedly. Likely either a numeric stepper bleeding from a misapplied input type, a duplicated chevron, or a native `<select>` size attribute. Cosmetic only — the dropdown still functions. Reproduce: navigate to /admin/categories as admin, scroll to the "Add Category" form. Screenshot in BUG-21 attachment (Johnny's WhatsApp 2026-04-26).

- **BUG-29 — Vendor document type labels showing raw i18n keys.**
  Severity: low. Same class as BUG-03. On `/vendor/documents`, the Document Type dropdown renders raw keys (`vendor.doc_type_trade_license`, `vendor.doc_type_tax_certificate`, `vendor.doc_type_insurance`, `vendor.doc_type_financial_statement`, `vendor.doc_type_bank_reference`, `vendor.doc_type_experience_certificate`, `vendor.doc_type_iso_certificate`, `vendor.doc_type_other`) instead of resolved labels. Apply Strategy A: confirm DB stores slugs (`trade_license`, `tax_certificate`, …), render via ``t(`vendor.doc_type.${slug}.label`)`` in React, add 8 keys to each of `lang/en.json` and `lang/ar.json`, dev-mode `console.warn` on missing keys per existing convention. Worth batching with BUG-03 and any other DB-seeded enum-like values (category names, role names, status labels) into one Strategy A cleanup commit.

## Tech debt

- **TECH-DEBT-01 — Unify file upload patterns across vendor pages.**
  Three vendor pages currently hand-roll their own file upload UI: `vendor/Documents/Index.tsx`, `vendor/CategoryRequests/Create.tsx`, and (post-BUG-18 Sub-B) `components/FileUpload.tsx`. The new `FileUpload` component is opinionated for bid documents (PDF only, 5 MB, fixed `BidDocType` list). When a fourth upload pattern shows up (e.g. tender documents v2, vendor profile attachments), revisit and extract a more general `<FileUploadField>` that can be parameterised on mime, size, and doc-type list — generalising prematurely now would compromise the bid-doc ergonomics.

- **TECH-DEBT-02 — FileUploadService uses extension-based validation, not mime-sniffing.**
  `getClientOriginalExtension()` on line ~24 trusts the filename. Laravel's `mimes:pdf` request rule does real content sniffing, so this is a defense-in-depth weakness, not an exploitable hole. When making the service parameter-driven for size/mime (per POLICY-01 service signature TODO), also switch to `$file->getMimeType()`.

- **TECH-DEBT-03 — UserFactory random language_pref causes flaky test (BUG-14 assertion).**
  `UserFactory` line 28 uses `fake()->randomElement(['en', 'ar'])` for language_pref. The SetLocale middleware respects it, so any test that asserts English copy fails ~50% of the time when the factory rolls Arabic. Fix: default to 'en', add an `->arabic()` factory state for tests that explicitly want Arabic. Affects CreateTenderPublishTest > "validation error message uses user-readable label (BUG-14)" today; likely affects other locale-sensitive tests not yet caught.

- **TECH-DEBT-04 — phpunit.xml `<env>` blocks lack `force="true"`.**
  Without `force="true"`, phpunit's env overrides only apply when the named env var is unset — they DON'T override values already in `.env`. Inside the Docker stack `.env` sets `DB_CONNECTION=mysql`, so `./vendor/bin/pest` runs against MySQL instead of the intended sqlite `:memory:`. The Makefile's `make test` target works around it via explicit `-e DB_CONNECTION=sqlite`. Fix: add `force="true"` to all `<env>` blocks in phpunit.xml. Note: this only fixes the *config drift*; the underlying MySQL strict-mode failures it exposes are filed separately as BUG-24.

- **TECH-DEBT-05 — App image is 3.61 GB (Sail Ubuntu base).**
  Could shrink to ~400 MB with a custom `docker/app/Dockerfile` based on `php:8.4-fpm-alpine` + multi-stage Composer/Node install. Not blocking daily dev — only matters when rebuilding from scratch (e.g. after `composer require`). Defer until rebuild times become annoying. The Sail-based approach was chosen for speed-to-prod-parity, not image minimalism — see docs/DEVELOPMENT.md.

- **TECH-DEBT-06 — File uploads aren't transaction-aware: rolled-back creates leave S3 orphans.**
  `TenderService::create` wraps DB writes in `DB::transaction`, but `FileUploadService::upload` writes to S3 synchronously and returns the path before the transaction commits. If a later step in the same transaction throws (e.g. a publish-prereq exception), the DB rolls back cleanly but the uploaded files persist in S3 as orphans with no DB row pointing at them. Surfaced by BUG-22 fix: after adding documents to the canonical `tenderPayload` fixture, T-C-02 and T-C-34 (which assert "no S3 files after rollback") started failing because docs upload before the publish-prereq throw. Worked around by overriding `documents => []` in those two tests. Real fix: either (a) defer S3 writes to after-commit hooks, or (b) collect uploaded paths during the transaction and `Storage::delete()` them in the catch block. (b) is simpler; (a) is more robust. Severity: low (orphans waste storage but don't corrupt data) — bump if storage costs become noticeable.

- **TECH-DEBT-07 — Vite production build fails inside the app container due to native binary mismatch.**
  `docker compose exec app npm run build` fails with "native rolldown binary mismatch" because `node_modules` was populated by `npm install` running on the Windows host — meaning the binaries inside `node_modules/@rollup/...` are Windows-native and the Linux container can't execute them. Workaround for this PR: run `npm run build` on the host. Real fix: either (a) add `node_modules` to the volumes-not-mounted list in compose.yaml so the container has its own Linux-native install, or (b) add a `make npm-install` target that always runs `npm install` inside the container, documented as a first-time-setup step in DEVELOPMENT.md. Severity: medium — currently breaks the dev-stack-parity story (we wanted local builds to match prod builds; right now they don't). Surfaced during BUG-22 verification.

- **TECH-DEBT-08 — Tender Shortlist tab and workflow.**
  New feature in M-04 Evaluation Engine domain. Add a "Shortlist" tab to the tender detail page (alongside Overview, BOQ, Documents, Addenda, Clarifications, Evaluation) for managing post-evaluation shortlists before final award. Scope definition required before implementation. Open design questions:

  1. Auto-populated from evaluation results (top N by score) or manually curated by the committee?
  2. Does shortlisting trigger a separate notification flow to shortlisted vendors (WhatsApp + Email per existing matrix)?
  3. Is there a BAFO (Best And Final Offer) round between shortlisting and award, with its own bid revision workflow?
  4. For two-envelope tenders: does shortlisting happen after technical pass and before financial envelope opening?
  5. Permissions: confirm view/edit scope (likely evaluation committee + procurement head).
  6. Does shortlist status feed into vendor performance scoring for future tenders (Phase 2 supplier performance module)?

  Likely data model: new `tender_shortlists` table linking `tender_id` → `bid_id` with status enum (`shortlisted`, `bafo_invited`, `awarded`, `rejected`); audit log entries on every shortlist change; `ShortlistPolicy` for view/create/update permissions. Dependencies: M-04 complete and tested; M-05 notification engine extension for shortlist events. Estimated effort: ~1 sprint (10 working days) once scope is locked.
