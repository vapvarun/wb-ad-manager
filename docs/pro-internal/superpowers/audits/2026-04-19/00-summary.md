# Audit Summary — 2026-04-19 (Phase A complete)

**Scope:** wb-ad-manager-pro `1.5.0` (HEAD `90436ec`) + wb-ads-rotator-with-split-test `2.8.0` (HEAD `9f38b51`)

Six passes run (Pass 5 sampled; others full). No code changed in Phase A.

---

## Severity totals

| Pass | P0 | P1 | P2 |
|------|-----|-----|-----|
| 1 — Settings inventory | 22 | 18 | 7 |
| 2 — Setting → consumer map | 4 | 18 | 92 |
| 3 — Template wiring | **0** ✅ | ~30 | ~5 |
| 4 — Hook / filter coverage | 18+45+9 | ~23 | — |
| 5 — Promise vs behavior (sampled 12) | 1 | 4 | — |
| 6 — Developer experience | 1 (Task C FORK-REQUIRED) | 2 | 1 |

**Dev-experience grade:** HARD (4/10) — plugin markets extensibility but requires forking on several high-value tasks.

**Security grade:** STRONG — zero missing escapes, zero missing nonces, zero ABSPATH-guard gaps, zero capability-check gaps on admin templates.

---

## The P0s that block the marketing push

Ordered by blast radius (worst first):

### 1. ~~`auto_approve_paid_ads` revenue leak~~ RETRACTED
- Pass 5 agent flagged this as a P0 revenue leak. On code re-trace during Batch A, the concern does not hold: `submit_ad()` guards balance at line 137 and auto-approved submissions immediately call `activate_ad()` which charges credits. The `$package->price > 0` check correctly distinguishes paid vs free packages. No revenue leak.
- Demoted to P2 polish: tighten setting description.

### 2. Cross-plugin settings leak (Pass 2)
- Pro's `Keyword_Linker` reads Free plugin's `wbam_settings` directly
- **Impact:** if Free plugin deactivated, Pro behavior silently changes
- **Fix:** route through `Settings_Helper::get_from_free()` abstraction or copy the relevant keys on Pro activation

### 3. Free-Pro settings overlap — 7 keys (Pass 1)
- Currency, page IDs, link prefix stored in BOTH plugins' option arrays
- **Impact:** mode-switching or upgrade path can lose data
- **Fix:** declare Pro as authoritative for overlapping keys; Free reads from Pro when Pro is active

### 4. 12 unsanitized settings (Pass 1)
- Module configurations, license keys, page options, credits URLs written without `sanitize_callback`
- **Impact:** XSS / stored-script risk via admin-only path (requires admin access, so low severity — but still P0)
- **Fix:** add `sanitize_callback` to each `register_setting()` call

### 5. 5 `wbam_page_*` keys bypass Settings API (Pass 1)
- No `register_setting()` call at all
- **Impact:** no sanitization, no defaults via Settings API, no REST exposure
- **Fix:** register each key with proper schema + sanitizer

### 6. 9 REST endpoints use `__return_true` permission (Pass 4)
- Package, Classified, Campaign GET APIs open to anyone
- **Impact:** enumerate packages/classifieds/campaigns from any client
- **Fix:** switch to explicit `Rest_Permissions::can_read_{resource}` returning `WP_Error` on failure

### 7. 18 write methods have no `before_` hook (Pass 4)
- Advertiser/Campaign/Classified/Package/Membership `create`/`update`/`delete` have no filterable validation gate
- **Impact:** approval workflows, custom validation, anti-spam tooling — all require fork
- **Fix:** add `apply_filters('wbam_before_{action}_{entity}', …, $data)` with `WP_Error`-able return

### 8. 45 REST endpoints have no response-shape filter (Pass 4)
- Cannot add custom fields, conditional visibility, legacy transforms
- **Impact:** blocks every 3rd-party integration from customizing API output
- **Fix:** wrap each endpoint's response with `apply_filters('wbam_rest_prepare_{resource}', $response, $request)`

### 9. Task C (custom submission field) is FORK-REQUIRED (Pass 6)
- No `do_action` slots in the ad wizard between steps
- No filter on submission data
- **Impact:** any custom-field requirement requires forking the wizard
- **Fix:** add ~4 `do_action` slots in `templates/portal/ad-form.php` + one filter in `Ad_Submission_Manager::submit_ad`. 6-hour fix, moves DX score up meaningfully.

### 10. `ad_submissions` module OFF still shows `[wbam_ad_form]` shortcode (Pass 5)
- **Fix:** gate shortcode registration on module flag

### 11. Overview / Recent Activity / Stat cards ignore Site Mode (site-mode-respect-gaps plan)
- Already planned — Batch 1 of that plan covers this

### 12. 3 dead-read settings referenced but never written (Pass 1)
- `wbam_format_matching_enabled`, `wbam_pro_rest_legacy_routes`, `wbam_bump_price`
- **Fix:** remove references or ship the feature

---

## P1 highlights

- **58 BYPASS-HELPER** calls — `get_option('wbam_pro_settings')` instead of `Settings_Helper::get()`
- **89 NOT-FILTERABLE** setting reads — no `apply_filters` wrapper
- **Rotation_Engine** refetches settings on every ad render (perf hotspot at scale)
- **34 email templates** call `get_option('date_format')` directly (architecture)
- **2 DB calls in `portal/tabs/ads.php`** and indirect ones across 7 more portal templates
- **11 inline `<script>` blocks** with logic in templates (should be enqueued JS)
- **Inconsistent hook naming** — both `wbam_before_X_Y` and `wbam_X_before_Y` exist

