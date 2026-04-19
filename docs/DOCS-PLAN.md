# WB Ad Manager Documentation Plan

**Status:** draft for review.
**Owner:** docs working group.
**Covers:** Free + Pro combined documentation set in `docs/` (single source of truth).

The audit in `docs/pro-internal/docs-audit-2026-04-19.md` graded the set at ~70–75% customer-ready. This plan turns that into a coherent target: docs that reflect what the product actually does, support each reader's real journey, and stay accurate over time instead of drifting.

---

## 1. Problem statement

Today's docs are feature-organized (ad-management, advertiser-portal, analytics, etc.) and grade well per-doc. What they do not do:

- **Reflect what a reader can actually do right now.** Lots of pages describe features in isolation; few walk a customer through a real outcome end-to-end.
- **Match the shipping code in every field label and setting key.** Drift has crept in: broken cross-links from the old `01-`/`02-` numbering scheme, payment-gateway copy that still says "not yet available" in three places, hook-ref links that point nowhere.
- **Match the reader's question, not the author's feature list.** Someone evaluating Pro wants to know "can I actually run an ad marketplace on this?" — the current Pro docs describe modules instead of outcomes.
- **Distinguish Free from Pro consistently.** The unified index marks Pro sections clearly, but Pro-only prerequisites creep into Free-looking docs and vice versa.
- **Prevent AI-flavored marketing fluff.** The audit flagged specific sentences that read like SaaS landing-page copy ("take your ads to the next level", "get your first ad live in 10 minutes — no coding required") that conflict with the otherwise-practical tone.

---

## 2. Reader personas we are writing for

We collapse to five. Every doc names its primary persona in the frontmatter.

| # | Persona | What they are trying to do | Where they land first |
|---|---------|----------------------------|-----------------------|
| 1 | **Evaluator — Free** | Decide whether to install WB Ad Manager at all | WP.org listing / `README.md` intro |
| 2 | **Operator — Free** | Install, put an ad on the page, iterate | Setup wizard → `getting-started/quick-setup-guide.md` |
| 3 | **Evaluator — Pro** | Decide whether to buy Pro on top of Free | "What's in PRO" Help tab → `pro-installation-requirements.md` |
| 4 | **Operator — Pro** | Turn on modules, run campaigns, collect revenue | `pro-settings-configuration.md` → module guides |
| 5 | **Developer** | Hook into the plugin, extend placements / ad types / REST | `DEVELOPER-GUIDE.md` → `pro-developer/` |

Advertiser end-users (the people buying ad space on a Pro-enabled site) are explicitly out of scope for the plugin admin's docs — the site owner supports them separately.

---

## 3. Documentation philosophy

1. **Journey before feature.** Each category's landing doc answers "what can I accomplish here?" before enumerating settings.
2. **Show, do not describe.** Every doc ends with a concrete verifiable outcome ("open `/go/your-slug` in an incognito window, confirm the redirect, then the click shows up in the Links list within 5 seconds").
3. **One job per doc.** Docs that try to cover create + configure + troubleshoot get split.
4. **Link laterally, not vertically.** Adjacent docs cross-reference each other by purpose, not by numbered order.
5. **Pro sections read Pro-first.** When a Pro doc assumes Free knowledge, it links to the Free doc — not inline-summarizes it.
6. **No marketing copy in docs.** Marketing copy lives in readme.txt's long description and the upgrade page. Docs are for people who already decided to use the product.

---

## 4. Information architecture — target state

The 10-category structure stays (it matches the wbcom-docs MCP convention), but we add two overlay artifacts that read across categories.

### 4a. Categories (unchanged structure, tightened per-doc charter)

