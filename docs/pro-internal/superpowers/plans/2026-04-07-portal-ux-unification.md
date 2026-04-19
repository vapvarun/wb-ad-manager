# Portal UX Unification — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Make all 14 advertiser portal tabs follow one consistent visual pattern — same wrapper, same headings, same stat cards, same empty states, same buttons.

**Architecture:** The portal has two rendering paths: 7 tabs inline-rendered in `class-advertiser-shortcodes.php` (4500+ lines) and 7 tabs loaded from `templates/portal/tabs/*.php`. Both paths must produce the same HTML structure and use the same CSS classes.

**Tech Stack:** PHP templates, CSS (portal.css), WordPress shortcode API

---

## Current State (from audit)

### Two rendering paths, two patterns:

| Rendering | Tabs | Has `wbam-tab-content` wrapper | Uses template file |
|---|---|---|---|
| Inline shortcode | Overview, Ads, Campaigns, Classifieds, Wallet, Analytics, Profile | NO | NO (dead template files exist for some) |
| Template file | Inquiries, Favorites, Following, Links, Messages, Membership, SoV | YES | YES |

### Inconsistencies found:

| Issue | Tabs affected |
|---|---|
| Duplicate heading (tab name repeated inside content) | Analytics, Profile |
| No `wbam-tab-content` wrapper | 7 inline-rendered tabs |
| 3 different stat card patterns | Overview, Analytics, SoV, Wallet |
| Empty states use different components | Inquiries, Messages, Membership, Analytics, SoV |
| Primary button is black instead of red | Links tab |
| Campaign COMPLETED = DRAFT (both grey) | Campaigns |
| Inconsistent filter tab styling | Inquiries vs Classifieds |

---

## Target Pattern

Every tab MUST produce this HTML structure:

```html
<!-- Tab header (rendered by shortcode wrapper — already exists) -->
<div class="wbam-tab-header">
    <h3>Tab Name</h3>
    <div class="wbam-tab-actions">
        <a class="wbam-btn wbam-btn-primary">Action Button</a>
    </div>
</div>

<!-- Tab content (this is what each tab renders) -->
<div class="wbam-tab-content wbam-{tab-name}">
    <!-- Tab-specific content here -->
</div>
```

### Component patterns (one way to do each thing):

**Stat cards:**
```html
<div class="wbam-stats-grid">
    <div class="wbam-stat-card">  <!-- or <a> if clickable -->
        <div class="wbam-stat-icon">
            <span class="dashicons dashicons-{icon}"></span>
        </div>
        <div class="wbam-stat-content">
            <span class="wbam-stat-value">123</span>
            <span class="wbam-stat-label">Label</span>
        </div>
    </div>
</div>
```

**Empty states:**
```html
<div class="wbam-empty-state">
    <span class="dashicons dashicons-{icon} wbam-empty-state-icon"></span>
    <h4 class="wbam-empty-state-title">Title</h4>
    <p class="wbam-empty-state-text">Description explaining the feature.</p>
    <a href="..." class="wbam-btn wbam-btn-primary">Call to Action</a>
</div>
```

**Section cards:**
```html
<div class="wbam-section">
    <h4>Section Heading</h4>
    <!-- content: table, form, cards -->
</div>
```

**Tables:**
```html
<div class="wbam-section">
    <h4>Table Title</h4>
    <div class="wbam-table-responsive">
        <table class="wbam-table">...</table>
    </div>
</div>
```

**Primary buttons:** Always `wbam-btn wbam-btn-primary` (red/coral). No black buttons.

**Status badges:** 
- Active/Completed → `wbam-badge-success` (green)
- Pending → `wbam-badge-warning` (yellow/orange)
- Draft → `wbam-badge-secondary` (grey)
- Rejected/Failed/Expired → `wbam-badge-danger` (red)

---

## Tasks

### Task 1: Add `wbam-tab-content` wrapper to inline-rendered tabs

**Files:**
- Modify: `includes/Modules/Advertisers/class-advertiser-shortcodes.php`

Find each `render_tab_*()` method for the 7 inline-rendered tabs and wrap their output in `<div class="wbam-tab-content wbam-{tab-name}">...</div>`:

| Method | Wrapper class |
|---|---|
| `render_tab_overview()` | `wbam-tab-content wbam-overview` |
| `render_tab_ads()` | `wbam-tab-content wbam-ads` |
| `render_tab_campaigns()` | `wbam-tab-content wbam-campaigns` |
| `render_tab_classifieds()` | `wbam-tab-content wbam-classifieds` |
| `render_tab_wallet()` | `wbam-tab-content wbam-wallet` |
| `render_tab_analytics()` | `wbam-tab-content wbam-analytics` |
| `render_tab_profile()` | `wbam-tab-content wbam-profile` |

Check if the template-loaded tabs already have this wrapper in their PHP files. If so, don't double-wrap.

**Verification:** Browser-check that all 14 tabs now have `wbam-tab-content` in their DOM.

---

### Task 2: Remove duplicate headings

