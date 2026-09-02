# API / Route Registry

> Update after adding any route.

## Authentication (Fortify)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/login` | `login` | Login page |
| POST | `/login` | `login.store` | Authenticate user |
| POST | `/logout` | `logout` | Logout |
| GET | `/forgot-password` | `password.request` | Forgot password page |
| POST | `/forgot-password` | `password.email` | Send reset link |
| GET | `/reset-password/{token}` | `password.reset` | Reset password page |
| POST | `/reset-password` | `password.update` | Update password |
| GET | `/email/verify` | `verification.notice` | Verify email prompt |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | Verify email |
| POST | `/email/verification-notification` | `verification.send` | Resend verification |
| GET | `/two-factor-challenge` | `two-factor.login` | 2FA challenge page |
| POST | `/two-factor-challenge` | `two-factor.login.store` | Verify 2FA code |

## Application

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/` | `home` | Welcome page |
| GET | `/dashboard` | `dashboard` | Authenticated dashboard |

## Settings

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/settings/profile` | `profile.edit` | Edit profile page |
| PATCH | `/settings/profile` | `profile.update` | Update profile |
| DELETE | `/settings/profile` | `profile.destroy` | Delete account |
| GET | `/settings/security` | `security.edit` | Security settings page |
| PUT | `/settings/password` | `user-password.update` | Change password |

## Tender Management (prefix: `/tenders`)

All routes require `auth` + `verified` middleware. Project-scoped via user assignments.

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/tenders` | `tenders.index` | List tenders for user's projects |
| GET | `/tenders/create` | `tenders.create` | Create tender form |
| POST | `/tenders` | `tenders.store` | Store new tender (draft) |
| GET | `/tenders/{tender}` | `tenders.show` | Tender detail with tabs |
| GET | `/tenders/{tender}/edit` | `tenders.edit` | Edit tender form |
| PUT | `/tenders/{tender}` | `tenders.update` | Update tender |
| POST | `/tenders/{tender}/publish` | `tenders.publish` | Publish tender |
| POST | `/tenders/{tender}/cancel` | `tenders.cancel` | Cancel tender |
| POST | `/tenders/{tender}/boq-sections` | `tenders.boq.sections.store` | Add BOQ section |
| PUT | `/tenders/{tender}/boq-sections/{section}` | `tenders.boq.sections.update` | Update BOQ section |
| DELETE | `/tenders/{tender}/boq-sections/{section}` | `tenders.boq.sections.destroy` | Delete BOQ section |
| POST | `/tenders/{tender}/boq-sections/{section}/items` | `tenders.boq.items.store` | Add BOQ item |
| PUT | `/tenders/{tender}/boq-items/{item}` | `tenders.boq.items.update` | Update BOQ item |
| DELETE | `/tenders/{tender}/boq-items/{item}` | `tenders.boq.items.destroy` | Delete BOQ item |
| POST | `/tenders/{tender}/boq-import` | `tenders.boq.import` | Import BOQ from Excel |
| POST | `/tenders/{tender}/documents` | `tenders.documents.store` | Upload tender document |
| DELETE | `/tenders/{tender}/documents/{doc}` | `tenders.documents.destroy` | Delete tender document |
| POST | `/tenders/{tender}/addenda` | `tenders.addenda.store` | Issue addendum |
| PUT | `/tenders/{tender}/clarifications/{c}/answer` | `tenders.clarifications.answer` | Answer clarification |
| POST | `/tenders/{tender}/clarifications/{c}/publish` | `tenders.clarifications.publish` | Publish clarification |
| POST | `/tenders/{tender}/evaluation-criteria` | `tenders.criteria.store` | Add evaluation criterion |
| PUT | `/tenders/{tender}/evaluation-criteria/{c}` | `tenders.criteria.update` | Update criterion |
| DELETE | `/tenders/{tender}/evaluation-criteria/{c}` | `tenders.criteria.destroy` | Delete criterion |
| POST | `/tenders/{tender}/open-bids` | `tenders.open-bids` | **Request** an opening, nominating a second authorizer. Opens nothing on its own — requires `bids.open` and project assignment for *both* parties, tender status `submission_closed`, and a past `opening_date` |
| POST | `/tenders/{tender}/open-bids/{openingRequest}/confirm` | `tenders.open-bids.confirm` | The nominated authorizer confirms from their own session; this is what actually unseals the bids. Rejected for anyone else, for the requester, and once the 30-minute window has passed |
| DELETE | `/tenders/{tender}/open-bids/{openingRequest}` | `tenders.open-bids.cancel` | Either party calls off a pending opening |
| GET | `/tenders/{tender}/bid-summary` | `tenders.bid-summary` | Bid opening summary |
| GET | `/tenders/{tender}/committees` | `tenders.committees.index` | List committees |
| POST | `/tenders/{tender}/committees` | `tenders.committees.store` | Create committee |
| PUT | `/tenders/{tender}/committees/{c}` | `tenders.committees.update` | Update committee |
| POST | `/tenders/{tender}/committees/{c}/members` | `tenders.committees.members.store` | Add member |
| DELETE | `/tenders/{tender}/committees/{c}/members/{user}` | `tenders.committees.members.destroy` | Remove member. Binds the **user**, not the pivot row — `members()` is a belongsToMany over users and never exposed a `committee_members.id` |
| POST | `/tenders/{tender}/complete-technical` | `tenders.complete-technical` | Complete technical eval |
| POST | `/tenders/{tender}/complete-financial` | `tenders.complete-financial` | Complete financial eval |
| POST | `/tenders/{tender}/evaluation-report` | `tenders.report.generate` | Generate eval report |
| GET | `/tenders/{tender}/evaluation-report` | `tenders.report.show` | View eval report |
| GET | `/tenders/{tender}/evaluation-report/pdf` | `tenders.report.pdf` | Download report PDF |
| POST | `/tenders/{tender}/request-approval` | `tenders.request-approval` | Submit for approval |

## Evaluation Scoring (prefix: `/evaluations`)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/evaluations/{tender}/score` | `evaluations.score.index` | Scoring dashboard |
| GET | `/evaluations/{tender}/score/{bid}` | `evaluations.score.bid` | Score a bid |
| POST | `/evaluations/{tender}/score/{bid}` | `evaluations.score.store` | Save scores |
| GET | `/evaluations/{tender}/my-progress` | `evaluations.my-progress` | Evaluator progress |

