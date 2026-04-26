# Claude Code Prompt — Fix BUG-26 (Addendum Deadline Cascade)

> **Status:** QUEUED. Run after BUG-28 interim mitigation lands (it has — commit `be3631e`). Next priority per `docs/PM_BACKLOG.md`.

> **How to run:** From `C:\VScode\eTender System`, run `claude --resume "mpc-etender-web"` and paste this entire prompt **after** the BUG-28 interim mitigation commit lands. BUG-26 is a code fix; the queue order matters because BUG-28's interim mitigation should ship first to remove the false-security signal before any non-trivial work happens.
>
> **Local environment is Docker-based.** All `php artisan` / `composer` / `vendor/bin/pint` / `vendor/bin/pest` commands run inside the app container via `docker compose exec app ...`. Confirm `docker compose ps` is healthy and the service name is `app` before starting. If the service name differs, substitute throughout. Per **TECH-DEBT-07**, `npm run build` / `npx tsc --noEmit` run on the **Windows host** instead of inside the container — that workaround is established.

---

## Context

Production verification on April 27, 2026 surfaced a high-severity bug on tender `MBP-T008`: issuing an addendum with "Extend Deadline" checked updates `submission_deadline` but leaves `opening_date` untouched. This produces tenders where `submission_deadline > opening_date` — mathematically un-openable. Logged as **BUG-26** in `BUGS.md`. This is a hard blocker for Step 7 of the test plan (vendor bid submission against `MBP-T008` will hit this state).

Reference for stack and conventions: `/mnt/skills/user/laravel-dev/SKILL.md` (read it before writing code). Project conventions you already established: audit-first debugging, Strategy A i18n for any DB-backed labels, `Inertia::flash('toast', [...])` for user feedback, audit log capture **before** save (the `getOriginal()` post-save gotcha), Pest test per controller action.

---

## Phase 1 — Audit & Reproduce (read-only, report before any code changes)

### 1.1 Pre-flight: Docker stack health

```bash
cd "C:/VScode/eTender System/mpc-etender"
docker compose ps
```

Confirm the `app` service is running and healthy. If anything's unhealthy, stop and report — don't try to debug addendum logic on top of a broken stack.

### 1.2 Locate the addendum write path

```bash
find app -path '*Addendum*' -type f
grep -rn "extend_deadline\|extendDeadline" app/ 2>/dev/null
```

Identify:
- The controller action that handles "Extend Deadline" (likely `AddendumController::store`)
- Any service/action class it delegates to (look in `app/Services/`, `app/Actions/`)

Show the full current method body for the action that processes `extend_deadline`.

### 1.3 Locate the addendum form request

```bash
find app/Http/Requests -iname '*Addendum*'
```

Show the current `rules()` array. Note whether `extend_deadline`, `new_submission_deadline`, or `new_opening_date` exist as fields.

### 1.4 Inspect the `tenders` table schema (run inside container)

```bash
docker compose exec app php artisan tinker --execute="echo json_encode(Schema::getColumnListing('tenders'));"
```

Confirm exact column names — don't guess. The audit log in BUGS.md context mentions `submission_deadline` / `opening_date` but verify against the live schema.

### 1.5 Inspect the `addenda` table schema

```bash
docker compose exec app php artisan tinker --execute="echo json_encode(Schema::getColumnListing('addenda'));"
```

Confirm exact column names for what the addendum stores. Does it persist `new_submission_deadline`, `new_opening_date`, or a delta?

### 1.6 Locate the React form component

```bash
find resources/js -iname '*addend*' -o -iname '*Addend*'
```

Show the current "Issue Addendum" form JSX — specifically the "Extend Deadline" checkbox and any conditional date field rendering.

### 1.7 Check existing settings keys (run inside container)

```bash
docker compose exec app php artisan tinker --execute="
\$keys = App\Models\Setting::query()
    ->where('key', 'like', '%deadline%')
    ->orWhere('key', 'like', '%opening%')
    ->orWhere('key', 'like', '%buffer%')
    ->get(['key','value','group']);
echo \$keys->toJson(JSON_PRETTY_PRINT);
"
```

Confirm whether anything like `tender.min_hours_between_deadline_and_opening` already exists. If not, identify the seeder file (`database/seeders/SettingsSeeder.php` likely).

### 1.8 Reproduce the bug state on production data (read-only)