| Category | Purpose | Primary persona |
|----------|---------|-----------------|
| Getting Started | Install + first success outcome | Operator Free / Operator Pro |
| Ad Management | Create ads, place them, control when they show | Operator Free |
| Link Management | Cloak affiliate URLs, track clicks, accept partnerships | Operator Free |
| Advertiser Portal (PRO) | Let advertisers sign up, submit, pay | Operator Pro |
| Analytics (PRO) | Read the numbers | Operator Pro |
| Classifieds (PRO) | Run the classifieds marketplace | Operator Pro |
| Payments & Wallet (PRO) | Accept money for ad purchases | Operator Pro |
| Pro Settings | Configure Pro modules | Operator Pro |
| Shortcode Reference | Drop-in snippets for templates | Operator Free, Operator Pro |
| Troubleshooting | Diagnose broken state | All operators |

### 4b. New overlay: "Flows" (one page per end-to-end outcome)

Add `docs/website/flows/` with five short task-flow walkthroughs. Each flow is a checklist that cross-links the detailed docs but owns the end-to-end story.

1. `flows/publish-first-ad.md` — operator-free journey from zero to first ad shown on the frontend.
2. `flows/monetize-affiliate-links.md` — operator-free journey from setup to first click-tracked conversion.
3. `flows/accept-paid-ads.md` — operator-pro journey from Pro activation to first advertiser payment cleared.
4. `flows/launch-classifieds-marketplace.md` — operator-pro journey for classifieds + moderation.
5. `flows/extend-via-hooks.md` — developer journey with three concrete extension examples.

Flows are the entry point for each persona; individual category docs are the reference library behind them.

### 4c. New overlay: "Feature matrix" (Free vs. Pro)

Add `docs/website/feature-matrix.md` — a single table that lists every capability with Free / Pro column ticks and a one-line description. Today's WP.org upgrade page serves this purpose for marketing; docs need their own version because evaluators will reach docs before they reach the upgrade page.

---

## 5. Per-doc charter

Each existing doc gets a one-line charter written as frontmatter. The charter forces the doc's author (or future editor) to keep scope honest. Pattern:

```markdown
---
title: Managing Ads
persona: Operator — Free
one_job: Create, edit, and organize the ad posts admins see in /edit.php?post_type=wbam-ad.
outcome: After reading, the admin can publish an ad with placements and see its impression count increment.
assumes: WordPress admin access, the free plugin activated.
tier: free
---
```

Benefits:
- Reviewers can reject a PR that drifts outside the charter.
- We can machine-check that every doc declares a persona, a tier, and an outcome.
- Charters feed the `docs_config.json` manifest so the site can render the outcome as the SERP snippet.

---

## 6. Accuracy cleanup — specific fixes

All items from the audit plus a proactive sweep. Bucketed by severity.

### 6a. Block-buy severity (fix before anything else)

- **3 broken links** in `getting-started/quick-setup-guide.md` (lines 121, 174, 193). The `01-`/`02-` prefix was dropped when we flattened slugs; fix the targets.
- **Payment-gateway "not yet available" duplicates** across `pro-installation-requirements.md`, `wallet-and-payments.md`, `pro-settings-configuration.md`. Decide once: are PayPal and Razorpay shipping in 1.5.0 or not? If not, centralize the "not yet" note on one page and link to it from the others; do not repeat the claim.
- **Missing admin navigation paths**. Several Pro docs reference menu items like "WB Ads → Tools" that may not exist in the current build. Verify each admin path link against the actual `Pro_Admin` registration and fix any that drifted.

### 6b. Mislead severity (next sprint)

- **Shortcode drift.** Two shortcodes documented in Free + one in Pro reference attributes we verified against code in the last audit. Do the same verification pass for the remaining shortcodes and update any stale attribute lists.
- **Hook references that do not link.** `managing-ads.md` names hooks like `wbam_register_ad_types` inline without an anchor to the Developer Guide. Make every hook name in user-facing docs a link.
- **IP hash rotation window** in `campaign-management.md` line 125 says "daily rotating salt" without specifying the window. Code says the salt rotates at UTC midnight — say so.
- **Bot filter list** in `analytics-dashboard.md` references "bot filtering" without naming the user-agent patterns. Extract the list from code and paste it in.

### 6c. Polish severity (ongoing)