## Approval Workflows (prefix: `/approvals`)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/approvals` | `approvals.index` | Pending approvals queue |
| GET | `/approvals/{approval}` | `approvals.show` | Approval detail |
| POST | `/approvals/{approval}/approve` | `approvals.approve` | Approve at level |
| POST | `/approvals/{approval}/reject` | `approvals.reject` | Reject approval |
| POST | `/approvals/{approval}/delegate` | `approvals.delegate` | Delegate to user |

## Notifications (prefix: `/notifications`)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/notifications` | `notifications.index` | Notification list |
| POST | `/notifications/{notification}/read` | `notifications.read` | Mark notification read |
| POST | `/notifications/mark-all-read` | `notifications.read-all` | Mark all read |
| GET | `/notifications/recent` | `notifications.recent` | Recent notifications (JSON) |

## Dashboards

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/dashboard/portfolio` | `dashboard.portfolio` | Portfolio-wide dashboard |
| GET | `/dashboard/project/{project}` | `dashboard.project` | Project-level dashboard |

## Language

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| PUT | `/user/language` | `language.update` | Switch language (en/ar) |

## Vendor Portal (prefix: `/vendor`)

### Public (guest:vendor middleware)

There is no vendor self-registration. Vendors are onboarded by admins via
`POST /admin/vendors`; see [Vendor Management](#vendor-management-admin-prefix-adminvendors).

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/vendor/login` | `vendor.login` | Vendor login form |
| POST | `/vendor/login` | `vendor.login.store` | Authenticate vendor |