---

## P2 highlights

- Missing `@var` docblocks in some portal templates
- Missing `@api`/`@since` markers on public classes (hurts IDE autocomplete)
- No `docs/HOOKS.md` reference doc
- Some `private` methods should be `protected` to unblock subclassing

---

## Fix budget — Phase C batches

Ordered by dependency and blast radius.

### MANDATORY for every batch — data-flow safety gate

**Zero-regression rule. No batch merges until the gate passes.**

Before touching code in any batch:

1. **Snapshot the data.** Dump counts + a hash of row IDs from every affected table:
   - `wbam_ads`, `wbam_campaigns`, `wbam_advertisers`, `wbam_classifieds`, `wbam_classified_meta`, `wbam_wallet_transactions`, `wbam_packages`, `wbam_reports`, plus postmeta for `_wbam_*` keys.
   - Save as `docs/superpowers/audits/2026-04-19/snapshots/{batch}-before.txt`.

2. **Map the data flow** the batch touches. For each file changed, list:
   - Which table / meta / option it reads
   - Which it writes
   - Which hook / filter it fires
   - Which REST / AJAX route it serves
   - What the existing integration tests cover (if none, flag)

3. **Write acceptance tests FIRST** (TDD). One PHPUnit test per plan item referencing the plan line. Tests must FAIL initially.

4. **Implement.** Narrow diff, one concern per commit.

5. **Re-snapshot.** Compare counts + hashes. Any row delta that is NOT explained by the plan item is a regression — revert.

6. **Browser-verify the golden path + edge cases** per item via Playwright MCP. Screenshot every visible surface that the item touches. No batching screenshots to the end.

7. **Hook audit.** Confirm no `before_` / `after_` hook changed signature (breaks 3rd-party extensions). Signature changes need a new hook, not a mutated one.

8. **Module dependency recheck.** Run `/ads` mode switch matrix (Publisher → Sponsored → Paid → Classifieds → Full) and confirm nothing regressed in any mode.

**If any step fails, revert the batch and re-plan.** No partial merges, no TODO markers.

---


### Batch A — Revenue + Security (P0, must land before marketing push)

1. `auto_approve_paid_ads` logic fix (1 line)
2. Unsanitized settings — add `sanitize_callback` to 12 keys
3. `wbam_page_*` — register via Settings API (5 keys)
4. REST `__return_true` → proper permission callbacks (9 endpoints)
5. Cross-plugin settings leak — abstraction layer
6. Free-Pro overlap — declare authoritative source

**Estimated effort:** 1 full day

### Batch B — Developer Experience (P0 for marketing credibility)

7. Add ~18 `before_` filters to manager write methods
8. Add ~45 `rest_prepare` response filters to REST endpoints
9. Add `do_action` slots to ad wizard (Task C fix)
10. Filter on ad submission data (Task C fix)
11. Document public API with `@api`/`@since` markers

**Estimated effort:** 2 days

### Batch C — Architecture Hygiene (P1, ship alongside B for quality)

12. Refactor `BYPASS-HELPER` calls → `Settings_Helper::get()`
13. Move DB calls out of `portal/tabs/ads.php` into loader
14. Move USERMETA reads out of 7 portal templates into loader
15. Email `View_Data::prepare()` helper — centralize `date_format`/`blogname`/`admin_email` plumbing
16. Rotation_Engine settings caching (perf)
17. Extract inline `<script>` logic to enqueued JS

**Estimated effort:** 2 days

### Batch D — Polish (P2, nice-to-have)

18. Consistent hook naming
19. `docs/HOOKS.md` reference
20. `@var` docblocks everywhere
21. Promise-vs-behavior full sweep (expand Pass 5 from 12 to all)

**Estimated effort:** 1 day

### Batch E — Site Mode Respect (separate plan, can run in parallel)

See `docs/superpowers/plans/2026-04-19-site-mode-respect-gaps.md`

---

## Triage recommendation

**Do Batch A + Batch B before the marketing push.** These are the 3 days that turn this from a plugin we can't honestly market as "developer friendly" into one we can.

**Batch C runs alongside B** for two pragmatic reasons:
- Fixing wiring now prevents the next audit pass from flagging the same things
- Mid-refactor, keeping the BYPASS-HELPER pattern discoverable is costly

**Batch D can slip** past marketing — polish, not correctness.

**Site-mode-respect (Batch E)** should land with A/B — currently the mode picker is the most visible feature and its gaps are the easiest for a prospect to find.

---

## What's strong (don't regress)

- Escaping hygiene across 71 templates
- Nonce coverage on every POST form
- Capability checks on every admin render
- ABSPATH guards universal
- Responsive CSS at ≤1024 + ≤640 everywhere
- Template override pattern works
- 23 `after_` action hooks already fire (writers are stronger than readers on hook surface)
- Site Mode registry + `_mode_applied` opt-in gate preserves existing customers

---

## Open questions for Varun

1. **Batch A timing** — start immediately, or wait for Phase B triage session?
2. **Marketing date** — when does the push start? Batch A+B fit in ~3 days; confirm timeline.
3. **Expand Pass 5?** — sampled 12, found 2 broken promises; full sweep is 1 day. Do it?
4. **Who owns Batch C?** — refactor-heavy, can delegate to sub-agent flow?

---

## Phase B

Phase B (triage) is a session with Varun + this summary to:
- Confirm the P0 list
- Reorder batches if priorities shift
- Produce one plan doc per batch
- Lock commit SHAs for each batch

Recommended: 30-min sync.
