# Plan — Full Plugin Audit: Settings, Templates, Wiring, Developer Experience

**Date:** 2026-04-19
**Branch:** 1.5.0
**Scope:** End-to-end audit of how settings are stored, read, consumed by templates, exposed to developers, and whether shipped behavior matches what the UI promises to site owners.

---

## Why now

The plugin has grown organically: Site Mode, Classifieds, Wallet, Campaigns, Packages, Rotation, Links, Analytics, Advertisers, BuddyPress, Memberships, AB Testing — each added its own settings, its own admin tab, its own template assumptions. The pieces work individually but we have never stood back and verified:

1. Every setting exposed to the site owner actually changes runtime behavior.
2. Every template reads settings the same way.
3. Every module exposes the filters/actions a developer needs to customize it.
4. The promises the admin UI makes ("toggle module X off and Y will stop") hold across all code paths (serve, admin, email, REST, AJAX, cron).
5. Extension points are consistent (`wbam_*` prefix, `apply_filters` on defaults, before/after hooks on writes).

We are about to market this to new site owners. The audit locks in quality before volume.

---

## Audit methodology

Six passes. Each pass produces a findings file under `docs/superpowers/audits/2026-04-19/`. Each finding is one of three severity levels:

- **P0 — Broken promise.** UI toggles a setting but behavior doesn't change.
- **P1 — Inconsistency / footgun.** Works but a developer or site owner will trip on it.
- **P2 — Developer-experience gap.** Missing filter/action, stale doc, unclear contract.

Findings become fix batches (separate plan docs) in priority order.

---

## Pass 1 — Settings inventory

**Deliverable:** `docs/superpowers/audits/2026-04-19/01-settings-inventory.md`

**Method:** Grep every `get_option`, `update_option`, `Settings_Helper::get`, `Settings_Helper::update`, `wbam_pro_settings`, module-specific `wbam_{module}_settings`, and all `register_setting()` calls. Build a table: setting key → where it's written → where it's read → default value → type/sanitizer → user-facing label.

**Look for:**
- Settings read but never written (dead)
- Settings written but never read (dead)
- Settings with no sanitizer
- Settings whose default is hardcoded in 3+ places (DRY violation)
- Duplicate keys (e.g., `wbam_stripe_key` in options AND `wbam_pro_settings.stripe_key`)

**Catch:** typically 20–40 drifted keys accumulate across a plugin this size.

---

## Pass 2 — Setting → consumer wiring

**Deliverable:** `docs/superpowers/audits/2026-04-19/02-setting-consumer-map.md`

**Method:** For each setting from Pass 1, trace every file that reads it. Classify consumer: template, module class, shortcode, REST endpoint, AJAX handler, cron job, email template, admin renderer.

**Look for:**
- Settings read directly via `get_option()` instead of `Settings_Helper::get()` (bypass sanitizer + defaults)
- Templates that read options directly instead of receiving via `$view_data`
- Hardcoded values in templates that should be settings-driven
- Modules that fetch settings on every hook (should cache per request)

**Catch:** the biggest source of promise-vs-behavior drift.

---

## Pass 3 — Template wiring

**Deliverable:** `docs/superpowers/audits/2026-04-19/03-template-wiring.md`

**Method:** For every file under `templates/`:

1. List the variables expected (from header docblock `@var`).
2. Compare against what the loader actually passes.
3. Check for direct DB calls, direct `get_option` / `get_user_meta`, direct `wpdb` queries (all forbidden per skill).
4. Check escaping on every output (no bare variables, `esc_html` vs `esc_attr` vs `wp_kses_post` correct).
5. Check for theme-override path (`themes/{theme}/wb-ad-manager-pro/{template}.php` should work).
6. Check responsive CSS (every template that ships layout must have `@media` for ≤640px).

**Look for:**
- Templates violating "zero logic in template" rule
- Templates mixing loader responsibilities with render responsibilities
- Templates without `@var` declarations
- Templates with inline `<style>` or `<script>` blocks (should be enqueued)

**Catch:** per our skill, templates must be dumb render layers. We're likely leaking logic.

---

## Pass 4 — Hook / filter coverage

**Deliverable:** `docs/superpowers/audits/2026-04-19/04-hook-coverage.md`

**Method:** For each write operation (create, update, delete) across all managers (Advertiser, Campaign, Ad_Submission, Package, Classified, Wallet, Report, Link, etc.), verify:

- `before_{action}_{entity}` filter exists and returns `WP_Error`-able
- `after_{action}_{entity}` action exists and fires post-commit
- REST response shape filter exists (`wbam_rest_prepare_{resource}`)
- Query args filter exists (`wbam_{resource}_query_args`)
- Permission map is filterable

**Look for:**
- Write paths with no before/after hooks
- Inconsistent naming (`wbam_before_ad_create` vs `wbam_ad_before_create`)
- Missing query filters (forces developers to fork the plugin to add custom WHERE clauses)
- Missing response filters on REST endpoints