### Authenticated (auth:vendor middleware)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| POST | `/vendor/logout` | `vendor.logout` | Vendor logout |
| GET | `/vendor/dashboard` | `vendor.dashboard` | Vendor dashboard |
| GET | `/vendor/profile` | `vendor.profile.edit` | Edit vendor profile |
| PUT | `/vendor/profile` | `vendor.profile.update` | Update vendor profile |
| GET | `/vendor/password/change` | `vendor.password.change.show` | Change-password form |
| PUT | `/vendor/password/change` | `vendor.password.change` | Change own password |
| GET | `/vendor/documents` | `vendor.documents.index` | List vendor documents |
| POST | `/vendor/documents` | `vendor.documents.store` | Upload document |
| GET | `/vendor/documents/{document}/download` | `vendor.documents.download` | Download own document (logged to `document_access_logs`) |
| DELETE | `/vendor/documents/{document}` | `vendor.documents.destroy` | Delete pending document |
| GET | `/vendor/categories` | `vendor.categories.index` | View approved categories (read-only) |
| GET | `/vendor/category-requests` | `vendor.category-requests.index` | List own category change requests |
| GET | `/vendor/category-requests/create` | `vendor.category-requests.create` | New category change request |
| POST | `/vendor/category-requests` | `vendor.category-requests.store` | Submit category change request |
| GET | `/vendor/category-requests/{r}` | `vendor.category-requests.show` | View own request |
| GET | `/vendor/category-requests/{r}/evidence/{e}/download` | `vendor.category-requests.evidence.download` | Download own evidence (logged to `document_access_logs`) |
| DELETE | `/vendor/category-requests/{r}` | `vendor.category-requests.destroy` | Withdraw an open request |
| GET | `/vendor/notifications` | `vendor.notifications.index` | Vendor notifications (`?unread=1` filters) |
| POST | `/vendor/notifications/read-all` | `vendor.notifications.read-all` | Mark all vendor notifications read |
| POST | `/vendor/notifications/{n}/read` | `vendor.notifications.read` | Mark vendor notification read |
| GET | `/vendor/tenders` | `vendor.tenders.index` | Browse open tenders |
| GET | `/vendor/tenders/{tender}` | `vendor.tenders.show` | View tender details |
| POST | `/vendor/tenders/{tender}/clarifications` | `vendor.tenders.clarifications.store` | Ask clarification |
| GET | `/vendor/bids` | `vendor.bids.index` | List vendor's bids |
| GET | `/vendor/bids/{bid}` | `vendor.bids.show` | View bid details |
| GET | `/vendor/tenders/{tender}/bid` | `vendor.bids.create` | Start or resume bid (redirects to `vendor.bids.show`) |
| PUT | `/vendor/bids/{bid}` | `vendor.bids.update` | Update bid pricing |
| POST | `/vendor/bids/{bid}/submit` | `vendor.bids.submit` | Submit (seal) bid |
| POST | `/vendor/bids/{bid}/withdraw` | `vendor.bids.withdraw` | Withdraw bid |

## Vendor Management (admin, prefix: `/admin/vendors`)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/vendors` | `admin.vendors.index` | List all vendors |
| POST | `/admin/vendors` | `admin.vendors.store` | Onboard a new vendor (requires `vendors.create`) |
| GET | `/admin/vendors/{vendor}` | `admin.vendors.show` | Vendor detail view |
| PUT | `/admin/vendors/{vendor}` | `admin.vendors.update` | Correct company details, contact person and categories (requires `vendors.update`) |
| GET | `/admin/vendors/{vendor}/confirmation` | `admin.vendors.confirmation` | Printable application-confirmation sheet with QR code (requires `vendors.view`) |
| GET | `/admin/vendors/{vendor}/confirmation.pdf` | `admin.vendors.confirmation.pdf` | Same letter as a downloadable PDF (requires `vendors.view`) |
| PUT | `/admin/vendors/{vendor}/prequalify` | `admin.vendors.prequalify` | Approve vendor |
| PUT | `/admin/vendors/{vendor}/reject` | `admin.vendors.reject` | Reject vendor |
| PUT | `/admin/vendors/{vendor}/suspend` | `admin.vendors.suspend` | Suspend vendor |
| POST | `/admin/vendors/{vendor}/send-password-reset` | `admin.vendors.send-password-reset` | Email a reset link to the vendor |
| POST | `/admin/vendors/{vendor}/force-temporary-password` | `admin.vendors.force-temporary-password` | Set a temporary password, shown once |
| POST | `/admin/vendors/{vendor}/reissue-password` | `admin.vendors.reissue-password` | Mint a new temporary password and return to the letter with it (requires `vendors.update`) |
| POST | `/admin/vendors/{vendor}/documents` | `admin.vendors.documents.store` | File a prequalification document on the vendor's behalf (requires `vendors.review_docs`) |
| PUT | `/admin/vendors/{vendor}/documents/{document}/approve` | `admin.vendors.documents.approve` | Accept a document the vendor uploaded (requires `vendors.review_docs`) |
| PUT | `/admin/vendors/{vendor}/documents/{document}/reject` | `admin.vendors.documents.reject` | Reject a document; `reason` required and shown to the vendor (requires `vendors.review_docs`) |
| DELETE | `/admin/vendors/{vendor}/documents/{document}` | `admin.vendors.documents.destroy` | Remove a document and its stored file (requires `vendors.review_docs`) |