**Files:**
- Modify: `includes/Modules/Advertisers/class-advertiser-shortcodes.php` (Analytics, Profile methods)
- Verify: all template files in `templates/portal/tabs/*.php`

**Analytics:** The inline render outputs an inner heading "Analytics" with date range. Remove the heading text, keep the date range display.

**Profile:** The inline render outputs `<h3>Profile Settings</h3>`. Remove it — the tab-header already says "Profile".

**Audit all template files:** Grep for `<h2>`, `<h3>`, `<h4>` in each template file. If the heading text matches the tab name, remove it. Keep section sub-headings (e.g., "Personal Information" inside Profile is fine — that's a section heading, not a tab heading).

**Verification:** Screenshot each tab, confirm single heading per tab.

---

### Task 3: Unify empty states

**Files:**
- Modify: `templates/portal/tabs/inquiries.php` — add CTA button
- Modify: `templates/portal/tabs/messages.php` — consolidate split-panel empty state
- Modify: `templates/portal/tabs/membership.php` — change h3→h4, use standard classes, add CTA
- Modify: `templates/portal/tabs/analytics.php` — chart empty state
- Modify: `templates/portal/tabs/share-of-voice.php` — table empty states
- Modify: `assets/css/portal.css` — ensure `.wbam-empty-state` component is complete

Every empty state MUST use this exact pattern:
```html
<div class="wbam-empty-state">
    <span class="dashicons dashicons-{icon} wbam-empty-state-icon"></span>
    <h4 class="wbam-empty-state-title">Title</h4>
    <p class="wbam-empty-state-text">Description</p>
    <a href="..." class="wbam-btn wbam-btn-primary">CTA</a>
</div>
```

| Tab | Icon | Title | CTA |
|---|---|---|---|
| Inquiries | dashicons-email-alt | No inquiries yet | Post a Classified |
| Messages (when 0 threads) | dashicons-format-chat | No messages yet | Browse Classifieds |
| Membership | dashicons-groups | No membership plans available | Contact Us / none |
| Analytics chart | dashicons-chart-bar | No analytics data yet | Create an Ad |
| SoV tables | dashicons-chart-pie | No rotation data yet | (none — just message) |

**CSS:** Define `.wbam-empty-state` and sub-elements ONCE in portal.css. All templates use the same classes.

**Verification:** Screenshot each empty state, confirm they all look identical in structure.

---

### Task 4: Unify stat card pattern

**Files:**
- Modify: `includes/Modules/Advertisers/class-advertiser-shortcodes.php` (Analytics render)
- Modify: `templates/portal/tabs/share-of-voice.php`
- Modify: `includes/Modules/Advertisers/class-advertiser-shortcodes.php` (Wallet render)
- Modify: `assets/css/portal.css` (if needed)

**Target:** All stat cards use the Overview pattern — grey icon circle + large value + label.

| Tab | Current | Target |
|---|---|---|
| Overview | Grey icon circles ✓ | Keep as-is (this is the target pattern) |
| Analytics | No icons, just number + label | Add grey icon circles: chart-area (Impressions), admin-links (Clicks), performance (CTR) |
| Share of Voice | Colored icon circles (teal/blue/purple/green) | Change to grey icon circles (same as Overview) |
| Wallet | Accent-border cards, no icons | Refactor to `wbam-stat-card` with icons: money-alt (Balance), cart (Spent) |

**Verification:** Screenshot Overview, Analytics, SoV, Wallet side by side — stat cards should look identical in structure.

---

### Task 5: Button and badge color consistency

**Files:**
- Modify: `templates/portal/tabs/links.php` — Submit button class
- Modify: `assets/css/portal.css` — campaign badge colors
- Modify: `includes/Modules/Advertisers/class-advertiser-shortcodes.php` — classifieds CTA wording

**Fixes:**
1. Links "Submit Inquiry" button: add `wbam-btn-primary` class (or change existing dark class)
2. Campaign COMPLETED badge: change from grey (`wbam-badge-secondary`) to green (`wbam-badge-success`)
3. Classifieds empty state CTA: "Post Your First Classified" → "Post New Classified" (match header button)

**Verification:** Screenshot Links form (red button), Campaigns (green COMPLETED badge), Classifieds empty state (matching wording).

---

## Execution Order

1. **Task 1** (structural wrapper) — foundation for CSS consistency
2. **Task 2** (duplicate headings) — quick wins
3. **Task 5** (buttons + badges) — quick wins
4. **Task 3** (empty states) — component unification
5. **Task 4** (stat cards) — visual unification

## Verification

After all tasks complete:
1. Screenshot all 14 tabs
2. Compare tab-by-tab against the target pattern
3. Check at 390px viewport for responsive
4. Confirm zero inline styles added
5. Run `php -l` on all modified files
6. Commit with clear message per task

---

## Basecamp Cards

| Card ID | Task |
|---|---|
| 9758731722 | Task 1: Tab content wrapper |
| 9758731801 | Task 2: Duplicate headings |
| 9758732073 | Task 3: Empty states |
| 9758732336 | Task 4: Stat cards |
| 9758732429 | Task 5: Buttons + badges |
