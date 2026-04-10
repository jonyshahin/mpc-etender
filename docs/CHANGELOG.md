# Changelog

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
