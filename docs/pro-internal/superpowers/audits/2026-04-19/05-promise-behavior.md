# Pass 5 — Promise vs Behavior

**Audit date:** 2026-04-19
**Scope:** Pro `1.5.0` (HEAD `90436ec`) + Free `2.8.0` (HEAD `9f38b51`)

Sampled approach: 12 representative settings from Pass 1's inventory. Code-traced each promise to its consumer to verify behavior matches the UI claim.

## Summary

- Settings sampled: 12
- **PROMISE-KEPT:** 7
- **PROMISE-PARTIAL:** 3 (behavior fires but not everywhere it claims)
- **PROMISE-BROKEN:** 2 (toggle saved, behavior does not change)

---

## Sampled settings

### ✅ PROMISE-KEPT (7)

| # | Setting | UI promise | Code path verified |
|---|---------|------------|--------------------|
| 1 | `wbam_pro_settings.enabled_modules.classifieds` | "Users post classified listings." | `is_module_enabled('classifieds')` gates `Classified_Manager::get_instance()->register()` |
| 2 | Site Mode → Classifieds | "no ads, no links" | Verified live 2026-04-19 — portal tabs and admin submenus collapse correctly |
| 3 | `enabled_modules.wallet` | Wallet UI + billing | All wallet tabs gate on this flag |
| 4 | `require_approval` (classifieds) | "Listings require admin approval before going live" | `Classified_Manager::create` sets `post_status='pending'` |
| 5 | `trust_system_enabled` + `trust_approvals_required` | "Trusted advertisers auto-approve" | `Ad_Submission_Manager::submit_ad` calls `Trust_Manager::is_trusted()` |
| 6 | `cookie_consent_required` | "Only track analytics after user accepts cookies" | `Analytics_Tracker::track()` early-returns if `Cookie_Consent::is_accepted() === false` |
| 7 | `admin_as_advertiser` | "Admin users auto-get advertiser account" | `Advertiser_Manager::auto_provision_admin()` runs on `admin_init` when flag is on |

### ⚠️ PROMISE-PARTIAL (3)

| # | Setting | UI promise | What breaks |
|---|---------|------------|-------------|
| 8 | `enabled_modules.ad_submissions` | Toggling off "hides the ad submission feature" | ✅ Portal tab hidden and ✅ admin submenu hidden, but ❌ the `[wbam_ad_form]` shortcode still renders the form when placed on any page. Needs gate in `Ad_Submission_Shortcodes::render_form()`. |
| 9 | `enabled_modules.rotation` | Toggle controls rotation logic | ✅ Rotation_Engine::rotate() early-returns, but ❌ admin "Ad Rotation" settings tab still renders its form (only the effect is gated). Should also gate tab render. |
| 10 | `hide_ads_for_role` | "Hide ads from selected user roles" | ✅ Server-side serve blocked, but ❌ the `wbam_ad` shortcode still outputs the outer `<div class="wbam-ad-slot">` container (empty, but present in DOM). Layout cosmetic. |

### ❌ PROMISE-BROKEN (1 after retraction)

| # | Setting | UI promise | Actual behavior |
|---|---------|------------|-----------------|
| 11 | `wbam_pro_rest_legacy_routes` | (unseen UI, Pass 1 flagged as DEAD-READ) | Setting has no `register_setting()` call and no admin UI, but internal code paths reference it. If it existed in UI, toggle would do nothing. **Action:** either remove references or add UI + wire it. |
| 12 | ~~`auto_approve_paid_ads` price-vs-payment check~~ **RETRACTED 2026-04-19 after code re-trace.** The flow does enforce payment: `submit_ad()` guards balance at line 137 (returns `insufficient_credits` WP_Error before submission record exists), and auto-approved submissions immediately call `activate_ad()` at line 282 which charges credits from the wallet. The `$package->price > 0` check at line 333 correctly distinguishes paid vs free package types. **No revenue leak.** The setting description is slightly ambiguous ("when advertiser pays" reads retroactive) — consider rewording to "Only auto-approve paid packages (free packages always require review)" as a P2 polish item. |

---

## Methodology

1. Pulled Pass 1's full settings table.
2. Picked 12 settings that make user-visible promises in their UI description (hints, descriptions, labels like "When enabled…").
3. For each: read the description, then grepped for the storage key + any helper like `is_module_enabled` / `get('key')` to find all consumer code paths.
4. Verified each consumer path actually respects the flag.

**Not a full sweep.** 12 of ~50+ user-facing settings. Phase B triage should expand this pass to cover 100% of settings that have UI copy making a behavior claim.

---

## Recommended Phase C batch

**Batch 5 (promise-closure):**
- Gate `[wbam_ad_form]` shortcode on `ad_submissions` module
- Gate ad-rotation admin tab render on `rotation` module (not just the effect)
- Fix empty `<div class="wbam-ad-slot">` when role-hidden
- Fix `auto_approve_paid_ads` to read `payment_status` not `price > 0`
- Remove or wire the `rest_legacy_routes` dead key

Order by blast radius — the `auto_approve_paid_ads` bug is a **revenue-impacting defect** (discounted ads auto-approved = unpaid exposure); that's P0. Others are P1 (cosmetic/navigational).

---

## What's next

This sampled pass gives signal, not total coverage. Phase B (triage) should decide:
- Do we expand Pass 5 to cover every UI promise before marketing push?
- Or accept sampled coverage and trust that the pattern holds for non-sampled settings (with risk of 1-2 more broken promises)?

Recommended: **expand Pass 5 to full coverage before marketing push.** Sampling found 2 broken promises in 12 — extrapolating, ~8–10 more broken promises likely sit in the remaining ~50 settings.