- **Marketing copy purge.** Five specific sentences flagged by the audit get rewritten in plain operator voice. (See §7 below.)
- **Emphasis discipline.** Bold on one-liner facts ("The Advertisers module is a core module and is always active") reads as filler — drop.
- **Example gaps.** Shortcode reference pages list parameter tables without a rendered-output preview. Add one rendered example per shortcode.

---

## 7. Tone and voice rules

Short rule list we check against in every PR.

- **No "take your ads to the next level".** No generic SaaS encouragement.
- **No counted-feature marketing.** "13 modules", "14-tab portal", "5 ad types" belongs on the upgrade page, not in a setup guide.
- **Lead with the outcome, not the feature.** "Accept paid ads without emailing invoices" beats "The Wallet module stores advertiser balances".
- **When in doubt, stay under 15 words per sentence.** Long sentences are where AI-flavor hides.
- **Name real commands and real paths.** Say "Settings → WB Ads → Pro → Wallet", not "navigate to the wallet area".
- **End each page with a verification step.** If the doc cannot tell the reader how to confirm they did the thing, the doc is not done.

---

## 8. Implementation roadmap

Three phases. Each phase is self-contained so we can ship after any one.

### Phase 1 — Truth pass (this sprint, ~3 hours)

Fix everything that would actively mislead a paying customer. Concretely:
- §6a items (broken links + payment-gateway "not yet" + stale admin paths).
- §6b shortcode-drift verification pass.
- §7 marketing-copy removal on the five audit-flagged sentences.

Exit criteria: no doc contains a statement the running code contradicts.

### Phase 2 — Flows overlay (next sprint, ~6 hours)

Write the five new flow docs under `docs/website/flows/` plus the feature matrix. Link each flow from the relevant category landing page. Update `docs_config.json` with the new flows category.

Exit criteria: every primary persona can reach a one-page flow that tells them exactly what to do from the category landing page.

### Phase 3 — Charters and CI (sprint after, ~4 hours)

Add a frontmatter charter to every doc per §5. Add a lightweight `bin/verify-docs.sh` that:
- validates every `docs/website/*.md` has `title`, `persona`, `tier`, `one_job`, `outcome` frontmatter;
- greps internal Markdown links and fails on broken relative paths;
- fails if any body contains a banned phrase from the §7 blacklist.

Wire it into `composer verify` in the Free repo so docs regressions surface at the same time as code regressions.

Exit criteria: docs regressions block a commit the same way a PHPStan regression blocks a commit.

---

## 9. Ongoing maintenance policy

Three small habits, written down so the docs do not re-rot.

1. **Every PR that changes admin UI labels, settings keys, hook names, or REST routes must also touch the corresponding doc** or include `docs: n/a` in the commit body with rationale. Reviewers enforce.
2. **Every new feature lands with a flow entry**, not just a category entry. A feature that does not fit into an existing flow is a signal we are building something without a customer outcome — discuss before merge.
3. **Every quarter, re-run the audit agent.** The audit that found the 15 items above took ~20 minutes of agent time. We keep it a recurring calendar item; any item found gets a Basecamp card, not a silent backlog.

---

## 10. What this plan does not do

- **It does not move docs out of `docs/website/`.** The wbcom-docs MCP expects that path; we keep it.
- **It does not split Free / Pro into separate doc sites.** We learned the hard way that drift between two sets is worse than one combined set with clear tier markers.
- **It does not add a videos strategy.** Videos are out of scope for this plan; if we add them, they come as a separate plan.
- **It does not touch internal planning docs** (`pro-internal/`). Those are audits and plans, not customer-facing; they live at Free level but are not part of the customer doc set.

---

## 11. Open questions for sign-off

Before Phase 1 starts, answers to:

1. Are PayPal and Razorpay shipping in Pro 1.5.0, or are they deferred? (Decides how we phrase the payment-gateway status across the 3 affected docs.)
2. Do we want the five flow docs in 2.8.x or can they slip to 2.9.0?
3. Do we want the `bin/verify-docs.sh` check blocking or advisory when it lands?
4. Who owns the quarterly audit rerun — product, engineering, or rotating?

Answer these four and Phase 1 can start.
