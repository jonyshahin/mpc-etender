# Security

## RBAC model

Three-layer authorization:

1. **Roles** (`roles` table) — 5 system roles: super_admin, admin, procurement_officer, project_manager, evaluator.
2. **Permissions** (`permissions` table) — 35 granular capability flags grouped by module (vendors, tenders, bids, evaluations, reports, admin, approvals).
3. **Role-permission mapping** (`role_permissions` pivot) — each role is assigned a set of permissions. Super admin gets all.

Users are assigned exactly one role. Permission checks use `User::hasPermission($slug)` which queries through the role relationship.

## Authorization policies

Laravel Policies enforce row-level access rules. Registered in `AppServiceProvider` via `Gate::policy()`:

| Policy | Key rules |
|--------|-----------|
| `TenderPolicy` | `view`: user must be assigned to tender's project. `create`: requires `tenders.create` permission. `update`/`delete`: only while status=draft. `publish`: requires `tenders.publish`. |
| `BidPolicy` | `view`: MPC user assigned to project OR the vendor who owns the bid. `viewPricing`: only after opening_date passed AND bid is unsealed. `create`: vendor must be qualified + in matching category. `submit`: only before submission_deadline. |
| `EvaluationScorePolicy` | `score`: user must be on the tender's evaluation committee + have `evaluations.score` permission. |
| `ProjectPolicy` | `view`: user must be assigned to project via `user_project` pivot. |
| `ApprovalRequestPolicy` | `approve`: user must have `approvals.levelN` permission matching the request's approval_level. |

## Project-level data isolation

MPC users see only data from projects they are assigned to:

1. **`user_project` pivot** — assigns users to projects with a role (project_manager, procurement_officer, evaluator, viewer).
2. **Policies** — every `view` check verifies `User::isAssignedToProject($projectId)`.
3. **`EnsureProjectAccess` middleware** — registered as `project.access` route middleware. Rejects requests where the route's project_id is not in the user's pivot.

Vendors are isolated similarly: they see only their own bids, documents, and eligible tenders.

## Bid sealing and encryption

- Bid pricing data is encrypted at rest via Laravel `encrypt()` / `decrypt()` using accessor/mutator on `Bid::encrypted_pricing_data`.
- Bids are created with `is_sealed = true`. Pricing is only decryptable programmatically, but the `BidPolicy::viewPricing` check ensures no controller exposes it until `tender.opening_date` has passed AND the bid is unsealed.
- **Dual authorization**: bid opening requires two different authenticated users (enforced at the service layer — `security.bid_opening_dual_auth` system setting).

## 2FA enforcement

- `is_2fa_enabled` flag on users. The `security.2fa_mandatory_internal` system setting controls whether 2FA is mandatory for MPC users.
- Fortify's `TwoFactorAuthenticatable` trait handles TOTP secret generation and verification.

## Audit trail

- `audit_logs` table is append-only. The `AuditLog` model overrides `save()` (on existing records), `update()`, and `delete()` to throw `RuntimeException`.
- `LogAuditTrail` middleware logs all non-GET HTTP requests (method, URI, user, IP, user agent) via the `terminate()` pattern (non-blocking, after response).
- `document_access_logs` tracks every document view/download/print with actor, IP, and timestamp.

## System settings

Security-relevant settings in `system_settings`:
- `security.bid_opening_dual_auth` — require two users for bid opening
- `security.2fa_mandatory_internal` — enforce 2FA for MPC staff
- `security.session_timeout_minutes` — idle session timeout