```bash
docker compose exec app php artisan tinker --execute="
echo App\Models\Tender::where('reference','MBP-T008')
    ->select('reference','submission_deadline','opening_date')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

This may or may not hit prod data depending on your local DB state — if local doesn't have `MBP-T008`, that's fine, the production screenshot from April 27 already documents the state. Report what local sees.

### 1.9 Check for tenders in the bad state (whole DB)

```bash
docker compose exec app php artisan tinker --execute="
\$bad = App\Models\Tender::whereColumn('submission_deadline', '>=', 'opening_date')
    ->select('id','reference','submission_deadline','opening_date')->get();
echo 'Bad-state tenders (local): ' . \$bad->count() . PHP_EOL;
echo \$bad->toJson(JSON_PRETTY_PRINT);
"
```

Adjust column names per Phase 1.4 findings if needed. Report count and references — this is local-DB only at this point. Production check happens in Phase 4.

### 1.10 Existing Pest coverage

```bash
find tests -iname '*Addend*'
```

Show what's already tested for addendum issuance — we'll extend, not duplicate.

**STOP HERE and report Phase 1 findings before continuing.** I want to see the audit table before you write code. If anything in the audit contradicts the implementation plan in Phase 2 (e.g., column names differ, the addendum already stores `new_opening_date` and the bug is elsewhere), flag it explicitly.

---

## Phase 2 — Implementation Plan

Once Phase 1 is reviewed and confirmed, implement the fix in this order. Stop and confirm with me **only if** Phase 1 surfaced something that contradicts the plan below; otherwise proceed straight through Phase 2 → 3 → 4.

### 2.1 Settings: add buffer config

Add a new setting key. Strategy A applies — DB stores the slug, React renders via `t()`:

- **Key:** `tender.min_hours_between_deadline_and_opening`
- **Default value:** `24` (hours)
- **Type:** integer
- **Group:** `tender` (or whatever group the existing tender-related settings live in — confirm from Phase 1.7)

Add the seeder entry. Run inside container:

```bash
docker compose exec app php artisan db:seed --class=SettingsSeeder
```

Verify it landed:

```bash
docker compose exec app php artisan tinker --execute="echo App\Models\Setting::where('key','tender.min_hours_between_deadline_and_opening')->first()?->toJson();"
```

Add the corresponding label/description i18n keys to `lang/en.json` and `lang/ar.json`:

```json
"settings.tender.min_hours_between_deadline_and_opening.label": "Minimum hours between submission deadline and opening date",
"settings.tender.min_hours_between_deadline_and_opening.description": "Buffer enforced when creating tenders or extending deadlines via addenda."
```

Arabic equivalents — use the same procurement terminology as existing keys (`المناقصة`, `الموعد النهائي`, `موعد فتح العروض`). Don't invent new terms; mirror what's already in `lang/ar.json` for consistency.

### 2.2 Form Request validation

In `AddendumRequest` (or whatever the actual class is named — confirm from Phase 1.3):

- Add conditional validation: when `extend_deadline === true`, require `new_submission_deadline` AND `new_opening_date`.
- Both must be `date|after:now`.
- `new_opening_date` must be `after:new_submission_deadline` by at least `Setting::get('tender.min_hours_between_deadline_and_opening')` hours. Implement as a custom rule class (`MinHoursAfter`) so it's reusable for `UpdateTenderRequest` later.
- Use `__()` for all error messages with both `en` and `ar` translations added.

### 2.3 Controller / service logic

In the addendum write path:

- When `extend_deadline` is true:
  - Capture audit data **before** save (remember the `getOriginal()` post-save gotcha — capture old values into a variable first).
  - Update `tender.submission_deadline = $validated['new_submission_deadline']`
  - Update `tender.opening_date = $validated['new_opening_date']`
  - Persist both old/new values to the audit log for both fields.
  - Wrap the tender update + addendum creation in `DB::transaction(...)` so a partial write can't produce the same bug state we're fixing.
- Toast on success: `Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.addendum.issued_with_deadline_extension')])`
- Toast on failure (caught): `Inertia::flash('toast', ['type' => 'error', 'message' => __('messages.addendum.failed')])`

Add the new `messages.addendum.*` keys to both language files.

### 2.4 React form (Issue Addendum)

In the React component identified in Phase 1.6:

