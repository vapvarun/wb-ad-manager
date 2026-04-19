# WB Ad Manager Documentation

**Version:** 2.8.0
**Requires WordPress:** 5.8+
**Requires PHP:** 7.4+
**Tested up to WordPress:** 6.9.1

Welcome to WB Ad Manager, a powerful ad management plugin for WordPress. Display ads anywhere on your site with priority-based rotation, automatic placements, and click tracking.

---

## What's Included

| Feature | Description |
|---------|-------------|
| **Ad Management** | Create unlimited image, rich content, code, AdSense, and email capture ads |
| **14+ Placements** | Header, footer, content, sidebar, popup, sticky bar, and more |
| **Smart Targeting** | Schedule ads, target by device, location, user role |
| **Priority-Based Rotation** | Higher priority ads show more often in shared placements |
| **Link Management** | Create cloaked affiliate links with click tracking |
| **A/B Testing** | Compare ad performance and find winners directly in the editor |
| **BuddyPress & bbPress** | Native integration for community sites |

---

## Quick Navigation

### Getting Started

| Guide | Description |
|-------|-------------|
| [Installation](getting-started/01-installation.md) | Install and activate the plugin |
| [Quick Setup](getting-started/02-quick-setup-guide.md) | Configure your site in 10 minutes |

### For Site Owners

| Guide | Description |
|-------|-------------|
| [Managing Ads](for-site-owners/01-managing-ads.md) | Create, edit, and organize your ads |
| [Link Management](for-site-owners/02-link-management.md) | Create tracked affiliate links |
| [Ad Types](for-site-owners/03-ad-types.md) | Image, rich content, code, AdSense, and email capture ads |
| [Placements](for-site-owners/04-placements.md) | Where ads appear on your site — page, community (BuddyPress / bbPress / Jetonomy), popup, sticky |
| [Targeting](for-site-owners/05-targeting.md) | Schedule ads, target by device, user role, geo |
| [Settings](for-site-owners/06-settings.md) | Configure plugin settings |
| [Link Partnerships](for-site-owners/07-partnership-inquiries.md) | Accept inbound partnership inquiries from advertisers |

### Reference

| Guide | Description |
|-------|-------------|
| [Ad Shortcodes](shortcode-reference/01-ad-shortcodes.md) | Display specific ads with shortcodes |
| [Link Shortcodes](shortcode-reference/02-link-shortcodes.md) | Use link shortcodes |
| [Troubleshooting](troubleshooting/01-common-issues.md) | Common issues and fixes |

### For Developers

| Guide | Description |
|-------|-------------|
| [Developer Guide](DEVELOPER-GUIDE.md) | Architecture, hooks, filters, and APIs |

---

## Ad Types

| Type | Best For |
|------|----------|
| **Image Ad** | Banner ads, promotional graphics, affiliate banners |
| **Rich Content** | Native ads, content promotions, styled announcements |
| **Code/HTML/JS** | AdSense manual units, third-party networks, custom scripts |
| **Google AdSense** | Google AdSense with responsive sizing and auto-ads support |
| **Email Capture** | Newsletter signups, lead generation forms |

---

## Placements

Display ads in 14+ locations without writing code:

- **Page Positions:** Header, Footer, Before/After Content
- **In-Content:** After Paragraph X
- **Archive / Loop:** Before/After Archive
- **Widgets:** Sidebar, bbPress Forum Widget, bbPress Topic Widget
- **Overlays:** Popup (delay / scroll / exit-intent), Sticky Bar (4 positions)
- **Manual:** Shortcode `[wbam_ad]`
- **Comments:** Before/After Comments
- **BuddyPress:** Activity stream + 6 directory positions (members & groups)
- **bbPress:** 7 positions (forums, topics, between replies)
- **Jetonomy:** 7 positions (sidebar, topic, between replies)

Ads assigned to the same placement rotate automatically based on priority (1–10 slider set per ad).

---

## Shortcodes Quick Reference

```
[wbam_ad id="123"]               Display a specific ad by ID
[wbam_ads ids="1,2,3"]           Display multiple specific ads
[wbam_link id="123"]Click[/wbam_link]  Display tracked link
[wbam_links category="affiliate"] Display links list
[wbam_partnership_inquiry]        Partnership request form
```

---

## Upgrade to Pro

Want to let advertisers buy ad space on your site?

**WB Ad Manager Pro** adds:

- **Advertiser Portal** - Self-service dashboard for advertisers
- **Campaigns & Budgets** - CPM, CPC, and flat-rate pricing
- **Wallet & Payments** - Stripe, PayPal, WooCommerce integration
- **Classifieds Marketplace** - Built-in buy/sell listings
- **Advanced Analytics** - Detailed performance reports
- **Email Notifications** - Automated notifications for all events

[Learn more about Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/)

---

## Support

- **Documentation:** You're reading it
- **WordPress.org:** [Plugin Support Forum](https://wordpress.org/support/plugin/wb-ads-rotator-with-split-test/)
- **Pro Support:** [wbcomdesigns.com/support](https://wbcomdesigns.com/support/)

---

*Last updated: April 2026*
