# Open issues / tech debt

A lightweight log of cross-cutting issues that don't fit a single PR.
For per-bug detail see commit history (search `BUG-NN` in messages).

## Open bugs (cosmetic / non-blocking)

- **BUG-21 — Parent Category dropdown shows stray stepper arrows on /admin/categories.**
  Below the "Parent Category" Select on the categories admin page, a vertical up/down arrow control renders unexpectedly. Likely either a numeric stepper bleeding from a misapplied input type, a duplicated chevron, or a native `<select>` size attribute. Cosmetic only — the dropdown still functions. Reproduce: navigate to /admin/categories as admin, scroll to the "Add Category" form. Screenshot in BUG-21 attachment (Johnny's WhatsApp 2026-04-26).

## Tech debt

- **TECH-DEBT-01 — Unify file upload patterns across vendor pages.**
  Three vendor pages currently hand-roll their own file upload UI: `vendor/Documents/Index.tsx`, `vendor/CategoryRequests/Create.tsx`, and (post-BUG-18 Sub-B) `components/FileUpload.tsx`. The new `FileUpload` component is opinionated for bid documents (PDF only, 5 MB, fixed `BidDocType` list). When a fourth upload pattern shows up (e.g. tender documents v2, vendor profile attachments), revisit and extract a more general `<FileUploadField>` that can be parameterised on mime, size, and doc-type list — generalising prematurely now would compromise the bid-doc ergonomics.

- **TECH-DEBT-02 — FileUploadService uses extension-based validation, not mime-sniffing.**
  `getClientOriginalExtension()` on line ~24 trusts the filename. Laravel's `mimes:pdf` request rule does real content sniffing, so this is a defense-in-depth weakness, not an exploitable hole. When making the service parameter-driven for size/mime (per POLICY-01 service signature TODO), also switch to `$file->getMimeType()`.

- **TECH-DEBT-03 — UserFactory random language_pref causes flaky test (BUG-14 assertion).**
  `UserFactory` line 28 uses `fake()->randomElement(['en', 'ar'])` for language_pref. The SetLocale middleware respects it, so any test that asserts English copy fails ~50% of the time when the factory rolls Arabic. Fix: default to 'en', add an `->arabic()` factory state for tests that explicitly want Arabic. Affects CreateTenderPublishTest > "validation error message uses user-readable label (BUG-14)" today; likely affects other locale-sensitive tests not yet caught.