`confirmation` renders `admin/Vendors/Confirmation` — a layout-less printable
sheet listing the vendor's company details, contact person, categories and
application status, alongside a QR code. The QR encodes the `general.website_url`
system setting, falling back to `APP_URL` when that is blank, so it carries no
credential and the sheet is safe to print, email or hand over. The SVG is
generated by `QrCodeService` via `bacon/bacon-qr-code`, which ships as a Fortify
dependency — no extra package, and no GD or imagick requirement.

The letterhead shows the `general.project_name` setting (default
"Boulevard Mosul Project") above `general.company_name`, with the logo from
`public/boulevard-logo.png` when present, otherwise the committed
`public/boulevard-logo.svg`.

`confirmation.pdf` renders `resources/views/pdf/vendor-confirmation.blade.php`
through dompdf. It is a separate Blade view because dompdf executes no
JavaScript, and it omits the Arabic company name because dompdf performs no
Arabic shaping or bidi — use the browser Print button for an Arabic-faithful
copy. The QR is embedded as a raster there rather than the page's vector SVG:
dompdf expands an SVG QR into thousands of path operations and exhausts memory.

`store` creates the vendor in `pending` status with a generated temporary
password and `must_change_password = true`, then redirects to the confirmation
letter so the credentials can be printed and handed over in one step. The
password is stored bcrypt-hashed, so it appears on the letter only while it is
in flight — it survives a reload of that page but is gone once the admin
navigates elsewhere, and a later reprint omits it. Prequalification remains a
separate step.

To reprint a letter *with* credentials, use `reissue-password`. It mints a fresh
temporary password, sets `must_change_password`, writes the same
`password_reset_admin_temp` audit row as `force-temporary-password`, and returns
to the letter with the new password on it. The previous password stops working —
which is the intended behaviour, since a reprint usually means the first copy was
lost. The letter surfaces the action only on a reprint and only to admins holding
`vendors.update`, and explains on screen why the password is absent.

### Editing a vendor

`update` is the only way a vendor record changes. Vendors are onboarded by
admins and the portal gives a vendor no way to edit their own company details,
so before this a typo in a licence number or a changed contact person had
nowhere to go.

`VendorService::updateByAdmin()` is deliberately narrow: it writes the fields on
the admin form plus the category assignment, and nothing else. Prequalification
status, `is_active` and credentials each have their own action with their own
audit row — folding them in here would let a routine typo fix quietly change a
vendor's standing or lock them out. Posting those fields anyway has no effect,
since the service is handed `$request->validated()`.

Two of the editable fields are not cosmetic:

- **`email` is the vendor's login identity**, so changing it changes who can
  sign in. Its uniqueness rule ignores the vendor's own row, or saving an
  untouched form would fail against the record it is saving.
- **`category_ids` decides tender eligibility.** It is `sync()`ed, so an edit
  removes categories as well as adding them. This is the admin-side counterpart
  to the vendor-initiated `vendor_category_requests` queue, not a replacement
  for it.

The `vendor_updated_by_admin` audit row carries only the fields that actually
moved, categories included. An unchanged save writes no row at all — an audit
trail full of no-ops buries the changes worth finding.

### The vendors list

`GET /admin/vendors` follows the same shape as the tenders list: `statusCounts`
and `summary` are built from the same base query as the rows, so the tiles and
tab counts cannot disagree with the list under them. Counts follow the search
and the category but deliberately **not** the status, or every other tab would
read zero the moment one was selected. All six `VendorStatus` cases appear —
the old UI offered four, leaving `under_review` and `blacklisted` unfilterable.