**Catch:** this is where "developer-friendly" lives or dies.

---

## Pass 5 — Promise-vs-behavior audit

**Deliverable:** `docs/superpowers/audits/2026-04-19/05-promise-behavior.md`

**Method:** Read every setting's description / help text as shown in admin UI. For each, design a minimum test: flip the setting, perform the action the description implies, assert the described behavior (or its absence).

**Examples:**
- "Disable guest viewing of ads" → load a classified as logged-out → must be redirected or gated
- "Send weekly digest email" → advance cron → inbox should receive digest
- "Require campaign approval" → submit ad → must land in pending state
- "Enable BuddyPress integration" → with BP active → profile should show ads widget
- "Classifieds Marketplace mode" → switch → all ad-related menus hidden (verified earlier)

**Look for:**
- Toggles that lie (setting saved, behavior unchanged)
- Toggles that only partially fire (UI hidden but endpoint still served)
- Toggles that depend on another toggle (undocumented coupling)

**Catch:** the user-facing trust damage class. Every drifted toggle is a refund.

---

## Pass 6 — Developer-experience audit

**Deliverable:** `docs/superpowers/audits/2026-04-19/06-developer-experience.md`

**Method:** Pretend to be a developer extending the plugin. Try these five tasks:

1. Add a new custom pricing model
2. Add a new ad format
3. Inject a custom field on the ad submission wizard
4. Intercept a wallet debit to charge an external processor
5. Customize the classified single template

For each: count the number of hooks/filters needed, whether the API is documented, whether public classes are autoloaded, whether the extension survives a plugin update.

**Look for:**
- Tasks that can't be done without forking
- Tasks that require modifying core files
- Internal-only classes referenced in templates (leak of internal API)
- `private` where `protected` would enable subclassing for extenders
- Missing `@package` / `@since` / `@return` types that break IDE autocomplete

**Catch:** tell us whether we can credibly call this "developer friendly" or we're lying.

---

## Deliverables

1. **Six audit findings docs** (listed above)
2. **One consolidated severity table** at `docs/superpowers/audits/2026-04-19/00-summary.md`
3. **Six fix plans** (one per pass, only if findings warrant)
4. **Updated `CLAUDE.md` + `docs/ARCHITECTURE.md`** reflecting the hook surface + settings registry
5. **New `docs/HOOKS.md`** — every public filter/action with signature, since, example

---

## Execution

### Phase A (read-only, ~1 day of focused work)

Run all six passes, produce the six findings docs. No code changes. No fixes yet.

### Phase B (triage, ~0.5 day)

Rank every finding across all six docs into one priority queue by severity + blast radius. Group into fix batches. Each batch is its own plan doc.

### Phase C (fix batches, plan-per-batch)

Each batch is a separate plan reviewed and approved before implementation. No merging with Phase A/B.

---

## Automation budget

- Use `mcp__wp-plugin-qa__*` for automated structural checks (REST contract, UX guidelines, wiring completeness, enum consistency)
- Use `mcp__wpcs__wpcs_full_check` for style
- Use `mcp__wpcs__wpcs_phpstan_check` for type errors
- Manual reading for template wiring + promise-vs-behavior (these need human judgment)
- Grep + AST-level search via `Grep` tool for settings inventory

---

## What this audit is NOT

- Not a rewrite. No refactors during audit phase — only findings.
- Not a performance audit (separate concern)
- Not a security audit (separate concern, run later via `mcp__wp-malware-cleanup__*` + OWASP review)
- Not a UI redesign
- Not a feature addition

---

## Risks

- **Scope creep.** Once we find issues, temptation to fix mid-audit is high. Resist. Findings only in Phase A.
- **Noise.** P2 findings could drown P0. Mitigate by putting P0 at the top of every doc and the summary.
- **Stale on delivery.** Six docs take time; by the time Phase C starts, code may have moved. Mitigate by locking the audit to commit SHA `d01b48a` (current HEAD) and re-verifying any divergence.

---

## Acceptance criteria (end of Phase A)

- All six findings docs exist and are non-empty
- Summary doc lists every P0 with a one-line fix idea
- No actual code changes in Phase A
- Doc committed on `1.5.0` before fix work begins

---

## Proposal to Varun

**If you approve this scope, I start Phase A now.** I run the six passes in order, commit the findings docs as I go (one commit per pass), and ping you at the end of Phase A with the summary to triage priorities together. Phase C fix plans come after.

Open questions before I start:

1. **Lock to which branch?** `1.5.0` HEAD (`d01b48a`) or bring `main` into the picture too?
2. **Audit Pro only, or Pro + Free?** Pro extends Free, so some findings might sit in Free.
3. **Timeline?** Same-day Phase A is feasible if I run the automated MCP passes in parallel with manual reading.
4. **Priority if we find P0s that block release?** Do we pause the site-mode-respect batch and fix P0 first, or finish the audit first?

---

**Owner:** Varun
**Status:** Awaiting approval of scope before Phase A starts.
