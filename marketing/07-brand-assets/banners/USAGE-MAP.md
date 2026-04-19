# Banner usage map

One place that maps each of the 15 banners to the campaigns, posts, emails, and pages where it belongs. Update this file when angles or placements change — do not sprinkle banner references across 30 post files.

## Quick lookup

| Banner | Size | Primary use | Secondary uses |
|---|---|---|---|
| `three-in-one-og.png` | 1200×630 | Plugin home page OG tag | Reddit intro posts; Twitter thread opener; Dev.to cover |
| `three-in-one-square.png` | 1080×1080 | Instagram explainer post | LinkedIn overview post; Mastodon intro |
| `three-in-one-wide.png` | 1920×600 | Newsletter top banner | Blog category header for "getting started"; sales-deck cover |
| `members-sell-ads-og.png` | 1200×630 | Pro sales page OG tag; LinkedIn "let members sell ads" post | Email #2 in the free→Pro sequence; forum outreach to publishers |
| `members-sell-ads-square.png` | 1080×1080 | Upgrade campaign IG / LinkedIn feed | Community membership Twitter |
| `members-sell-ads-wide.png` | 1920×600 | Free→Pro upgrade email header | Webinar landing banner |
| `free-ships-features-og.png` | 1200×630 | WordPress.org listing OG | Blog post: "why our free version isn't crippleware"; r/WordPress share |
| `free-ships-features-square.png` | 1080×1080 | WP-community IG / LinkedIn feed | Freemium-skeptic ad set |
| `free-ships-features-wide.png` | 1920×600 | Free-install welcome email header | WP Tavern / WP news pitch graphic |
| `classifieds-marketplace-og.png` | 1200×630 | Classifieds use-case landing OG; Pro feature deep-dive blog | Marketplace / community outreach |
| `classifieds-marketplace-square.png` | 1080×1080 | IG / LinkedIn for classifieds angle | Facebook group promo |
| `classifieds-marketplace-wide.png` | 1920×600 | Classifieds landing page header | Case study article banner |
| `affiliate-link-cloaker-og.png` | 1200×630 | Affiliate forum / Reddit niche-site posts | Link-management blog post OG |
| `affiliate-link-cloaker-square.png` | 1080×1080 | Pinterest / IG affiliate-marketer angle | Twitter to affiliate community |
| `affiliate-link-cloaker-wide.png` | 1920×600 | Affiliate-marketer email newsletter | YouTube video thumbnail base for link tutorials |

## By campaign surface

### Plugin home page + wbcomdesigns.com

- **OG / Twitter card**: `three-in-one-og.png` (home) or `members-sell-ads-og.png` (Pro landing)
- **Landing page hero** (if switching from the existing portal-overview.png): `three-in-one-wide.png`

### WordPress.org listing

- **Banner (772×250 / 1544×500)** — generate when we ship to WP.org. Until then, use `free-ships-features-wide.png` as a placeholder for store graphics.
- **OG for the listing page**: `free-ships-features-og.png`

### Email sequences (`marketing/04-email-sequences/`)

| Sequence | Header banner |
|---|---|
| `01-welcome-sequence.md` (Free install) | `free-ships-features-wide.png` |
| `02-free-to-pro-upgrade.md` | `members-sell-ads-wide.png` |
| `03-feature-announcement.md` | `three-in-one-wide.png` |
| `04-re-engagement.md` | `three-in-one-og.png` (embedded at 1200px) |

### Social media (`marketing/05-social-media/`)

Map by post angle rather than platform — copy the file that matches the post's theme:

- Post about **what the plugin does overall** → `three-in-one-{og|square}.png`
- Post about **self-serve advertisers / monetization** → `members-sell-ads-{og|square}.png`
- Post about **free version value** → `free-ships-features-{og|square}.png`
- Post about **classifieds marketplace** → `classifieds-marketplace-{og|square}.png`
- Post about **affiliate links** → `affiliate-link-cloaker-{og|square}.png`

Use `-og` (1200×630) for Twitter / LinkedIn share cards; `-square` (1080×1080) for IG / LinkedIn feed; `-wide` (1920×600) for newsletter / blog header use only (cropped or letterboxed on most social surfaces).

### Sales materials (`marketing/06-sales-materials/`)

- **One-pager** (`01-one-pager.md`): already uses `portal-overview.png` screenshot. Add `three-in-one-wide.png` at the top as a masthead; keeps the product screenshot as the evidence shot.
- **Comparison chart / ROI / objections**: no banner — these are data-dense, stay text-first.

## What NOT to do

- Don't use banners inside `docs/website/` — that tree is accurate reference documentation; banners belong to `marketing/`.
- Don't use banners on WordPress.org feature screenshots (1544×500 slots) — those need product-truth screenshots, not marketing compositions. Banners only go in social / blog / email surfaces.
- Don't recolour the banners to fit a different campaign. If an angle is wrong, file a new angle in `source/` and render three sizes; keep the palette consistent.
- Don't embed the same banner at multiple places on the same page. They're wayfinders, not decoration.