`sort` is whitelisted, for the same reason as the tenders list: `orderBy()`
checks the direction but not the column, so `?sort=anything` was a 500.

Two things worth knowing about the query:

- **`select()` comes before `withCount()`.** `select()` replaces the select
  list, so calling it afterwards drops the count subqueries and rows arrive
  with no `documents_count` at all.
- **The `category_id` filter is not new** — the controller has always accepted
  it and `scopeInCategory` has always existed. The page simply never rendered
  a control for it, so it was unreachable from the UI. It now drives a select
  built from the same category tree the Add Vendor dialog uses.

Search covers the English name, the Arabic name, the email and the trade
licence number.

### The tenders list

`GET /tenders` is scoped by project assignment before every other filter — a
user sees only tenders in the projects they are on.

It returns `statusCounts` and `summary` alongside the paginated rows. Both are
built from the same base query as the rows, so the tiles and the tab counts
can never disagree with the list under them. The counts follow the search but
deliberately **not** the status filter: they answer "how many would this
search find in each tab", and applying the status filter to them would make
every other tab read zero the moment one was selected. Every status appears,
including empty ones, so a tab never disappears as it empties.

`sort` is checked against a whitelist. `orderBy()` validates the direction and
throws on anything but asc/desc, but hands the column straight to the grammar
— so `?sort=anything` used to be a 500. Both now fall back to
`created_at desc`.

The page echoes the complete filter set back as `filters`, and passes it to
`DataTable`. That prop is not optional in practice: `DataTable` merges it into
every sort request, so without it sorting wiped the search and status, and —
because it compares against a `filters.sort` that was always undefined —
could never toggle to descending.

### The landing dashboard

`GET /dashboard` was a `Route::inertia` stub rendering four placeholder
rectangles — every internal user's first screen after signing in carried no
information at all. It is now backed by `DashboardService::landing()`.

The page has two halves. The headline tiles describe the portfolio and are the
same for everyone. The "needs your attention" queues describe *this* user's
work and are each gated on the permission behind the page they link to, so a
procurement officer and an evaluator get different lists from the same route —
showing a queue someone cannot open would turn the dashboard into a list of
dead ends. A queue with a count of zero is dropped client-side.

Two details worth keeping:

- **The award trend is grouped in PHP, not SQL.** The older `monthlySpend()`
  uses `DATE_FORMAT`, which is MySQL-only and throws under the SQLite test
  suite. Row counts here are small enough that portability wins. It is
  gap-filled to a full twelve months so the axis is a continuous timeline
  rather than a line joining whichever months happened to have activity.
- **The pipeline is returned in `TenderStatus` order, not count order**, and
  includes stages with zero tenders. The sequence is the meaning; sorting by
  size would destroy it.

`/dashboard/portfolio` and `/dashboard/project/{project}` are unchanged and
remain the deeper reporting views.

### Arabic in the PDFs

dompdf draws glyphs in the order it is given them and does neither of the two
things Arabic needs: no bidi reordering, and no shaping to the joined letter
form that depends on a character's neighbours. Handed logical-order Arabic it
prints text that is both reversed and written in disconnected isolated
letters — `اربيل / موصل` came out as `لصوم / ليبرا`.

Both PDF templates therefore pass every user-supplied string through
`ArabicTextService::forPdf()`, which converts it to the visually-ordered
Arabic Presentation Forms-B sequence dompdf can lay out. DejaVu Sans, which
the templates use, carries 141 of that block's 144 glyphs. Strings with no
Arabic pass through untouched, and Western digits are preserved rather than
rewritten as Arabic-Indic.

**Never apply it to anything a browser renders.** The React pages must receive
logical order — browsers do their own bidi and shaping, and pre-shaped text
breaks text selection, in-page search and screen readers.

One limitation: because the text reaches dompdf already in visual order, a
string long enough for the renderer to wrap will have its lines in the wrong
order. The fields on these letters are short; a long Arabic paragraph would
need ar-php to do the wrapping instead.

### Vendor documents

Vendors are onboarded by admins rather than registering themselves, so most
prequalification paperwork arrives by hand or email and never passes through the
vendor portal. `documents.store` is how it gets on file.

