# Pro Shortcodes Reference

> **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.

Complete reference for all 12 WB Ad Manager Pro shortcodes. Each shortcode requires the corresponding module to be enabled in **Pro Settings → Modules**.

---

## Advertiser Portal Shortcodes

These shortcodes require the **Advertisers** module. All portal shortcodes redirect unauthenticated users to a login form.

---

### `[wbam_advertiser_dashboard]`

Renders the full advertiser portal with a sidebar navigation layout. This is the primary shortcode — place it on the Advertiser Dashboard page.

**Attributes:**

| Attribute | Default | Description |
|-----------|---------|-------------|
| `show_tabs` | `overview,ads,campaigns,classifieds,inquiries,favorites,following,links,wallet,analytics,share-of-voice,profile` | Comma-separated list of tabs to display. Classifieds-related tabs only appear when the Classifieds module is enabled. The `links` tab only appears when the free Links module's `Partnership_Form` class is present |

**Available tab slugs:**

| Slug | Label | Notes |
|------|-------|-------|
| `overview` | Overview | Balance, recent activity, quick stats |
| `ads` | My Ads | Create, edit, view submitted ads |
| `campaigns` | Campaigns | Campaign management |
| `classifieds` | Classifieds | Manage classified listings (Classifieds module required) |
| `inquiries` | Inquiries | View inquiries on classifieds (Classifieds module required) |
| `favorites` | Favorites | Saved/favorited classifieds (Classifieds module required) |
| `following` | Following | Sellers the user follows |
| `links` | Links | Link partnership requests (free Links module required) |
| `wallet` | Wallet | Balance, top-up, transaction history |
| `analytics` | Analytics | Impression and click stats for the advertiser's own ads |
| `share-of-voice` | Share of Voice | Advertiser's share of total impressions |
| `profile` | Profile | Advertiser profile settings |

**Examples:**

```
[wbam_advertiser_dashboard]
[wbam_advertiser_dashboard show_tabs="overview,ads,wallet,profile"]
[wbam_advertiser_dashboard show_tabs="overview,ads,campaigns,wallet,analytics,profile"]
```

---

### `[wbam_my_ads]`

Displays only the advertiser's ads list. Use this when you want a standalone ads list without the full portal.

**Attributes:** None

**Requires:** User must be logged in and have an advertiser account.

```
[wbam_my_ads]
```

---

### `[wbam_advertiser_stats]`

Renders a small stats widget showing the advertiser's wallet balance and count of active ads. Suitable for sidebars or widget areas.

**Attributes:** None

```
[wbam_advertiser_stats]
```

---

### `[wbam_advertiser_wallet]`

Displays the wallet section only — balance, transaction history, and the Add Funds button. Use this when you want a standalone wallet page separate from the full dashboard.

**Attributes:** None

```
[wbam_advertiser_wallet]
```

---

### `[wbam_seller_profile]`

Displays a public seller profile page showing the advertiser's information and their active classified listings.

**Attributes:**

| Attribute | Default | Description |
|-----------|---------|-------------|
| `id` | `0` | Advertiser ID to display. If `0`, the shortcode reads the `?seller=` query parameter from the URL |
| `limit` | `12` | Number of classified listings to display |
| `columns` | `3` | Grid columns for the listings grid |

**Examples:**

```
[wbam_seller_profile]
[wbam_seller_profile id="42"]
[wbam_seller_profile id="42" limit="6" columns="2"]
```

---

## Ad Submission Shortcodes

These shortcodes require the **Ad Submissions** module (which in turn requires Wallet, Campaigns, and Packages modules).

---

### `[wbam_submit_ad]`

Legacy shortcode that redirects the user to the Advertiser Dashboard with the ad creation form open (`?action=create-ad`). The standalone ad submission wizard was replaced by the dashboard form in v1.1.0.

**Attributes:** None (all attributes are ignored)

**Note:** If no Advertiser Dashboard page is set in **Pro Settings → Pages**, this shortcode shows a warning message instead of redirecting.

```
[wbam_submit_ad]
```

