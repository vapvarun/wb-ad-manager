# WB Ad Manager Documentation

Single documentation set covering **both** the Free plugin
(`wb-ads-rotator-with-split-test`) and the **Pro** add-on
(`wb-ad-manager-pro`). Per the team `wbcom-docs` guideline, all
user-facing guides live under `docs/website/`. Guides marked
**PRO** in the navigation below require the Pro add-on — every
Pro doc also carries an inline callout at the top of the file so a
reader opening the Markdown directly can tell at a glance.

**Plugin root docs:**
- [`website/`](website/) — user-facing guides.
- [`DEVELOPER-GUIDE.md`](DEVELOPER-GUIDE.md) — Free-plugin developer reference (hooks, REST routes, ad-type and placement extension points).

**Pro developer reference** lives in the Pro repo
(`wb-ad-manager-pro/docs/`) because it tracks a different release
cycle: `DEVELOPER-GUIDE.md` and `HOOKS.md`.

---

## Getting Started

| Guide | Tier |
|-------|------|
| [Installation](website/getting-started/installation.md) | Free |
| [Quick Setup Guide](website/getting-started/quick-setup-guide.md) | Free |
| [Pro Installation & Requirements](website/getting-started/pro-installation-requirements.md) | **PRO** |

## Ad Management — Free

| Guide |
|-------|
| [Managing Ads](website/ad-management/managing-ads.md) |
| [Ad Types](website/ad-management/ad-types.md) |
| [Placements](website/ad-management/placements.md) |
| [Targeting & Scheduling](website/ad-management/targeting.md) |
| [Settings](website/ad-management/settings.md) |

## Link Management — Free

| Guide |
|-------|
| [Link Management](website/link-management/link-management.md) |
| [Link Partnerships](website/link-management/partnership-inquiries.md) |

## Advertiser Portal — **PRO**

| Guide |
|-------|
| [Advertiser Portal Overview](website/advertiser-portal/advertiser-portal-overview.md) |
| [Ad Submissions & Approval Workflow](website/advertiser-portal/ad-submissions-approval-workflow.md) |
| [Campaign Management](website/advertiser-portal/campaign-management.md) |
| [Link Management System](website/advertiser-portal/link-management-system.md) |

## Analytics — **PRO**

| Guide |
|-------|
| [Analytics Dashboard](website/analytics/analytics-dashboard.md) |

## Classifieds — **PRO**

| Guide |
|-------|
| [Setting Up Classifieds](website/classifieds/setting-up-classifieds.md) |

## Payments & Wallet — **PRO**

| Guide |
|-------|
| [Wallet and Payments](website/payments/wallet-and-payments.md) |

## Pro Settings — **PRO**

| Guide |
|-------|
| [Pro Settings Configuration](website/settings/pro-settings-configuration.md) |
| [Creating Ad Packages](website/settings/creating-ad-packages.md) |

## Shortcode Reference

| Guide | Tier |
|-------|------|
| [Ad Shortcodes](website/shortcode-reference/ad-shortcodes.md) | Free |
| [Link Shortcodes](website/shortcode-reference/link-shortcodes.md) | Free |
| [Pro Shortcodes Reference](website/shortcode-reference/pro-shortcodes-reference.md) | **PRO** |

## Troubleshooting

| Guide | Tier |
|-------|------|
| [Common Issues](website/troubleshooting/common-issues.md) | Free |
| [Pro Troubleshooting](website/troubleshooting/pro-troubleshooting.md) | **PRO** |

---

## Editing guideline

- All user-facing Markdown goes into `docs/website/<category>/<slug>.md`.
- Add every new file to `docs/website/docs_config.json` under the
  matching category so the `wbcom-docs` MCP can pick it up.
- Pro-only pages set `"tier": "pro"` in the config entry **and**
  start with the Pro callout blockquote:
  > **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.
- Developer-only content stays outside `website/` — at `DEVELOPER-GUIDE.md`
  in the plugin that owns it.