- When the "Extend Deadline" checkbox is checked, render **two** date-time fields side-by-side: "New Submission Deadline" and "New Opening Date".
- Pre-fill `new_opening_date` reactively as `new_submission_deadline + buffer_hours` whenever `new_submission_deadline` changes (read buffer from Inertia shared props — pass it from `HandleInertiaRequests` middleware so it's globally available).
- Both fields use `useForm()` from `@inertiajs/react`, both show server-side validation errors via the standard error display pattern used elsewhere in the project.
- RTL: ensure the two-field layout uses logical CSS (`gap-*`, `grid-cols-2`, no physical `ml-*`/`mr-*`) so it doesn't regress BUG-01 territory.

### 2.5 Backfill check (production-aware, read-only)

Write a one-off Tinker check (not a migration — this is a check, not a schema change):

```bash
docker compose exec app php artisan tinker --execute="
\$bad = App\Models\Tender::whereColumn('submission_deadline', '>=', 'opening_date')
    ->select('id','reference','submission_deadline','opening_date')->get();
echo 'Bad-state tenders: ' . \$bad->count() . PHP_EOL;
echo \$bad->toJson(JSON_PRETTY_PRINT);
"
```

**Run against production via Laravel Cloud's tinker** (or whatever access pattern Johnny's used before — typically via the Cloud dashboard's tinker tab). If the count > 0, list them in the Phase 4 report so the procurement officer can review manually. **Do not auto-fix production data.** The fix is for new addenda; existing bad-state tenders need a human decision.

### 2.6 Pest tests

Add to the existing addendum test file (don't create a new one if `tests/Feature/AddendumTest.php` or similar already exists — confirm from Phase 1.10):

1. **Happy path:** `it('cascades opening date when addendum extends deadline')` — issue addendum with both new dates, assert tender's `submission_deadline` and `opening_date` both updated, assert `opening_date > submission_deadline + buffer_hours`.
2. **Validation: missing new_opening_date:** `it('rejects deadline extension without new opening date')` — assert 422 with the specific error key.
3. **Validation: opening before deadline:** `it('rejects deadline extension when new opening date is before new submission deadline')` — assert 422.
4. **Validation: buffer violated:** `it('rejects deadline extension when buffer between deadline and opening is too small')` — set buffer to 24h via `Setting::set(...)`, attempt with 1h gap, assert 422.
5. **Audit log:** `it('writes audit log entries for both submission_deadline and opening_date when cascading')` — assert two audit log rows with correct old/new values.
6. **Transaction rollback:** `it('rolls back tender date changes if addendum creation fails')` — force the addendum insert to throw (use a model event or a malformed payload), assert tender dates remain at original values.
7. **No-cascade case:** `it('does not modify opening date when extend_deadline is false')` — addendum without deadline extension leaves both dates untouched.

Run the addendum tests inside the container:

```bash
docker compose exec app vendor/bin/pest --filter=Addendum
```

Then full suite to catch regressions:

```bash
docker compose exec app vendor/bin/pest
```

### 2.7 BUGS.md update

**Don't remove BUG-26 from BUGS.md** in this commit — that's the project convention (closed bugs are removed in a separate housekeeping pass, recoverable via git log). Just commit the fix referencing `BUG-26` in the message so it's findable later.

You may optionally append a closing bullet under BUG-26 after verification:

```
  Closed 2026-MM-DD (commit `<short-sha>`): cascade implemented in `AddendumController::store`,
  buffer enforced via `MinHoursAfter` custom rule, 7 Pest tests passing, production backfill
  identified N bad-state tenders flagged for procurement review (see verification report).
```

---

## Phase 3 — Verification with Chrome DevTools MCP (local stack first)

Standard sequence on the local stack:

1. Login as admin, create a test tender with submission_deadline = `now+5d`, opening_date = `now+6d`.
2. Issue an addendum with `extend_deadline=true`, `new_submission_deadline=now+10d`, leave `new_opening_date` blank.
3. `take_snapshot` → assert validation error appears for missing opening date.
4. Fill `new_opening_date=now+11d`, submit.
5. `take_snapshot` of the tender Overview tab → assert both Submission Deadline shows `now+10d` AND Opening shows `now+11d`.
6. `list_network_requests` → confirm the X-Inertia request returned 200 and toast flashed.
7. `evaluate_script` → run `document.body.innerText.includes('successfully')` or whatever the success toast text resolves to.
8. Switch language to Arabic (BUG-01 territory — sidebar still wrong, ignore for now), `take_screenshot` to verify both date fields render and toast appears in Arabic.
9. RTL date-field layout sanity check: `take_screenshot` of the addendum form in Arabic mode.
10. Save screenshots to `docs/bug-26-addendum-cascade-en.png`, `docs/bug-26-addendum-cascade-ar.png`, `docs/bug-26-validation-errors.png`.

If any step fails, stop and report. Don't push a green-but-broken fix.

---

## Phase 4 — Build, Deploy, Verify on Production

1. Run the addendum tests inside container: `docker compose exec app vendor/bin/pest --filter=Addendum`
2. Run full Pest suite: `docker compose exec app vendor/bin/pest` — catch regressions.
3. Format check: `docker compose exec app vendor/bin/pint`
4. TypeScript check (host, per TECH-DEBT-07): `npx tsc --noEmit`
5. Frontend build (host, per TECH-DEBT-07): `npm run build`
6. Clear caches inside container: `docker compose exec app php artisan optimize:clear`
7. Run the backfill check (Phase 2.5) against **production** via Laravel Cloud tinker. Report the list of any bad-state tenders found — these need human review, not auto-fix.
8. Stage:
   ```bash
   git add app/ database/migrations/ database/seeders/ lang/en.json lang/ar.json resources/js/ tests/Feature/ docs/bug-26-*.png
   git status --short
   ```
   Confirm only expected paths are staged.
9. Commit:
   ```
   fix(BUG-26): cascade opening_date when addendum extends submission deadline

   - Add tender.min_hours_between_deadline_and_opening setting (default 24h)
   - AddendumRequest now requires new_opening_date when extend_deadline=true,
     enforces buffer via custom MinHoursAfter rule
   - AddendumController wraps tender update + addendum insert in DB::transaction
   - Audit log captures old/new for both submission_deadline and opening_date
   - React form exposes both date fields side-by-side, pre-fills opening date
     as new_deadline + buffer_hours
   - Pest: 7 new tests covering cascade, validation, audit, transaction rollback
   - i18n: en + ar keys for new setting + new toast messages
   - Backfill check identified N bad-state tenders flagged for procurement review
     (see BUG-26 verification report)
   ```
10. Push: `git push origin master`. Auto-deploy to Laravel Cloud.
11. Re-run Phase 3 verification on production URL (`https://mpc-etender-master-ad2keh.laravel.cloud/`) once deployed.

---

## Final Report

Deliver a Phase 4 summary table:

| Item | Status |
|---|---|
| Phase 1 audit complete + reviewed | ✓/✗ |
| Settings key added + seeded | ✓/✗ |
| AddendumRequest validation updated | ✓/✗ |
| Controller transaction wrap | ✓/✗ |
| Audit log captures both fields (pre-save capture) | ✓/✗ |
| React form shows both date fields | ✓/✗ |
| RTL layout verified | ✓/✗ |
| Pest tests (7 added) | X passing / Y total |
| TypeScript check (host) | ✓/✗ |
| `npm run build` succeeded (host workaround per TECH-DEBT-07) | ✓/✗ |
| `vendor/bin/pint` clean | ✓/✗ |
| Backfill check on prod — bad-state tenders found | N (list references) |
| Local Chrome DevTools verification | ✓/✗ |
| Production smoke test post-deploy | ✓/✗ |
| Step 7 of test plan now unblocked | ✓/✗ |

---

## Hard guardrails

**Phase 1 first. Show the audit before any code changes.**

If Phase 1 reveals the bug is elsewhere (e.g., column names differ, addendum already stores both new dates and the controller is dropping `new_opening_date`), stop and report — the implementation plan above assumes a specific shape of the bug and may need adjustment.

If `docker compose exec app vendor/bin/pest` fails for reasons unrelated to addendum tests (e.g., the 3 pre-existing test failures in `Auth/RegistrationTest` + `Settings/ProfileUpdate*` already in the open task list), note them in the report but don't try to fix them in this commit — that's separate work.

If `npm run build` fails on the host with anything *other* than the known TECH-DEBT-07 rolldown error, stop and report.

---

## What's queued after this lands

1. **BUG-03 + BUG-29 i18n batch** — mechanical Strategy A cleanup, can run anytime after BUG-26.
2. **BUG-28 full enforcement build (5–7d)** + **TECH-DEBT-09 column drop** — scheduled after BUG-26 verification on prod.
3. **BUG-27 admin dashboard scope** — waiting on sponsor/PM call.
4. **Step 7 of test plan** (vendor bid submission against `MBP-T008`) — unblocked the moment BUG-26 is verified on prod.
