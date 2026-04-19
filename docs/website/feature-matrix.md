---
title: Feature Matrix — Free vs. Pro
persona: Evaluator — Free
tier: free
one_job: Let a prospective user compare exactly what ships in Free versus Pro so they can decide whether they need the Pro add-on.
outcome: Reader can find any capability in the matrix, see whether Free and/or Pro supports it, and read a one-line description of what it does.
assumes: None — this is a decision page for evaluators before they install anything.
---

# Feature Matrix — Free vs. Pro

Use this page to decide whether you need the Pro add-on or whether the free plugin covers your use case. Each row names a capability, marks whether it ships in Free and Pro, and describes what the capability actually does. Pro always sits on top of Free — nothing in Free is removed when Pro activates.

Free is the ad-rotation and tracked-link engine: self-serve admin places ads, admin counts impressions and clicks. Pro adds the advertiser-facing portal, the credit wallet and Credits-SDK adapters, the classifieds marketplace, campaign budgeting, deeper analytics, A/B testing, and the developer hook surface.

## Matrix

| Capability | Free | Pro | Description |
|------------|------|-----|-------------|
| Ad post type + admin list | ✓ | ✓ | `wbam-ad` custom post type managed from `WB Ad Manager → Ads` |
| Image ad type | ✓ | ✓ | Upload JPG / PNG / GIF / WebP with destination URL and alt text |
| Rich-content ad type | ✓ | ✓ | HTML body sanitized via `wp_kses_post` |
| Code / HTML / JS ad type | ✓ | ✓ | Third-party ad network snippets, AdSense code, custom JS |
| Google AdSense ad type | ✓ | ✓ | Dedicated AdSense field with auto / horizontal / vertical / rectangle formats |
| Email Capture ad type | ✓ | ✓ | Newsletter-style lead-capture ad |
| Placement metabox (per-ad) | ✓ | ✓ | Ad picks its own placements via checkboxes; no separate zone admin |
| Automatic placements (header, footer, before/after content, sidebar, after paragraph N) | ✓ | ✓ | Ads render without shortcodes where the checkbox is on |
| Shortcode placement — `[wbam_ad id]` | ✓ | ✓ | Drop a single ad by ID into any post, page, widget, or theme template |
| Shortcode placement — `[wbam_ads ids]` | ✓ | ✓ | Drop a pinned set of ads by comma-separated ID list |
| Priority-weighted rotation | ✓ | ✓ | Priority 1–10 per ad; higher shows more often when multiple ads share a placement |
| Session impression limit | ✓ | ✓ | Cap per-visitor exposure per session |
| Schedule start / end dates | ✓ | ✓ | Publish ahead of time; expire automatically |
| Built-in A/B comparison metabox | ✓ | ✓ | Side-by-side CTR bar chart when multiple ads share a placement; Winner badge at 100+ impressions |
| Dedicated A/B Testing module | — | ✓ | Variant management, weighted splits, programmatic enroll / disqualify |
| Weighted fair-rotation engine | — | ✓ | Rotation module replaces default priority sort; share-of-voice reporting |
| Impressions + clicks counts in Ads list | ✓ | ✓ | Columns on the Ads admin list |
| Analytics dashboard with charts | — | ✓ | Date-range impression / click / CTR charts, per-ad / per-placement / per-date breakdowns |
| Bot filtering (40-pattern UA list) | — | ✓ | Rejects Googlebot, Bingbot, Ahrefsbot, SEMrushbot, headless-browser signatures, etc. |
| IP-hash anonymization with daily-rotating salt | — | ✓ | GDPR-friendly anonymization; salt rotates at UTC midnight |
| Cookie-consent integration (CookieYes, Complianz, Moove GDPR, Cookie Notice) | — | ✓ | Only records tracking events after consent |
| Managed link CPT + categories | ✓ | ✓ | `/go/<slug>` redirect URLs behind every tracked link |
| Click-tracking on managed links | ✓ | ✓ | Total clicks, unique clicks, referrers, click dates |
| `[wbam_link]`, `[wbam_links]`, `[wbam_link_url]` shortcodes | ✓ | ✓ | Inline anchor, list / grid / inline layout, raw URL for custom HTML |
| Link nofollow / sponsored / UGC flags | ✓ | ✓ | Per-link and per-shortcode SEO attributes |
| Partnership inquiry form | ✓ | ✓ | `[wbam_partnership_inquiry]` captures visitor requests; approval creates the link automatically |
| Keyword auto-linking | — | ✓ | Links Pro module rewrites keywords in post content to tracked links |
| Broken-link health checks | — | ✓ | Scheduled scan flags dead destinations in the links admin |
| Advertiser accounts | — | ✓ | `wbam_advertisers` table, approval workflow, suspended / banned states |
| Advertiser Dashboard (`[wbam_advertiser_dashboard]`) | — | ✓ | 12-tab frontend portal: Overview, Ads, Campaigns, Classifieds, Inquiries, Favorites, Following, Links, Wallet, Analytics, Share of Voice, Profile |
| Self-service ad submission wizard | — | ✓ | Four-step wizard (Ad Type → Content → Package → Placements) inside the dashboard |
| Admin submission review queue | — | ✓ | `WB Ads → Submissions` with approve / reject / request changes, plus bulk actions |
| Trust-based auto-approval | — | ✓ | Paid submissions from advertisers with N approved ads auto-approve; code/HTML always held for review |
| Ad packages (pricing tiers) | — | ✓ | Flat / CPM / CPC / CPM+CPC packages with duration, impression / click limits, placement restrictions |
| Campaign lifecycle | — | ✓ | Draft → Pending → Active → Paused → Completed / Expired / Cancelled state machine |
| Pre-funded budget reservation | — | ✓ | CPM / CPC campaigns pre-charge full budget; unused portion refunded on completion |
| Unlimited-budget hourly billing | — | ✓ | `wbam_calculate_hourly_billing` cron charges for prior hour's impressions / clicks |
| Auto-pause on budget or limit exhaustion | — | ✓ | `wbam_do_check_campaign_budgets` cron moves campaigns to Completed every 15 min |
| Credit wallet with ledger | — | ✓ | Per-advertiser balance, nine transaction types, atomic row-locked credit / debit |
| Wbcom Credits SDK adapter — WooCommerce Products | — | ✓ | Sell credit-pack products via any WC-supported gateway |
| Wbcom Credits SDK adapter — WooCommerce Subscriptions | — | ✓ | Recurring credit top-ups on subscription renewal |
| Wbcom Credits SDK adapter — WooCommerce Memberships | — | ✓ | Credits bundled into membership activation and renewal |
| Wbcom Credits SDK adapter — Paid Memberships Pro | — | ✓ | Map PMPro levels to credit grants |
| Wbcom Credits SDK adapter — MemberPress | — | ✓ | Map MemberPress products to credit grants |
| Manual / bank-transfer top-ups | — | ✓ | Advertiser submits reference note; admin approves from `WB Ads → Transactions → Pending Approval` |
| Admin wallet adjustments | — | ✓ | Credit or debit any advertiser with a reason; lands in ledger as `adjustment` |
| Classifieds marketplace | — | ✓ | `wbam-classified` CPT with categories, locations, AJAX filters, favorites, follow-seller |
| Paid classified upgrades (Featured, Highlighted, Urgent, Bump, Top) | — | ✓ | Wallet-debited promotions with configurable prices and durations |
| Classified inquiry system | — | ✓ | Buyer-to-seller messages stored in `wbam_classified_inquiries`; seller gets email |
| Classified moderation + reports | — | ✓ | Admin review queue plus visitor-submitted inappropriate-content reports |
| BuddyPress / BuddyBoss integration | — | ✓ | Advertiser tabs on member profiles; xProfile field mapping onto seller pages |
| Seller profile pages (`/seller/{slug}/`) | — | ✓ | Public page per advertiser showing their listings and follow button |
| Email notifications module | — | ✓ | Registration, ad approved / rejected, campaign status, low balance, classified events, inquiries, payments |
| Site Mode presets | — | ✓ | Five operator modes (Publisher, Sponsored, Classifieds-only, Full marketplace, Custom) with billing-UI gates |
| Free-plugin public hooks | ✓ | ✓ | `wbam_register_ad_types`, `wbam_register_placements`, `wbam_ad_rendered`, etc. |
| Pro write-path hooks (`wbam_pro_before_/after_*`) | — | ✓ | ~20 create / update / delete hooks across advertisers, campaigns, classifieds, packages, submissions |
| Pro REST response-shape filters (`wbam_pro_rest_prepare_*`) | — | ✓ | Four filters on Packages + Classifieds (public / user / admin scope) |
| Ad submission wizard render slots | — | ✓ | Four action slots, one per wizard step, for injecting custom fields |
| Cross-plugin bridge filter (`wbam_pro_free_setting_{key}`) | — | ✓ | Override any Free-plugin setting read made by Pro |
| REST API namespace | — | ✓ | `wbam-pro/v1` endpoints for classifieds, campaigns, advertisers, wallet, packages, analytics |
| Audit log | — | ✓ | Admin log of account, billing, and moderation events |
| Delete-all-data uninstall toggle | — | ✓ | Optional clean wipe on plugin uninstall |

## Minimums and identifiers

- Free plugin slug: `wb-ads-rotator-with-split-test`. Required WordPress 5.8+, PHP 7.4+.
- Pro plugin slug: `wb-ad-manager-pro`. Declares `Requires Plugins: wb-ads-rotator-with-split-test`.
- Pro DB version: `3.8.0`, stored in option `wbam_pro_db_version`.
- Optional integrations on top of Pro: BuddyPress / BuddyBoss, WooCommerce, WooCommerce Subscriptions, WooCommerce Memberships, Paid Memberships Pro, MemberPress.

## What to read next

- [Pro Installation & Requirements](getting-started/pro-installation-requirements.md) — what Pro activation looks like step by step.
- [Wallet and Payments](payments/wallet-and-payments.md) — which adapter to pick and how credits reach the wallet.
- [Pro Settings Configuration](settings/pro-settings-configuration.md) — all seven settings tabs, module toggles, and the Credits adapter table.
- [Accept Paid Ads](flows/accept-paid-ads.md) — end-to-end flow from Pro activation to first cleared advertiser payment.
