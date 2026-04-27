# PM Backlog — MPC e-Tender

> **Owner:** Claude (PM role, drafts via prompts pasted into Claude Code).
> **Audience:** Claude Code (developer role) reads this at session start to load queue state. Future engineers/PMs read this to understand priority decisions and pending scope questions.
> **Companion file:** `BUGS.md` (engineer-owned, holds bug definitions and fixes). This file holds the *meta* — what to work on next, why, what's blocked on whom.

---

## How this file works

- **Priority queue** is the ordered list of "what's next" — Claude Code should execute the topmost unblocked item when it has capacity and no other instruction.
- **Blocked items** are listed with what they're blocked on (sponsor decision, prerequisite bug, external dependency).
- **Scope decisions pending** are open product/scope questions that need Johnny + sponsor/PM input — Claude Code should NOT preempt these.
- **Recently shipped** is a short rolling log (last 5–10 items) so a fresh session can see what just landed without reading the full git log.
- Updates to this file come from PM-drafted prompts. Claude Code commits the changes; it does not author priority decisions unilaterally.

---

## Priority queue (top = next)

1. **BUG-03 + BUG-29 — i18n Strategy A cleanup batch.** Mechanical. Apply Strategy A pattern to settings page raw config keys (BUG-03) and vendor document type labels (BUG-29). Likely batch with role names, category names, status labels. Prompt not yet drafted — BUG-26 has landed (commit `9fb1f18`, 2026-04-27); Claude (PM) to draft the i18n batch prompt next cycle.
2. **BUG-28 full enforcement build (~5–7 days).** Build `EnsureTwoFactorAuthenticated` middleware, wire to `auth('web')` route group, gate on the setting key, expand Pest coverage. Includes TECH-DEBT-09 (drop `users.is_2fa_enabled` column). Blocked behind BUG-26 verification on prod. Prompt not yet drafted.
3. **Step 7 of automated test plan — vendor bid submission.** Unblocked the moment BUG-26 ships and is verified. Resume from `ETENDER_TEST_PLAN.md`. Steps 8–12 follow.
4. **Other open BUGS** (BUG-01 RTL sidebar, BUG-02 sidebar padding, BUG-05 MultiSelect a11y, BUG-06 ku language pref, BUG-07 transaction wrap, BUG-08 audit log fields). Tracked in `BUGS.md`. Schedule individually or batch by file/area as opportunities arise.

---

## Scope decisions pending (Johnny + sponsor)

- **BUG-27 — Admin dashboard scope.** Two options on the table:
  - Option A (PM recommendation): system-health/governance cockpit (users by role, vendor queue, audit log tail, Horizon health, queue depth, storage, settings drift). Distinct from `/dashboard/portfolio` (cross-project KPIs) and `/dashboard` (personal).
  - Option B (pragmatic): embed the portfolio view in the admin dashboard for Phase 1, split later.
  - **Awaiting:** Johnny's call. Estimated 2–3 days once decided.

- **TECH-DEBT-08 — Tender Shortlist tab.** New feature in M-04 Evaluation Engine. Six open design questions documented in `BUGS.md` under TECH-DEBT-08. Awaiting product/scope decisions before any prompt is drafted. ~1 sprint once locked.

---

## Recently shipped (rolling log, last ~10)

- **2026-04-27** — `0d7a84c` `fix(BUG-31)`: disable Fortify `/register` route. Internal closed system — internal users created by admins via `/admin/users`, vendors via `/vendor/register`. `Features::registration()` removed from `config/fortify.php`; `RegistrationTest` rewritten to assert GET + POST `/register` return 404. Suite 248/1-failing/10-skipped → **249/0-failing/10-skipped** (first zero-failing baseline since the audit). `App\Actions\Fortify\CreateNewUser` left in place pending TECH-DEBT cleanup pass. Surfaced as a side finding while fixing BUG-30 — was misattributed to BUG-30 in the original audit grouping.
- **2026-04-27** — `24fff42` `fix(BUG-32)`: gate admin routes, Horizon, and Pulse by role. New `EnsureUserHasRole` middleware (alias `'role'`) closes a latent privilege-escalation gap on the entire `/admin/*` route group; `Horizon::auth` and `viewPulse` Gate swapped from email-allowlist to `role IN (admin, super_admin)`. 5 Pest tests in `tests/Feature/Admin/RoleGateTest.php` cover positive and negative paths. Side finding: 11 pre-existing admin tests had been silently relying on the missing role gate (helpers used random role slugs); their helpers now seed `admin` slug, with "no-permission" cases on `super_admin` so the asserted 403 is the controller-level permission check rather than the role middleware. Surfaced by BUG-27 Phase 1 audit.
- **2026-04-27** — `7ce6a5c` `fix(BUG-30)`: correct `ProfileValidationRules` type signatures for UUID PKs. Two-char fix (`?int` → `?string`) on `profileRules()` and `emailRules()` in `app/Concerns/ProfileValidationRules.php` plus a UUID-keyed regression test. Suite went 240/3-failing/10-skipped → 243/1-failing/10-skipped. Surfaced by audit 2026-04-27; would have produced 500s on every internal-user `/settings/profile` PATCH in production. Side finding: the audit conflated `RegistrationTest::test_new_users_can_register` with this bug — that's a separate `users.role_id` NOT NULL issue, filed as BUG-31 and resolved by `0d7a84c`.
- **2026-04-27** — `9fb1f18` `fix(BUG-26)`: cascade `opening_date` when addendum extends submission deadline. New `MinHoursAfter` validation rule + 24h buffer setting + DB::transaction wrap + AuditLog capture for both fields. 8 Pest tests, browser-verified en + ar. Backfill identified 1 existing bad-state tender (MBP-T008) flagged for procurement-officer-issued corrective addendum.
- **2026-04-27** — `8eef587` `fix(BUG-23)`: split addendum-form gate from canEdit. New `canIssueAddendum` prop allows the form on Published tenders while keeping canEdit (Draft-only) correct for tender-content edits. Prerequisite for BUG-26 browser verification.
- **2026-04-27** — `be3631e` `fix(BUG-28)`: hide 2FA mandatory toggle behind "Coming soon" badge (interim mitigation, Pattern A inline early-return). Local + post-deploy smoke verified. Full enforcement build deferred behind BUG-26.
- **2026-04-27** — `218f6ac` `docs(bugs)`: BUG-28 investigation verdict — COSMETIC. Toggle persists `security.2fa_mandatory_internal` but no enforcement code reads it.
- **2026-04-27** — `eac694c` `docs(bugs)`: log 5 new items from prod verification (BUG-26, BUG-27, BUG-28, BUG-29, TECH-DEBT-08).
- **2026-04-12** — `37482d6` `[Toast] Fix Sonner toast notifications system-wide` (73 calls, 23 controllers, 68 i18n keys, RTL position).

