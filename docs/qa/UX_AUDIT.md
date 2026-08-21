# UX Audit — WB Ad Manager (Free + Pro)

Per-surface check. Every customer-visible view × light/dark × desktop 1280px / mobile 390px.

**Rendered-surface rule:** HTTP 200, a DOM node existing, a clean grep or a well-shaped JSON response are **not** visual verification. Look at the screen, or measure the computed style. Several 3.1.0 defects returned 200 and looked fine to every structural check while being unusable on screen.

## How to record a finding

| Field | Meaning |
|---|---|
| Surface | Screen + persona, e.g. "Portal → Messages (advertiser)" |
| Viewport / theme | 1280 light, 390 dark, … |
| Expected | What a site owner would expect to see |
| Actual | What is on screen, with a **measurement** where possible |
| Evidence | Screenshot path or computed values |

Measurements beat adjectives. "Looks cramped" is not actionable; "seller card bottom 1679, description top 1679 — 0px gap" is.

## Admin surfaces

| Surface | Check |
|---|---|
| All Ads | Columns readable; Impressions/Clicks agree with Ad Analytics; status pills legible |
| Ad Folders | Rail counts match the list; unpublished rows labelled; breadcrumb reflects scope |
| Ad Analytics | Chart renders; empty states explain themselves rather than showing a blank box |
| Revenue | Figures formatted in the configured currency and decimals |
| Submissions | Pending queue distinguishable from live |
| Settings (all tabs) | Notices appear once; exactly one `wp-header-end`; saving one tab leaves the others alone |
| Tools | Destructive actions use an in-page confirm, never a native `confirm()` |
| Classified Reports | Row actions resolve; a deleted listing degrades to text, not an empty link |

## Frontend surfaces

| Surface | Check |
|---|---|
| Classifieds list | Grid, images, prices, badges, filters, counts; 390px collapses filters and does not scroll sideways |
| Classified detail | Gallery, price, seller card, description, reviews, report control; **cards separated**, not flush |
| Seller reviews | Title, comment and meta on their own lines. A two-word review must not collapse into one run-on row |
| Review form | Stars are ≥40px targets and select 1–5 accurately; focus ring visible |
| Ad slots | Creative fits its slot; `rel="noopener noreferrer"`; alt text present |

## Portal surfaces (advertiser)

| Surface | Check |
|---|---|
| Overview | Stat tiles readable; onboarding steps make sense on an empty account |
| My Ads / Campaigns | Lists paginate; status legible |
| Messages | **Conversation pane must not be starved.** Bubbles wrap as words, never one character per line |
| Favorites | Heart removes the card immediately and the removal persists |
| Link Partnerships | **Inputs visibly bounded in both themes** — the plugin owns border style and width, not the theme |
| Analytics | Export control is a real link; chart and table agree |
| Wallet / Membership | Balances in the configured currency |

## Contrast and tokens

- Form-control boundaries need **3:1** against their fill (WCAG 2.1 SC 1.4.11). Measure; do not eyeball.
- Never use a theme's divider token for a control boundary — dividers are tuned for hairlines and land near 1.1:1.
- Never declare `border-color` without `border-style` and `border-width`. The initial style is `none`, so the field renders borderless on any theme that doesn't style inputs.
- Colours come from tokens. Raw hex in a component is drift.

## Known theme-side limitation

On Reign **dark**, the theme pins input `border-color` with `!important` at higher specificity than the plugin can reach from `portal.css`. Portal fields there measure ~1.19:1 against their fill. The plugin's own declaration is correct; closing the gap needs a Reign change. Re-measure if Reign updates.
