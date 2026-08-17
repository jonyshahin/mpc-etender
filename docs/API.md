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
| POST | `/tenders/{tender}/open-bids` | `tenders.open-bids` | Open bids (dual auth) |
| GET | `/tenders/{tender}/bid-summary` | `tenders.bid-summary` | Bid opening summary |
| GET | `/tenders/{tender}/committees` | `tenders.committees.index` | List committees |
| POST | `/tenders/{tender}/committees` | `tenders.committees.store` | Create committee |
| PUT | `/tenders/{tender}/committees/{c}` | `tenders.committees.update` | Update committee |
| POST | `/tenders/{tender}/committees/{c}/members` | `tenders.committees.members.store` | Add member |
| DELETE | `/tenders/{tender}/committees/{c}/members/{m}` | `tenders.committees.members.destroy` | Remove member |
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
| GET | `/vendor/documents` | `vendor.documents.index` | List vendor documents |
| POST | `/vendor/documents` | `vendor.documents.store` | Upload document |
| DELETE | `/vendor/documents/{document}` | `vendor.documents.destroy` | Delete pending document |
| GET | `/vendor/categories` | `vendor.categories.index` | View/select categories |
| PUT | `/vendor/categories` | `vendor.categories.update` | Update category selections |
| GET | `/vendor/notifications` | `vendor.notifications.index` | Vendor notifications |
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
| GET | `/admin/vendors/{vendor}/confirmation` | `admin.vendors.confirmation` | Printable application-confirmation sheet with QR code (requires `vendors.view`) |
| GET | `/admin/vendors/{vendor}/confirmation.pdf` | `admin.vendors.confirmation.pdf` | Same letter as a downloadable PDF (requires `vendors.view`) |
| PUT | `/admin/vendors/{vendor}/prequalify` | `admin.vendors.prequalify` | Approve vendor |
| PUT | `/admin/vendors/{vendor}/reject` | `admin.vendors.reject` | Reject vendor |
| PUT | `/admin/vendors/{vendor}/suspend` | `admin.vendors.suspend` | Suspend vendor |
| POST | `/admin/vendors/{vendor}/send-password-reset` | `admin.vendors.send-password-reset` | Email a reset link to the vendor |
| POST | `/admin/vendors/{vendor}/force-temporary-password` | `admin.vendors.force-temporary-password` | Set a temporary password, shown once |
| POST | `/admin/vendors/{vendor}/reissue-password` | `admin.vendors.reissue-password` | Mint a new temporary password and return to the letter with it (requires `vendors.update`) |

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

## Administration (prefix: `/admin`)

All routes require `auth` + `verified` middleware. Permission checks via Form Requests.

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/dashboard` | `admin.dashboard` | Admin dashboard with stats |
| GET | `/admin/users` | `admin.users.index` | List users |
| POST | `/admin/users` | `admin.users.store` | Create user |
| GET | `/admin/users/{user}/edit` | `admin.users.edit` | Edit user form |
| PUT | `/admin/users/{user}` | `admin.users.update` | Update user |
| DELETE | `/admin/users/{user}` | `admin.users.destroy` | Deactivate user |
| GET | `/admin/projects` | `admin.projects.index` | List projects |
| POST | `/admin/projects` | `admin.projects.store` | Create project |
| GET | `/admin/projects/{project}/edit` | `admin.projects.edit` | Edit project form |
| PUT | `/admin/projects/{project}` | `admin.projects.update` | Update project |
| POST | `/admin/projects/{project}/assign-users` | `admin.projects.assign-users` | Assign users to project |
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
