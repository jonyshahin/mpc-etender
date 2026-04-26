# Claude Code Prompts — Versioned Archive

This directory holds every Claude Code prompt issued for the MPC e-Tender project. Each prompt is a standalone `.md` file that can be pasted directly into a `claude --resume "mpc-etender-web"` session.

## Naming convention

- `PROMPT_BUG_NN_BRIEF_DESCRIPTION.md` — bug fix prompts
- `PROMPT_BUG_NN_INVESTIGATION.md` — read-only audit prompts (verdict required before fix)
- `PROMPT_BUG_NN_TRIAGE_DESCRIPTION.md` — interim mitigations or scope adjustments
- `PROMPT_TECH_DEBT_NN_DESCRIPTION.md` — tech debt cleanup
- `PROMPT_FEATURE_DESCRIPTION.md` — new features
- `PROMPT_BOOTSTRAP_DESCRIPTION.md` — repo-structure / convention changes

## Why version prompts

1. **Audit trail:** what was asked of Claude Code and when, separate from what Claude Code did (commits).
2. **Replay:** a prompt can be re-issued (with adjustments) without retyping.
3. **Refinement:** patterns that work surface; patterns that don't can be improved over time.
4. **Onboarding:** future engineers/PMs see how the project was driven.

## Lifecycle

- A prompt is committed to this directory before or alongside the work it triggers.
- After the work lands, the prompt stays — it's archive, not scratch.
- If a prompt is superseded mid-execution (e.g., audit revealed the plan needed adjustment), commit the revised version with a `_v2` suffix and a note in the file's first line referencing the original.

## Status of each prompt

Tracked in the commit message of the work it triggered, and reflected in `docs/PM_BACKLOG.md` (Recently shipped section). This file is just the archive — it doesn't double as a status board.
