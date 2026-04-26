# Open issues / tech debt

A lightweight log of cross-cutting issues that don't fit a single PR.
For per-bug detail see commit history (search `BUG-NN` in messages).

## Tech debt

- **TECH-DEBT-01 — Unify file upload patterns across vendor pages.**
  Three vendor pages currently hand-roll their own file upload UI: `vendor/Documents/Index.tsx`, `vendor/CategoryRequests/Create.tsx`, and (post-BUG-18 Sub-B) `components/FileUpload.tsx`. The new `FileUpload` component is opinionated for bid documents (PDF only, 5 MB, fixed `BidDocType` list). When a fourth upload pattern shows up (e.g. tender documents v2, vendor profile attachments), revisit and extract a more general `<FileUploadField>` that can be parameterised on mime, size, and doc-type list — generalising prematurely now would compromise the bid-doc ergonomics.
