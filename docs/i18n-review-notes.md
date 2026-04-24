# i18n Review Notes

Operational log for translation quality review. Each entry records
translations where the contributor had to make a judgment call — so the
next native-speaker reviewer spends their time verifying specific strings,
not re-reading the whole locale file.

## How to use this file

- **Translation contributor** (human or AI) adds entries when making a
  non-trivial word choice. Include the key, the chosen value, alternatives
  considered, and the reason for the pick.
- **Native reviewer** reads only the entries under "Awaiting review",
  approves or corrects, then moves the entry to "Reviewed" with their
  initials and the date. If they correct: edit the SORANI map in
  `scripts/build-ku-locale.mjs` (Sorani) or the JSON directly (Arabic /
  English), then regenerate ku.json and commit.
- Keep entries short. If a review takes more than 15 minutes the file has
  grown too big — trim approved entries older than 6 months.

## Locale source files

| Locale | Source | Editor |
|---|---|---|
| English (`en`) | `lang/en.json` | Edit JSON directly |
| Arabic (`ar`) | `lang/ar.json` | Edit JSON directly |
| Sorani Kurdish (`ku`) | `scripts/build-ku-locale.mjs` — `SORANI` map | Edit script, then `node scripts/build-ku-locale.mjs` to regenerate `lang/ku.json` |

---

## Sorani — awaiting MPC Kurdish-reader review

First Sorani pass, 122 keys covering welcome + pre-auth surfaces. Deployed
in commit `9a2f226` (2026-04-24). The 7 flagged entries below were choice
points where the contributor leaned on Iraqi-Kurdish procurement register
and Arabic loanwords over literal coinages. Reviewer to confirm or correct.

### 1. `welcome.hero_line_1` — "tender" terminology

**Chosen:** `بەڕێوەبردنی مناقەسە،`

**Alternatives considered:**
- `پێشبڕکێ` — means "competition", loses procurement specificity
- `کڕدن` — means "buying", loses competitive-bidding sense

**Reason:** `مناقەسە` is the standard Iraqi-Kurdish procurement term
(Arabic loanword widely used in Kurdistan Region procurement documents).

### 2. `welcome.hero_line_2` — "end to end"

**Chosen:** `لە سەرەتاوە تا کۆتایی.`

**Alternatives considered:**
- `سەرتاسەرییانە` — "comprehensively", more concise but changes register
- Direct transliteration of "end to end"

**Reason:** Literal "from beginning to end" reads natural in Sorani; no
native idiom for "end to end". Preserves the English heading's rhythm.

### 3. `welcome.cta_register_vendor` + all `دابینکەر` uses — "vendor / supplier"

**Chosen:** `دابینکەر` (dabînker)

**Alternative considered:** `فرۆشیار` ("seller")

**Reason:** Procurement context is supply-side; `دابینکەر` is the correct
register. `فرۆشیار` implies retail sales.

### 4. `welcome.card_sealed_title` — "sealed bidding"

**Chosen:** `پێشنیاری داخراو` (closed/sealed proposal)

**Reason:** Standard Iraqi-Kurdish procurement term. No alternatives seemed
worth flagging.

### 5. `welcome.card_committee_desc` — "two-envelope"

**Chosen:** `پشتگیری سیستەمی دوو ئەمباڵۆ`

**Alternative considered:** `پاکەت` (Kurdish native for "packet/parcel")

**Reason:** `ئەمباڵۆ` (envelope, Arabic loan) is the procurement-specific
term; `پاکەت` usually means something different in daily Sorani.

### 6. `welcome.card_prequal_title` — ZWNJ rendering

**Chosen:** `پێش‌مەرجی دابینکەران`

**Note:** There is a Zero-Width Non-Joiner (U+200C) between `پێش` and `مەرج`
to prevent undesired glyph joining. Verified rendering correctly in Chrome
on production (post-deploy screenshot of /welcome in Sorani). If a reviewer
sees this display wrong in their tooling, the character is present in the
source; the issue would be font-level, not translation-level.

### 7. `auth.staff_verify_email_description` — "click on"

**Chosen:** `کرتەکردن لەسەر` for "click on"

**Reason:** Standard Sorani UI phrasing; no alternative seemed worth
flagging.

### 8. `form.show_password` / `form.hide_password` (added in `9a2f226+1`)

**Chosen:**
- `form.show_password` = `نیشاندانی وشەی نهێنی`
- `form.hide_password` = `شاردنەوەی وشەی نهێنی`

**Reason:** Straightforward verb-noun-noun construction. `نیشاندان` (to
show) and `شاردنەوە` (to hide) are the idiomatic UI verbs.

---

## Reviewed (Sorani)

_None yet. First review pass pending._

---

## Reviewed (Arabic)

_None yet. Claude Code contributed the Arabic welcome.* and auth.staff_*
translations in commit `9a2f226`; these have not been native-reader
reviewed._

Specific Arabic judgment calls worth future review:

- `welcome.hero_subhead`: chose `المشتريات الإنشائية` ("construction
  procurement") over `مشتريات البناء` ("building buying"). Former reads
  more formal-register.
- `welcome.card_sealed_desc`: `خوارزمية AES-256` keeps the crypto term
  in its borrowed Arabic form, standard in modern technical Arabic.
- `welcome.cta_register_vendor`: `تسجيل مقاول جديد` (register new
  contractor). Could also be `تسجيل مورد` (register supplier). Chose
  `مقاول` (contractor) because MPC's procurement is construction-heavy.

---

## When Kurdish coverage expands post-auth

The 903 keys currently carrying `[en] ` prefix in `lang/ku.json` are NOT
untranslated-by-oversight — they are untranslated by scope decision. See
the SORANI map comment in `scripts/build-ku-locale.mjs` for the rationale.

When Kurdish scope expands to a new surface (e.g., vendor dashboard):

1. Identify the keys that surface renders (`grep` t() calls in the page).
2. Add those keys to the SORANI map in `scripts/build-ku-locale.mjs` with
   Sorani translations.
3. Flag new judgment calls in the "Awaiting review" section above.
4. Run `node scripts/build-ku-locale.mjs` to regenerate `lang/ku.json`.
5. Commit the script change + the regenerated JSON together.

Don't translate in isolation. Hand the reviewer a specific list of newly
flagged entries, not the full locale file — same principle as the current
7-entry batch.
