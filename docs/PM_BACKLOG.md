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

1. **BUG-26 — Addendum deadline cascade fix.** High severity. Prompt ready: `docs/prompts/PROMPT_BUG_26_ADDENDUM_DEADLINE_CASCADE.md`. Blocks Step 7 of test plan (vendor bid submission against `MBP-T008`). Run when Johnny gives the go-ahead.
2. **BUG-03 + BUG-29 — i18n Strategy A cleanup batch.** Mechanical. Apply Strategy A pattern to settings page raw config keys (BUG-03) and vendor document type labels (BUG-29). Likely batch with role names, category names, status labels. Prompt not yet drafted — Claude (PM) to write when BUG-26 lands.
3. **BUG-28 full enforcement build (~5–7 days).** Build `EnsureTwoFactorAuthenticated` middleware, wire to `auth('web')` route group, gate on the setting key, expand Pest coverage. Includes TECH-DEBT-09 (drop `users.is_2fa_enabled` column). Blocked behind BUG-26 verification on prod. Prompt not yet drafted.
4. **Step 7 of automated test plan — vendor bid submission.** Unblocked the moment BUG-26 ships and is verified. Resume from `ETENDER_TEST_PLAN.md`. Steps 8–12 follow.
5. **Other open BUGS** (BUG-01 RTL sidebar, BUG-02 sidebar padding, BUG-05 MultiSelect a11y, BUG-06 ku language pref, BUG-07 transaction wrap, BUG-08 audit log fields). Tracked in `BUGS.md`. Schedule individually or batch by file/area as opportunities arise.

---

## Scope decisions pending (Johnny + sponsor)

- **BUG-27 — Admin dashboard scope.** Two options on the table:
  - Option A (PM recommendation): system-health/governance cockpit (users by role, vendor queue, audit log tail, Horizon health, queue depth, storage, settings drift). Distinct from `/dashboard/portfolio` (cross-project KPIs) and `/dashboard` (personal).
  - Option B (pragmatic): embed the portfolio view in the admin dashboard for Phase 1, split later.
  - **Awaiting:** Johnny's call. Estimated 2–3 days once decided.

- **TECH-DEBT-08 — Tender Shortlist tab.** New feature in M-04 Evaluation Engine. Six open design questions documented in `BUGS.md` under TECH-DEBT-08. Awaiting product/scope decisions before any prompt is drafted. ~1 sprint once locked.

---

## Recently shipped (rolling log, last ~10)

- **2026-04-27** — `be3631e` `fix(BUG-28)`: hide 2FA mandatory toggle behind "Coming soon" badge (interim mitigation, Pattern A inline early-return). Local + post-deploy smoke verified. Full enforcement build deferred behind BUG-26.
- **2026-04-27** — `218f6ac` `docs(bugs)`: BUG-28 investigation verdict — COSMETIC. Toggle persists `security.2fa_mandatory_internal` but no enforcement code reads it.
- **2026-04-27** — `eac694c` `docs(bugs)`: log 5 new items from prod verification (BUG-26, BUG-27, BUG-28, BUG-29, TECH-DEBT-08).
- **2026-04-12** — `37482d6` `[Toast] Fix Sonner toast notifications system-wide` (73 calls, 23 controllers, 68 i18n keys, RTL position).

---

## Conventions

- **Bug numbering:** `BUG-NN` for user-visible breakage, `TECH-DEBT-NN` for non-bug forward work or refactors. Both number monotonically — closed items keep their number, gaps in `BUGS.md` reflect closed/historical entries (look up via `git log --grep="BUG-NN"`).
- **Strategy A i18n:** DB stores slugs, React renders via ``t(`scope.${slug}.label`)``, dev-mode `console.warn` on missing keys. Applies to settings, doc types, role names, category names, status labels — any DB-seeded enum-like values.
- **Audit log:** capture old values **before** save (Laravel's `getOriginal()` post-save returns the new value — known gotcha).
- **Toasts:** `Inertia::flash('toast', ['type' => '...', 'message' => __('...')])`. All messages translated en + ar (ku gets `[en]` placeholder until full translation pass).
- **Build environment:** Docker for backend/Pint/Pest/tinker. Host for `npm run build` and `npx tsc --noEmit` (TECH-DEBT-07 workaround).
- **PR/commit hygiene:** every commit references `BUG-NN` or `TECH-DEBT-NN` in the message so `git log --grep` works.

---

## Session-start ritual (Claude Code)

When opening a fresh `claude --resume "mpc-etender-web"` session, before doing anything else:

```bash
cat BUGS.md docs/PM_BACKLOG.md | head -200
```

Or read both files via the `view` tool. This loads the current queue state, blocking decisions, and recent context so the session begins informed instead of asking Johnny "what should I work on?" — the answer is already in this file.
