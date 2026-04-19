# Banner briefs — WB Ad Manager 2.8.0

Blog / social sharing banners for WB Ad Manager (Free) + WB Ad Manager Pro.
15 PNGs in this folder (5 angles × 3 sizes), source HTML under `source/`.

## Palette (deliberately distinct from prior Wbcom blue/purple work)

Chosen to feel editorial / utility-tool rather than SaaS-landing-page. All
text contrast passes WCAG AA on the cream background.

| Token | Hex | Usage |
|---|---|---|
| `--ink` | `#1A1A1A` | Primary text, brand lockup, frame rule, pill fills |
| `--paper` | `#FAF6EE` | Warm cream background (the sheet) |
| `--slate` | `#475569` | Body / lede text |
| `--terracotta` | `#C65A3E` | Primary accent — italic emphasis in headlines, kicker, accent arrow |
| `--amber` | `#D97706` | Decorative oversized numeral (opacity 0.10) |
| `--olive` | `#4B5B3A` | Rare secondary accent, reserved for variants |

Fonts: **Fraunces** (variable serif, italic display) + **Manrope** (body).
Both Google Fonts; both free.

## What each banner is for

### 1. `three-in-one-*` — the 3-in-1 angle

- **Kicker**: "Three-in-one"
- **Headline**: *One plugin. **Three tools.***
- **Lede**: "Affiliate link cloaker. Ad manager. Classifieds marketplace. One install replaces three plugins."
- **Decorative glyph**: italic serif `3`
- **When to use**: top-of-funnel (cold audience on Twitter / LinkedIn / blog OG), first impression of what the plugin even is.

### 2. `members-sell-ads-*` — advertiser portal angle

- **Kicker**: "Self-serve advertisers"
- **Headline**: *Your members **sell the ads.***
- **Lede**: "An advertiser portal where they sign up, top up credits, and run their own campaigns. No inbox of invoices. You keep the revenue."
- **Decorative glyph**: italic serif `$`
- **When to use**: targeting site-owners who've tried to monetize and burned out on manual invoicing. This is the most differentiating angle vs. other ad plugins.

### 3. `free-ships-features-*` — free is not crippleware

- **Kicker**: "The free version"
- **Headline**: ***Not** crippleware.*
- **Lede**: "Rotation. 16 placements. 5 ad types. Link cloaking. AdSense. BuddyPress. REST API. Everything you need to start — at zero cost."
- **Decorative glyph**: italic serif `0`
- **When to use**: objection-handler. WordPress users are burned by freemium-bait; this tells them the free tier is a real tool.

### 4. `classifieds-marketplace-*` — classifieds marketplace (Pro)

- **Kicker**: "Classifieds marketplace"
- **Headline**: *Run a **real marketplace.***
- **Lede**: "Listings, categories, featured upgrades, messaging, seller memberships, inquiries. Every moving part of a classifieds site, built-in."
- **Decorative glyph**: italic serif `M`
- **When to use**: audience already shopping for a classifieds plugin. This is Pro's killer-app story.

### 5. `affiliate-link-cloaker-*` — link cloaker

- **Kicker**: "Link cloaker"
- **Headline**: *Links that **look like yours.***
- **Lede**: URL transform demo — `amazon.com/gp/product/…` → `yoursite.com/go/book`
- **Decorative glyph**: large right arrow `→`
- **When to use**: affiliate-marketing audiences on Reddit / niche-site forums. The URL demo does the work; no illustration needed.

## Sizes per angle

| Suffix | Dimensions | Aspect | Use |
|---|---|---|---|
| `-og` | 1200×630 | 1.90 | Blog Open Graph, Twitter / X summary card, LinkedIn share preview |
| `-square` | 1080×1080 | 1:1 | Instagram feed, LinkedIn feed post, Mastodon, Facebook post |
| `-wide` | 1920×600 | 3.2 | Blog header hero, email header, site banner, trade show loop |

## How to regenerate

1. `cd wp-content/plugins/wb-ads-rotator-with-split-test`
2. Open `marketing/07-brand-assets/banners/source/*.html` — every file references `_shared.css`.
3. Serve locally via the existing WP nginx (files are already web-accessible at `http://<your-local>/wp-content/plugins/wb-ads-rotator-with-split-test/marketing/07-brand-assets/banners/source/<name>.html`).
4. In a browser at exact viewport size (1200×630 / 1080×1080 / 1920×600) take a viewport screenshot. Save to `banners/{angle}-{size}.png`.
5. No other build step — the HTML uses Google Fonts + a single shared CSS file.

Layout changes at different aspect ratios are handled by `@media (aspect-ratio:...)` rules in `_shared.css`. The same HTML produces all three sizes.

## Rules that were applied

- **No stock photos, no emojis, no AI faces.** None were needed — the decorative glyph + italic serif emphasis is the visual anchor.
- **Thumbnail-readable.** At 300px wide the headline is still legible because the display serif is large and the palette is high-contrast (ink on cream).
- **Palette audit vs. prior work.** Earlier Wbcom shots on this plugin use navy blue, slate blue, purple (admin UI + portal UI + frontend ad placeholders). Terracotta + amber + cream sits visually separate from every one of those — so blog posts using these banners won't feel like a reskinned screenshot.
- **Voice.** Every headline uses a short italic serif emphasis phrase; the italic + color shift does the persuasive work without exclamation marks or marketing gasping.
