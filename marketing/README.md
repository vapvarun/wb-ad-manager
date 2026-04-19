# WB Ad Manager — Marketing Assets

This folder holds all marketing content for WB Ad Manager (Free) and WB Ad Manager Pro.

**Source of truth for specs:** [`docs/website/feature-matrix.md`](../docs/website/feature-matrix.md). Before publishing any copy that makes a capability claim, verify it there. The payment/wallet section is especially easy to misstate — see [`docs/website/payments/wallet-and-payments.md`](../docs/website/payments/wallet-and-payments.md).

---

## Folder Map

| Folder | What's in it | Use it when… |
|--------|-------------|--------------|
| `01-slides/` | Presentation decks — product overview, feature breakdown, free-vs-pro comparison, use-case slides | Running a webinar, demo, or partner briefing |
| `02-video-scripts/` | Script + shot-list files for each video | Recording a walkthrough or tutorial video |
| `03-website-copy/` | Full page copy — landing page, feature pages, FAQ, pricing, use cases, WP.org listing, docs outline | Building or updating the marketing website |
| `04-email-sequences/` | Triggered email sequences — welcome, free-to-pro upgrade, feature announcements, re-engagement | Setting up or editing email automation |
| `05-social-media/` | Twitter/X threads, LinkedIn posts, Facebook posts, carousel content | Scheduling or writing social posts |
| `06-sales-materials/` | One-pager, comparison charts, ROI calculator, objection-handling guide | Sales calls, partner decks, support escalations |
| `07-brand-assets/` | Messaging guide, persona profiles, screenshot requirements | Ensuring consistent voice; briefing new writers |

---

## Quick Decision Table

| Task | Go to |
|------|-------|
| Writing a homepage or feature page | `03-website-copy/` |
| Writing an email campaign | `04-email-sequences/` |
| Posting on Twitter / LinkedIn | `05-social-media/` |
| Creating a slide deck | `01-slides/` |
| Scripting a video | `02-video-scripts/` |
| Preparing for a sales call | `06-sales-materials/` |
| Checking brand voice or target persona | `07-brand-assets/` |
| Verifying a feature claim | [`docs/website/feature-matrix.md`](../docs/website/feature-matrix.md) |
| Understanding how payments work | [`docs/website/payments/wallet-and-payments.md`](../docs/website/payments/wallet-and-payments.md) |

---

## Key Facts — Do Not Drift From These

1. **Active installs:** ~20 on WordPress.org. Do not write "10,000+ installs" or similar inflated numbers. Use "new plugin gaining traction" or leave the stat out.
2. **Payments:** Pro does not ship built-in Stripe/PayPal/Razorpay. It ships the Wbcom Credits SDK with 5 adapters (WooCommerce Products, WooCommerce Subscriptions, WooCommerce Memberships, Paid Memberships Pro, MemberPress). Gateways like Stripe or PayPal only appear as options inside WooCommerce.
3. **Memberships:** Membership plans in classifieds gate how many listings and featured upgrades a seller gets. They do not gate paid ad campaigns or packages.
4. **Admin path:** Membership Plans live at `Classifieds → Membership Plans` (not `Advertisers → Membership Plans`).
5. **Ad types:** 5 types: Image, Rich Content, HTML/JS Code, Google AdSense, Email Capture.
6. **DB version:** `3.8.0`, stored in option `wbam_pro_db_version`.

---

## Images

Screenshots are stored at `docs/website/images/` and referenced from marketing files via relative path `../docs/website/images/...`. Do not copy images into `marketing/images/` — keep one source of truth.

Sub-folders: `free/`, `pro/`, `flows/`.

---

## Sharing banners

`07-brand-assets/banners/` holds 15 banners (5 angles × 3 sizes) for blog OG, social feed, and email header use. Don't sprinkle banner references across every post file — the mapping of "which banner goes with which post / email / landing" lives in one place:

- [`07-brand-assets/banners/USAGE-MAP.md`](07-brand-assets/banners/USAGE-MAP.md) — which banner to use where.
- [`07-brand-assets/banners/briefs.md`](07-brand-assets/banners/briefs.md) — palette + per-angle copy + regeneration notes.

Banners use their own warm editorial palette (terracotta / amber / cream) — deliberately distinct from the navy / blue of the product's screenshots, so a blog post using a banner doesn't look like a reskinned admin UI.
