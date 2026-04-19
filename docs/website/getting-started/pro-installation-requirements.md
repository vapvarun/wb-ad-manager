# WB Ad Manager Pro - Installation & Requirements

> **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.

WB Ad Manager Pro adds a monetization layer on top of the free plugin. You get an advertiser portal, a classifieds marketplace, a credit wallet, and the campaign / analytics / A/B testing / link-tracking modules that let you run a real ad platform on your site. Everything in the free plugin keeps working; Pro is strictly additive.

Install it on top of the free plugin once you're ready to let other people (advertisers, classified sellers, community members) pay you to appear in your ad slots or listings.

---

## Requirements

### Required

| Requirement | Minimum Version |
|-------------|----------------|
| WB Ad Manager (free) | 2.7.0 or higher |
| WordPress | 5.8 or higher |
| PHP | 7.4 or higher |
| Valid license key | — |

WB Ad Manager (free) must be installed and activated before activating the Pro addon. The Pro plugin declares `Requires Plugins: wb-ads-rotator-with-split-test` and will not activate without it.

### Optional

| Dependency | Adds |
|------------|------|
| BuddyPress or BuddyBoss | Advertiser tabs in member profiles, xProfile seller field mapping |
| WooCommerce | WooCommerce checkout as a wallet top-up payment method |

---

## Installation

### Step 1: Install WB Ad Manager (Free)

If you have not already installed the free plugin:

1. Go to **Plugins → Add New**
2. Search for **WB Ad Manager**
3. Click **Install Now**, then **Activate**

### Step 2: Install the Pro Addon