---

## Classifieds Shortcodes

All classifieds shortcodes require the **Classifieds** module to be enabled.

---

### `[wbam_submit_classified]`

Displays the classified submission form. Requires the user to be logged in and have an advertiser account. Users without an advertiser account see a prompt to register first.

**Attributes:**

| Attribute | Default | Description |
|-----------|---------|-------------|
| `redirect` | `''` | URL to redirect to after successful submission. Leave empty to use the default behaviour |

```
[wbam_submit_classified]
[wbam_submit_classified redirect="https://example.com/my-classifieds"]
```

---

### `[wbam_my_classifieds]`

Displays the logged-in advertiser's classified listings with full management options: view, edit, promote (featured, highlighted, bump, urgent, top), mark as sold, renew, and delete.

**Attributes:**

| Attribute | Default | Description |
|-----------|---------|-------------|
| `limit` | `10` | Number of listings to show per page |

```
[wbam_my_classifieds]
[wbam_my_classifieds limit="20"]
```

---

### `[wbam_my_favorites]`

Displays the logged-in user's saved/favorited classified listings in a grid. Requires login.

**Attributes:**

| Attribute | Default | Description |
|-----------|---------|-------------|
| `limit` | `12` | Number of favorited listings to display |
| `columns` | `3` | Grid column count |

```
[wbam_my_favorites]
[wbam_my_favorites limit="24" columns="4"]
```

---

### `[wbam_my_following]`

Displays the list of sellers that the logged-in user follows. Each seller card shows their avatar, follower count, total listing count, and up to 3 recent listings. Requires login.

**Attributes:**

| Attribute | Default | Description |
|-----------|---------|-------------|
| `limit` | `24` | Maximum number of followed sellers to display |

```
[wbam_my_following]
[wbam_my_following limit="12"]
```

---

### `[wbam_browse_classifieds]`

Public classifieds archive with a sidebar filter panel. Supports real-time URL-based filtering — category, location, keyword, price range, and condition filters are applied via query parameters so the filtered view is bookmarkable and shareable.

**Attributes:**

| Attribute | Default | Description |
|-----------|---------|-------------|
| `limit` | `12` | Listings per page |
| `category` | `''` | Pre-filter by category term ID |
| `location` | `''` | Pre-filter by location term ID |
| `columns` | `3` | Grid column count |
| `sidebar` | `left` | Filter sidebar position: `left`, `right`, or `none` |

URL query parameters accepted: `cpage` (pagination), `category`, `location`, `q` (keyword search), `orderby`, `min_price`, `max_price`, `condition[]`

```
[wbam_browse_classifieds]
[wbam_browse_classifieds limit="24" columns="4" sidebar="left"]
[wbam_browse_classifieds category="5" sidebar="none"]
```

---

### `[wbam_classified_search]`

Renders a search form widget with keyword input, category dropdown, and location dropdown. Submits to the configured results page or to the URL set via `results_page`.

**Attributes:**

| Attribute | Default | Description |
|-----------|---------|-------------|
| `results_page` | `''` | URL of the page that contains `[wbam_browse_classifieds]`. If empty, the form action is the current page URL |

```
[wbam_classified_search]
[wbam_classified_search results_page="https://example.com/classifieds"]
```

---

## Module Requirements Summary

| Shortcode | Required Module |
|-----------|----------------|
| `[wbam_advertiser_dashboard]` | Advertisers |
| `[wbam_my_ads]` | Advertisers |
| `[wbam_advertiser_stats]` | Advertisers |
| `[wbam_advertiser_wallet]` | Advertisers |
| `[wbam_seller_profile]` | Advertisers |
| `[wbam_submit_ad]` | Ad Submissions |
| `[wbam_submit_classified]` | Classifieds |
| `[wbam_my_classifieds]` | Classifieds |
| `[wbam_my_favorites]` | Classifieds |
| `[wbam_my_following]` | Classifieds |
| `[wbam_browse_classifieds]` | Classifieds |
| `[wbam_classified_search]` | Classifieds |