---

## Conventions

- **Bug numbering:** `BUG-NN` for user-visible breakage, `TECH-DEBT-NN` for non-bug forward work or refactors. Both number monotonically — closed items keep their number, gaps in `BUGS.md` reflect closed/historical entries (look up via `git log --grep="BUG-NN"`).
- **Strategy A i18n:** DB stores slugs, React renders via ``t(`scope.${slug}.label`)``, dev-mode `console.warn` on missing keys. Applies to settings, doc types, role names, category names, status labels — any DB-seeded enum-like values.
- **Audit log:** capture old values **before** save (Laravel's `getOriginal()` post-save returns the new value — known gotcha).
- **Gate-split for amends-published-tender actions:** the `update` policy / `canEdit` correctly restricts to Draft (you can't edit a published tender's content). Actions that *amend* a published tender (addenda, clarifications, withdrawals) need their own permission + status check, gated separately in the controller and exposed as a distinct prop (e.g., `canIssueAddendum`). Don't bundle them under `canEdit`. Pattern established in BUG-23 (`8eef587`); apply when adding similar amends-published-tender features.
- **Toasts:** `Inertia::flash('toast', ['type' => '...', 'message' => __('...')])`. All messages translated en + ar (ku gets `[en]` placeholder until full translation pass).
- **Build environment:** Docker for backend/Pint/Pest/tinker. Host for `npm run build` and `npx tsc --noEmit` (TECH-DEBT-07 workaround).
- **PR/commit hygiene:** every commit references `BUG-NN` or `TECH-DEBT-NN` in the message so `git log --grep` works.

---

## Session rituals (Claude Code)

### Session start

Before doing anything else in a fresh `claude --resume "mpc-etender-web"` session:

```bash
cat BUGS.md docs/PM_BACKLOG.md | head -200
```

Or read both files via the `view` tool. This loads current queue state, blocking decisions, and recent context so the session begins informed instead of asking Johnny "what should I work on?" — the answer is already in this file.

### Session end

Before ending a session, run this drift check to prevent the substrate leak diagnosed in commit `a55672c` (housekeeping 2026-04-27) from re-forming. This takes ~30 seconds.

**Step 1 — Extract filed bug numbers from `BUGS.md`:**

```bash
grep -E "^- \*\*BUG-[0-9]+|^- \*\*TECH-DEBT-[0-9]+" BUGS.md | grep -oE "BUG-[0-9]+|TECH-DEBT-[0-9]+" | sort -u
```

**Step 2 — Extract bug numbers referenced in your local todo list this session.** This includes anything in active todos OR mentioned in conversation as a "BUG-NN" reference (whether written or said). If your todos don't have explicit `BUG-NN` prefixes, scan their text for any matches.

**Step 3 — Diff:** for every `BUG-NN` / `TECH-DEBT-NN` in your todos that does NOT appear in Step 1's output, classify it as one of:

- **(a) Shipped this session** — verify with `git log --grep="BUG-NN" -n 1 --since="<session start time>"`. Mark the todo done; no BUGS.md change needed if the bug was never filed in the first place.
- **(b) Belongs in BUGS.md** — file the entry now, before ending the session, with whatever context is in working memory. Mark the entry with a `[NEEDS_PM_REVIEW]` tag at the end of the prose so PM (Johnny / Claude in PM mode) sees it next session-start and can refine severity / repro / scope.
- **(c) Ambiguous** — flag in your final session report so PM can decide. Don't file speculatively.

**Step 4 — Update PM_BACKLOG's "Recently shipped" rolling log** if any commits this session closed a bug. Most fix prompts include this update implicitly; this step is the safety net for sessions where it didn't (housekeeping, triage, small docs fixes).

**Step 5 — Final session report** lists, in addition to the per-task results:

- Drift check verdict: `clean` / `closed N items in-flight` / `flagged N items for PM review`
- Any `[NEEDS_PM_REVIEW]` entries newly filed in this session

If drift check returns `clean`, write `clean` and move on. The ritual exists to surface drift, not generate paperwork when there isn't any.

### Why both rituals exist

The session-start ritual loads the substrate. The session-end ritual *protects* the substrate from divergence. Together they make `BUGS.md` + `docs/PM_BACKLOG.md` the single source of truth in fact, not just in policy. Without the end ritual, every session leaks a little context into Claude Code's local todo list that nobody else can read; with it, that drift is detected within the same session, while context is still fresh.

The 73% leak rate caught in housekeeping commit `a55672c` is the empirical case for this ritual. If future drift checks return `clean` repeatedly across many sessions, the ritual has done its job and we can revisit whether it's still earning its keep. Until then, run it.
