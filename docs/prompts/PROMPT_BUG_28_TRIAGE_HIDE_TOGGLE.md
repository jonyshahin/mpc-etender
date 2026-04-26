# Claude Code Prompt — BUG-28 Triage: Hide Toggle (Interim Mitigation)

> **Status:** EXECUTED 2026-04-27. Commit: `be3631e`. See `BUGS.md` BUG-28 interim mitigation bullet. Archived here for reference.

The original prompt's structure was 5 phases:

1. **Locate toggle** — audit the React UI to find where the "2FA mandatory for internal users" checkbox is rendered. Phase 1 surfaced that the admin Settings page is data-driven (single generic `renderSettingInput` switch over `Setting.type`) rather than per-toggle JSX. Pattern A (inline early-return keyed on `'security.2fa_mandatory_internal'`) was selected.
2. **Make UI change** — disable + force `checked={false}` + add "Coming soon" badge using existing `<Badge variant="secondary">` primitive; mute helper text via `text-muted-foreground`; use logical `ms-2` margin for RTL.
3. **Chrome DevTools verification** — local en + ar snapshots, screenshots, `evaluate_script` to confirm click is a no-op.
4. **BUGS.md updates** — severity history clarified, TECH-DEBT-09 entry opened (redundant `users.is_2fa_enabled` column), interim mitigation bullet appended under BUG-28 with verification screenshot paths.
5. **Build / deploy / report** — Pint check, host TypeScript + Vite build (per TECH-DEBT-07), commit + amend SHA, push.

Hard guardrails were: no backend changes, no migrations, no PHP files, frontend-only. The persisted setting value in `system_settings` was deliberately left untouched — flipping it from the UI fix would be a behavior change masquerading as a UI fix.

For the canonical full prompt body, see Johnny's local `outputs/PROMPT_BUG_28_TRIAGE_HIDE_TOGGLE.md`.
