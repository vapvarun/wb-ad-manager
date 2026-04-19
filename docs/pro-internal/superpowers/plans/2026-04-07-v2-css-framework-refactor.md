# v2.0 — Portal CSS Framework Refactor

**Status:** Planned for next sprint
**Priority:** Medium (internal code quality, not user-facing)

---

## Problem

The portal uses `.wbam-portal-content > div` as a wildcard CSS selector to apply box styling (white bg, 1px border, 8px radius, 24px padding) to whatever div is the direct child. This works today but is fragile:

- Adding any structural wrapper div creates double-box
- Inline-rendered tabs (7) and template-loaded tabs (7) use different DOM structures
- The wildcard styling is accidental, not intentional

## Root Cause

Two rendering paths produce different HTML:

```
Inline (shortcode class):
  .wbam-portal-content > .wbam-overview     ← gets box from > div selector

Template (file):
  .wbam-portal-content > .wbam-tab-content  ← gets box from > div selector
    (no inner div with box styling)
```

## Plan

### Step 1: Remove wildcard selector

In `assets/css/portal.css`, remove:
```css
.wbam-portal-content > div {
    /* whatever box styles are here */
}
```

### Step 2: Add explicit box class

Create `.wbam-portal-box` class with the box styling:
```css
.wbam-portal-box {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 24px;
}
```

### Step 3: Apply to every tab's root div

**Inline-rendered tabs** — add `wbam-portal-box` to existing root div:
- `.wbam-overview` → `wbam-overview wbam-portal-box`
- `.wbam-ads-list` → `wbam-ads-list wbam-portal-box`
- `.wbam-campaigns-list` → `wbam-campaigns-list wbam-portal-box`
- `.wbam-classifieds-manage` → `wbam-classifieds-manage wbam-portal-box`
- `.wbam-wallet` → `wbam-wallet wbam-portal-box`
- `.wbam-analytics` → `wbam-analytics wbam-portal-box`
- `.wbam-profile` → `wbam-profile wbam-portal-box`

**Template-loaded tabs** — add to `.wbam-tab-content`:
- Already works if `.wbam-tab-content` gets the class, or add to each template's root div

### Step 4: Verify all 14 tabs

Screenshot each tab to confirm single box, no visual regression.

## Also in v2.0 Scope

- **Migrate inline tabs to template files** — Move the 7 inline-rendered tab methods to `templates/portal/tabs/*.php` using `Template_Loader`. This enables child theme overrides for ALL tabs, not just 7.
- **Classified package admin UI** — Replace hardcoded Free/Standard/Premium with admin-manageable packages
- **Action audit fixes** — WooCommerce wallet endpoint mismatch (`wallet/create-woo-order` vs `wallet/woocommerce-add-funds`), missing `analytics/top-placements` endpoint

## Pre-existing Wiring Bugs (from action audit)

| Issue | JS file:line | PHP endpoint | Fix |
|---|---|---|---|
| WooCommerce wallet endpoint mismatch | portal.js:554 `wallet/create-woo-order` | class-woocommerce-integration.php:643 `wallet/woocommerce-add-funds` | Rename JS or PHP to match |
| Missing top-placements endpoint | portal.js:137 `analytics/top-placements` | Not registered in analytics-api.php | Register endpoint or remove JS call |
| Stale balance selector | portal.js:617 `.wbam-wallet-amount` | No matching HTML element | Change to `.wbam-balance-value` |
