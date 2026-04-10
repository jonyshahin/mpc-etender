# Changelog

## Sprint 5–6 — Notifications, Dashboards, Bilingual Support (2026-04-10)

**3 commits | 21 files changed | 2,215 lines added**

### M-05: Notification Engine
- `NotificationService` with multi-channel dispatch (email, WhatsApp stub, SMS stub, in-app)
- Template-based bilingual notifications with `{{placeholder}}` interpolation
- `NotificationController` for MPC users and vendors (list, mark read, recent JSON endpoint)
- `NotificationBell` dropdown component with unread count badge
- `NotificationTemplateSeeder` with 20 bilingual templates across 9 notification types
- Delivery logging via `notification_logs` with retry tracking
- `LanguageController` for EN/AR language switching

### M-08: Dashboards & Reporting
- `DashboardService` with portfolio overview, project overview, and KPI metrics
- KPI calculations: average cycle time, average bids per tender, savings rate
- Spend analytics: by project, monthly trend, tender status distribution
- Portfolio Dashboard page with KPI cards, status distribution bars, monthly spend chart
- Project Dashboard page with tender pipeline and bid activity
- Sidebar updated with Portfolio and Notifications links

### M-10: Bilingual Support (Arabic/English RTL)
- `lang/en.json` and `lang/ar.json` with 100+ translation keys across all modules
- `useTranslation` hook — `{ t, locale, dir }` for React components
- RTL support via `dir="rtl"` attribute on HTML element
- `SetLocale` middleware updated to read session + user preference
- `HandleInertiaRequests` shares `locale` and `dir` to all pages
- Translations injected into `window.__translations__` via Blade template

---

## Sprint 3–4 — Evaluation Engine & Approval Workflows (2026-04-10)

**2 commits | 27 files changed | 3,145 lines added**

### M-04: Evaluation Engine
- `EvaluationService` with score aggregation, weighted averages, bid ranking
- Two-envelope support: technical pass/fail threshold → financial scoring for passing bids
- `BidOpeningController` with dual-authorization ceremony (two different users required)
- `CommitteeController` for creating technical/financial committees and assigning members
- `ScoringController` with per-criterion per-bid scoring, partial saves, completion tracking
- `EnvelopeController` for completing technical and financial evaluation phases
- `ReportController` with PDF generation via DomPDF, S3 storage
- `ScoringMatrix` reusable React component with real-time weighted total calculation
- 6 React pages: BidOpening, Committees, Scoring, ScoreBid, Report
- 4 form requests: OpenBids, StoreCommittee, AddMember, StoreScores

### M-06: Approval Workflows
- `ApprovalService` with multi-level approval chains (1–3 levels based on tender value)
- Value-based threshold routing from `system_settings` (configurable)
- Level escalation: approval at level N creates level N+1 request if required
- Delegation support: approver can delegate to another user
- Auto-escalation scheduler: hourly check for expired approvals
- Award creation on final approval: sets tender status, creates award record
- 2 React pages: Approval queue (Index), Approval context with decision form (Show)
- 3 action dialogs: Approve, Reject, Delegate with comments

---

## Sprint 1–2 — Admin, Vendor Portal, Tender & Bid Modules (2026-04-10)

**4 commits | 96 files changed | 12,764 lines added**

### M-09: Administration Panel
- 7 Admin Controllers: Dashboard, User, Project, Role, Category, Setting, AuditLog
- 11 Form Requests for all admin operations with authorization
- 23 routes under `/admin/*` with named routes
- 6 shared UI components: DataTable, StatusBadge, ConfirmDialog, SearchableSelect, MultiSelect, LanguageToggle
- 10 React pages: Dashboard, Users (Index/Form), Projects (Index/Form), Roles (Index/Permissions), Categories, Settings, AuditLogs
- Admin sidebar navigation conditional on admin role

### M-01: Vendor Registration & Prequalification
- `VendorService` for registration, prequalification, rejection, suspension workflows
- `FileUploadService` for S3 uploads with access logging and presigned URLs
- 6 Vendor Controllers: Register (multi-step), Login, Dashboard, Profile, Document, Category
- Admin `VendorController` for vendor management (list, detail, approve/reject/suspend)
- Multi-step registration wizard with 5 steps
- Vendor portal layout with dedicated sidebar
- `app.tsx` routing for vendor pages (AuthLayout for login/register, VendorLayout for portal)
- 14 vendor routes + 5 admin vendor management routes

### M-02: Tender Management
- `TenderService` with create/update/publish/cancel/closeSubmission and auto reference numbers
- `BoqService` for BOQ section/item management with Excel import/export
- 6 Controllers: Tender CRUD, BOQ builder, Document versioning, Addenda, Clarifications, Evaluation Criteria
- 9 Form Requests for all tender operations
- Multi-step tender creation wizard (6 steps)
- Tender detail page with tabbed interface (Overview, BOQ, Documents, Addenda, Clarifications, Evaluation)
- Scheduled task: auto-close tenders at submission deadline (every minute)
- 24 routes under `/tenders/*`

### M-03: Bid Submission Portal
- `BidSealingService` with AES-256-CBC encryption via Laravel `encrypt()`, dual-auth opening
- `BidService` for bid lifecycle: draft, pricing, submission, withdrawal, eligibility validation
- Vendor tender browsing (card grid) with category matching
- BOQ pricing table with auto-calculated totals (per-item, section subtotals, grand total)
- Sealed bid submission with confirmation dialog
- Bid withdrawal with reason tracking
- 11 vendor routes for tender browsing and bid CRUD

### M-07: Document Management (integrated)
- All uploads through `FileUploadService` with S3 presigned URLs
- Document versioning: `is_current` toggle with version numbering
- File validation: max 10MB, restricted MIME types
- Access logging in `document_access_logs`

---

## Sprint 0 — Project Setup, Database & Auth Foundation (2026-04-10)

### Added
- Laravel 13 project with React 19 starter kit (Inertia.js)
- Core packages: Horizon, Reverb, Scout, Pulse, Telescope, Pint, Excel, DomPDF, Auditing, Sanctum, Pest
- 19 PHP backed string enums for all status and type fields
- 35 database migrations matching the data dictionary (UUID PKs, composite indexes, unique constraints)
- 31 Eloquent models with HasUuids, $fillable, $casts, relationships, and scopes
- Bid pricing encryption at rest via encrypt()/decrypt() accessor/mutator
- AuditLog model: append-only enforcement (save/update/delete throw RuntimeException)
- Multi-guard auth: `web` (MPC users) + `vendor` (suppliers) with Sanctum API tokens
- UuidPivot base class for all many-to-many pivot tables with UUID PKs
- 5 system roles (super_admin, admin, procurement_officer, project_manager, evaluator)
- 35 permissions across 7 modules
- Role-permission seeder with correct mappings per role
- 7 parent + 20 child work categories (Civil Works, MEP, Finishing, etc.)
- 15 system settings (approval thresholds, notification toggles, security config)
- Default admin user (admin@mpc-group.com)
- 31 model factories with realistic construction procurement data
- 7 authorization policies (Tender, Bid, EvaluationScore, Project, Vendor, EvaluationReport, ApprovalRequest)
- 3 custom middleware (EnsureProjectAccess, SetLocale, LogAuditTrail)
- Foundation Pest tests: auth (4), RBAC (6), bid encryption (3), enum validation (6), audit log (3) — 22 tests, 64 assertions
- Documentation: README, ARCHITECTURE, CODING_STANDARDS, DATABASE, API, SECURITY, CHANGELOG
