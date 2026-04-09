# Architecture

## Modular monolith

Code is organized by **domain**, not by technical layer. Each domain (Admin, Vendor, Tender, Bid, Evaluation, Dashboard) owns its controllers, services, requests, and React pages. Models, enums, and policies live in shared `app/` namespaces but are grouped logically by domain.

## Layering: Controller → Service → Model

- **Controllers** are thin. They receive a Form Request, call a Service method, and return an Inertia response. No business logic, no Eloquent queries beyond simple lookups.
- **Services** (`app/Services/`) hold all business logic. Injected via constructor. Each public method is unit-testable in isolation.
- **Models** (`app/Models/`) define schema-level concerns: relationships, casts, scopes, accessors/mutators. No cross-aggregate logic.
- **Form Requests** (`app/Http/Requests/`) handle validation and authorization. Never validate inline in controllers.
- **Policies** (`app/Policies/`) enforce row-level access. Registered in `AuthServiceProvider`.

## Cross-cutting concerns: Events & Listeners

- **Audit logging** is dispatched via Events on model lifecycle (`Created`, `Updated`, `Deleted`) and explicit business events (`BidOpened`, `TenderAwarded`).
- **Notifications** are dispatched via the central `NotificationService` — never directly from controllers. The service routes through channels (WhatsApp, SMS, email, in-app, broadcast) based on user preferences and `notification_templates`.
- **File uploads** go through `FileUploadService`, which writes to S3, records the access log, and returns a stable reference.

## Multi-guard authentication

Two distinct authenticatable models:

- `web` guard → `App\Models\User` (MPC internal staff). 2FA mandatory.
- `vendor` guard → `App\Models\Vendor` (external suppliers). Sanctum API tokens supported.

Both guards use session driver with Redis backing. Login routes are namespaced (`/login` for MPC, `/vendor/login` for vendors).

## Project-level data isolation

MPC users see only data from projects they are assigned to via the `user_project` pivot table. This is enforced at three layers:

1. **Global query scopes** on `Tender`, `Bid`, `EvaluationReport` filter by `project_id ∈ user.projects`.
2. **Policies** double-check on every authorization call (defense in depth).
3. **Middleware** `EnsureProjectAccess` rejects route access when the URL's `project_id` is not in the user's pivot.

Vendors are isolated similarly: they see only their own bids, their own documents, and tenders they are eligible to bid on.

## Real-time

Reverb broadcasts on private channels:

- `tender.{id}` — addenda, clarifications, status changes
- `bid.{id}` — opening events, evaluation progress
- `user.{id}` — personal notifications

## Search

Meilisearch indexes are configured per searchable model via Scout. Indexed fields exclude any sealed (encrypted) bid pricing.
