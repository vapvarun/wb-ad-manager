# Plan: Drop OS-driven dark mode — follow the WordPress theme only

**Status:** Approved (execute immediately after doc commit)
**Date:** 2026-04-19
**Target release:** `1.5.0`
**Risk class:** CSS-only, visual-only.

---

## 0. Problem

Customer surfaced the bug: "why are we getting dark mode suddenly?"

Root cause: `assets/css/portal.css:251-268` has:

```css
@media (prefers-color-scheme: dark) {
    :root {
        --wbam-bg:             #0f172a;
        --wbam-surface:        #1e293b;
        ...
    }
}
```

This rewrites the plugin's design tokens based on the visitor's **operating-system** dark-mode preference (macOS / Windows / iOS auto-dark).

A WordPress site doesn't re-skin itself when the visitor's OS is dark. The site stays in whatever visual state the **theme** rendered it in. A BuddyX-styled site remains BuddyX-light even when the visitor's Mac is on dark mode.

The plugin flipping to dark under those conditions produces the exact bug the customer saw: page background + cards stay light (theme-driven), but the plugin's wizard panel goes navy (plugin-driven via OS). Visually incoherent.

---

## 1. Rule: the plugin follows the theme, not the system

Every other Wbcom plugin already follows this rule — tokens cascade from theme CSS variables first (`--wp--preset--color--base`, `--brand`, `--reign-site-sections-bg-color`, etc.), and the plugin falls back to its own defaults only when the theme doesn't define them.

Dark mode is a *theme-owned* concept:
- If the theme has a dark toggle (BuddyX Pro, Reign, Astra Pro, etc.), it sets `html.dark-mode`, `body.dark-scheme`, `[data-theme="dark"]`, or `.theme-dark`.
- Plugin already has selectors for all four (portal.css:275-310).
- When the theme flips its class, the plugin's tokens flip with it — because they read from theme CSS variables.

That path is **correct and stays**.

The OS-driven `@media (prefers-color-scheme: dark)` path is **wrong** because it fires regardless of what the theme is doing, producing the incoherent state the customer hit.

---

## 2. Change

**Single edit:** delete lines 251-268 of `assets/css/portal.css` (the `@media (prefers-color-scheme: dark)` block).

**Keep:** lines 275-310 (theme-driven `html.dark-mode`, `body.dark-scheme`, `[data-theme="dark"]`, `.theme-dark` selectors).

---

## 3. What changes for users

| Scenario | Before | After |
|----------|--------|-------|
| Theme in light, visitor OS on light | Light plugin ✓ | Light plugin ✓ |
| Theme in light, visitor OS on **dark** | Plugin flipped dark while theme was light → **incoherent** (the customer-reported bug) | Light plugin ✓ (matches theme) |
| Theme has dark toggle, user clicks it | Plugin follows (class-driven) ✓ | Plugin follows (class-driven) ✓ |
| Theme in dark (always), visitor OS on light | Plugin light, page dark → incoherent | Plugin dark (class-driven) ✓ |
| Theme in dark (always), visitor OS on dark | Double-triggered but visually same | Plugin dark (class-driven) ✓ |

In every row the post-change behavior is "plugin matches theme." Never "plugin matches OS."

---

## 4. No risk

- **No PHP changes.** Pure CSS deletion.
- **No new behavior.** This is a removal.
- **Existing dark-toggle themes unaffected.** The class selectors do the work.
- **No visitor loses functionality.** OS-dark users still see the plugin, it just matches the theme like every other plugin element on the page.

---

## 5. Related: `.min.css`

`portal.min.css` also needs the block stripped. It's a minified copy of the same file — either regenerated via the build pipeline or edited inline. For this release: inline edit (minified content search-and-delete).

---

## 6. Verification

1. `grep -n "prefers-color-scheme" assets/css/portal.css` — should return nothing.
2. `grep -n "prefers-color-scheme" assets/css/portal.min.css` — should return nothing.
3. Browser: load the frontend with OS in dark mode + BuddyX theme on light → wizard should render light (matching theme).
4. Browser: if a dark-toggle theme is installed, flip it — plugin tokens should follow the theme's class.

---

## 7. Commit

One commit: `style(css): drop OS prefers-color-scheme dark — plugin follows theme not system`