1. Download the Pro ZIP from your [Wbcom Designs account](https://wbcomdesigns.com)
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Step 3: Enter Your License Key

1. Go to **WB Ad Manager Pro → Settings → License**
2. Paste your license key
3. Click **Activate License**

Your license is validated against the Wbcom Designs store. An active license is required to receive automatic plugin updates.

---

## Initial Setup

### 1. Create Required Pages

Create a WordPress page for the advertiser dashboard and add this shortcode to its content:

```
[wbam_advertiser_dashboard]
```

This page becomes the self-service portal where advertisers manage ads, campaigns, wallet, classifieds, and analytics.

If you plan to use classifieds, also create pages for:

```
[wbam_submit_classified]
[wbam_browse_classifieds]
[wbam_my_classifieds]
```

### 2. Configure at least one credit top-up adapter

Pro uses the Wbcom Credits SDK rather than shipping its own Stripe, PayPal, or Razorpay integration. Credits reach an advertiser's wallet through an **adapter** that connects the SDK to a plugin you already use for payments. Open **WB Ads → Pro Settings → Credits** and enable whichever adapter matches the plugin(s) active on your site.

| Adapter | Requires | Accepts payment via |
|---------|----------|---------------------|
| WooCommerce Products | WooCommerce | Any WC-supported gateway — Stripe, PayPal, Razorpay, Square, bank transfer, etc. |
| WooCommerce Subscriptions | WooCommerce Subscriptions | Any WC-supported gateway, billed on each renewal |
| WooCommerce Memberships | WooCommerce Memberships | Any WC-supported gateway, credit bundled into the membership |
| Paid Memberships Pro | PMPro | Gateways supported by PMPro (Stripe, PayPal, Braintree, etc.) |
| MemberPress | MemberPress | Gateways supported by MemberPress |
| Manual / Bank Transfer | — | Offline payment approved manually from the admin Transactions page |

Advertisers can also request manual/bank-transfer top-ups directly from their Wallet tab; you approve them from **WB Ads → Transactions → Pending Approval** once funds have cleared.

See [Wallet and Payments](../payments/wallet-and-payments.md) for the full setup walkthroughs for each adapter path.

### 3. Enable Modules

All Pro features are organized into modules. Each module can be toggled independently at **WB Ad Manager Pro → Settings → Modules**.

| Module | What It Provides |
|--------|-----------------|
| Wallet | Prepaid credit system, transaction history, payment processing |
| Advertisers | Advertiser accounts, approval workflow, frontend portal |
| Campaigns | Budget-based ad campaigns with CPM/CPC/flat pricing |
| Packages | Pricing tiers for ad submissions |
| Ad Submissions | Advertiser-submitted ad creatives |
| Classifieds | Peer-to-peer classified listings marketplace |
| Analytics | Impression and click tracking with charts |
| A/B Testing | Split-test multiple ad variants |
| Rotation | Fair impression rotation engine |
| Links | Advanced link tracking and keyword linking |
| BuddyPress | BuddyPress/BuddyBoss member integration |
| Notifications | Email notification system |

Disabling a module hides its admin menus, deregisters its REST API routes, and removes its shortcodes. Data is preserved in the database.

### 4. Create Ad Packages

Go to **WB Ad Manager Pro → Packages → Add New** to create your first pricing package.

Each package defines:

- Package name and description
- Pricing model: flat rate, CPM (cost per 1,000 impressions), or CPC (cost per click)
- Price per unit or flat price
- Which ad placements the package applies to
- Duration and impression limits
- Optional setup fee

Advertisers select a package when submitting an ad through the portal.

---

## Budget Reservation System

For campaigns using CPM or CPC pricing with a set budget, WB Ad Manager Pro pre-reserves the full budget amount from the advertiser's wallet when the campaign is activated. This prevents campaigns from overspending beyond the available balance.

- **On activation:** Budget amount is reserved (locked, not spent)
- **As ads serve:** Reserved funds are moved to spent incrementally
- **On completion or cancellation:** Unspent reserved funds are refunded to the wallet

Flat-rate campaigns do not use budget reservation — the package price is debited at submission.

---

## What's Included

| Feature | Description |
|---------|-------------|
| Advertiser Portal | 12-tab self-service frontend dashboard |
| Classifieds System | Full listings marketplace with categories, locations, upgrades, inquiries, reports, favorites, and seller following |
| Wallet System | Prepaid credit wallet with full transaction history |
| Credit Top-up Adapters | WooCommerce Products, WooCommerce Subscriptions, WooCommerce Memberships, Paid Memberships Pro, MemberPress, plus manual / bank transfer |
| Campaign Management | Draft, active, paused, completed statuses; CPM/CPC/flat pricing; budget reservation |
| Analytics Dashboard | Impressions, clicks, CTR with date-range charts |
| A/B Testing | Split-test ad variants to find top performers |
| Ad Rotation | Weighted and fair impression rotation engine with share-of-voice reporting |
| Package System | Multiple pricing tiers with placement restrictions |
| Link Tracking | Affiliate link management, keyword auto-linking, link health checks |
| BuddyPress Integration | Advertiser tabs in member profiles, xProfile seller field mapping |
| REST API | Programmatic access to classifieds, campaigns, wallet, and analytics |
| Audit Log | Admin log of all significant account and billing events |

---

## Database

On activation, the installer creates all required tables and sets DB version `2.8.0`. The installer runs automatic upgrade routines on plugin updates — no manual database work is needed.

Key tables created:

| Table | Purpose |
|-------|---------|
| `wbam_advertisers` | Advertiser accounts |
| `wbam_campaigns` | Ad campaigns |
| `wbam_classifieds` | Classified listings |
| `wbam_classified_meta` | Custom fields for classifieds (added DB 2.8.0) |
| `wbam_classified_inquiries` | Buyer-to-seller messages |
| `wbam_classified_reports` | Inappropriate content reports |
| `wbam_transactions` | Wallet transaction ledger |
| `wbam_packages` | Ad pricing packages |

---

## Next Steps

- [Advertiser Portal Overview](../advertiser-portal/advertiser-portal-overview.md) — Learn about the 12-tab dashboard
- [Setting Up Classifieds](../classifieds/setting-up-classifieds.md) — Configure the listings marketplace
- [Wallet & Payments](../payments/wallet-and-payments.md) — Configure payment gateways