A document an admin files is recorded as **approved**, with `uploaded_by` and
`reviewed_by` both naming them: the admin received the paperwork directly, so
filing it is the verification. A vendor's own upload lands as `pending` with
`uploaded_by` null, and an admin approving it later sets only `reviewed_by` —
the two columns stay distinguishable in an audit.

Both paths run through `VendorDocumentService`, write to the same
`vendors/{id}/documents` S3 prefix and enforce the same PDF-only size policy
(`App\Rules\PdfFile`), so a document is not identifiable later by which route it
came in through. Every action writes an `AuditLog` row scoped to the vendor:
`vendor_document_filed_by_admin`, `vendor_document_uploaded`,
`vendor_document_approved`, `vendor_document_rejected`, `vendor_document_deleted`.

`{document}` is bound independently of `{vendor}`, so each route asserts the
document belongs to the vendor in the URL and 404s otherwise.

Document types come from `App\Enums\DocumentType` and are served to both pickers
as `documentTypes`. They were previously hardcoded in the React page and
duplicated as an `in:` list in the FormRequest; the two had drifted, so four of
the eight options the vendor was offered failed validation on submit.

## Administration (prefix: `/admin`)

All routes require `auth` + `verified` middleware. Permission checks via Form Requests.

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/dashboard` | `admin.dashboard` | Admin dashboard with stats |
| GET | `/admin/users` | `admin.users.index` | List users. Accepts `search` (name, email, phone), `role_id`, `status` (`active` or `inactive`), `sort` (one of `name`, `email`, `is_active`, `last_login_at`, `created_at`) and `direction`; anything else falls back to `created_at desc`. `status` replaces the earlier `is_active`, which read an empty value as false and so narrowed the list to deactivated accounts |
| POST | `/admin/users` | `admin.users.store` | Create user |
| GET | `/admin/users/{user}/edit` | `admin.users.edit` | Edit user form |
| PUT | `/admin/users/{user}` | `admin.users.update` | Update user |
| DELETE | `/admin/users/{user}` | `admin.users.destroy` | Deactivate user |
| GET | `/admin/projects` | `admin.projects.index` | List projects. Accepts `search` (name, Arabic name, code, client), `status` (a `ProjectStatus` value), `sort` (one of `name`, `code`, `location`, `status`, `start_date`, `end_date`, `created_at`) and `direction`; anything else falls back to `created_at desc` |
| POST | `/admin/projects` | `admin.projects.store` | Create project |
| GET | `/admin/projects/{project}/edit` | `admin.projects.edit` | Edit project form |
| PUT | `/admin/projects/{project}` | `admin.projects.update` | Update project |
| POST | `/admin/projects/{project}/assign-users` | `admin.projects.assign-users` | Assign users to project |
| PUT | `/admin/projects/{project}/users/{user}` | `admin.projects.users.update` | Change a member's project role |
| DELETE | `/admin/projects/{project}/users/{user}` | `admin.projects.users.destroy` | Remove a member from the project |
| GET | `/admin/roles` | `admin.roles.index` | List roles |
| POST | `/admin/roles` | `admin.roles.store` | Create role |
| PUT | `/admin/roles/{role}` | `admin.roles.update` | Update role |
| GET | `/admin/roles/{role}/permissions` | `admin.roles.permissions` | View role permissions |
| PUT | `/admin/roles/{role}/permissions` | `admin.roles.permissions.update` | Update role permissions |
| GET | `/admin/categories` | `admin.categories.index` | List categories (tree) |
| POST | `/admin/categories` | `admin.categories.store` | Create category |
| PUT | `/admin/categories/{category}` | `admin.categories.update` | Update category |
| DELETE | `/admin/categories/{category}` | `admin.categories.destroy` | Delete category |
| GET | `/admin/settings` | `admin.settings.index` | View system settings |
| PUT | `/admin/settings` | `admin.settings.update` | Update settings |
| GET | `/admin/audit-logs` | `admin.audit-logs.index` | View audit logs |

## Package dashboards

| URI | Package |
|-----|---------|
| `/horizon` | Laravel Horizon (queue monitoring) |
| `/pulse` | Laravel Pulse (app monitoring) |

## Custom middleware aliases

| Alias | Class | Purpose |
|-------|-------|---------|
| `project.access` | `EnsureProjectAccess` | Verify user is assigned to the route's project |
