---
title: Launch a Classifieds Marketplace
persona: Operator — Pro
tier: pro
one_job: Walk a site owner through enabling Classifieds and standing up a working marketplace — submissions, browse, inquiries, and moderation.
outcome: Reader has Classifieds module enabled, rules and upgrades configured, categories/locations seeded, and a test listing that goes through submission and review.
assumes: Pro activated and licensed, Wallet module enabled, a sense of the categories and locations that fit the audience.
---

# Launch a Classifieds Marketplace

![Frontend classifieds marketplace with 10 listings across categories](../images/flows/launch-classifieds-marketplace.png)

> **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.

This flow sets up a working classifieds marketplace: sellers submit listings, buyers browse and inquire, and you review and moderate from the admin. Budget about 30 minutes plus the time to seed categories and locations relevant to your audience. Run [Accept Paid Ads](accept-paid-ads.md) first if you want paid upgrades (Featured, Highlighted, Urgent, Bump, Top) — those deduct from the advertiser wallet.

## Before you start

- Pro is activated and licensed. See [Pro Installation & Requirements](../getting-started/pro-installation-requirements.md).
- The **Wallet** module is enabled in **Pro Settings → Modules** (Classifieds depends on Wallet).
- You know which taxonomies matter for your audience — categories (e.g., Cars, Jobs, Real Estate) and locations (cities or regions). Seed these up front so submissions have somewhere to live.

## Step 1 — Enable the Classifieds module

1. Go to **WB Ad Manager → Pro Settings → Modules**.
2. Toggle **Classifieds Marketplace** on.
3. Save.

The module registers the `wbam-classified` custom post type and two taxonomies: `wbam-classified-cat` (Categories) and `wbam-classified-loc` (Locations). The top-level **Classifieds** admin menu appears immediately.

## Step 2 — Configure marketplace rules

1. Go to **Pro Settings → Classifieds**.
2. Set:
   - **Listing Duration** — how many days a listing stays live before expiring. Default `30`.
   - **Require Approval** — on for moderated marketplaces, off for instant-publish.
   - **Max Images** per listing (default `10`, limit `50`).
   - **Max Classifieds Per Advertiser** — cap concurrent active listings per user. `0` = unlimited.
   - **Minimum Balance to Post** — require a wallet balance above zero before a user can submit. Set to `0` to let anyone with an active advertiser account post for free.
3. Under **Upgrade Options**, decide which paid upgrades to offer: Featured, Highlighted, Urgent, Bump, Top. Setting any price to `0` disables that upgrade. Defaults and durations live in [Pro Settings Configuration → Tab 6 Classifieds](../settings/pro-settings-configuration.md#tab-6-classifieds).
4. Under **Inquiries**, enable the inquiry system and the **Email Notifications** option so sellers get alerted.
5. Save.

## Step 3 — Seed categories and locations

Sellers cannot submit if there are no categories to file a listing under.

1. Go to **WB Ads → Classifieds → Categories** (WordPress taxonomy screen).
2. Add the top-level categories your marketplace needs. Use nested categories sparingly — the sidebar filter flattens them.
3. Go to **WB Ads → Classifieds → Locations** and add your regions.

Confirm at least three categories and three locations exist before you move on.

## Step 4 — Publish the marketplace pages

Create one WordPress page per shortcode. All five are reachable from a single navigation menu item once they exist.

| Page | Shortcode | Why you need it |
|------|-----------|-----------------|
| Browse Classifieds | `[wbam_browse_classifieds]` | Public grid with filters, the marketplace "homepage" |
| Submit a Listing | `[wbam_submit_classified]` | Multi-step seller form |
| My Listings | `[wbam_my_classifieds]` | Seller dashboard for their own active listings |
| My Favorites | `[wbam_my_favorites]` | Saved listings for logged-in visitors |
| Sellers I Follow | `[wbam_my_following]` | Feed from followed sellers |

Shortcode options are in [Pro Shortcodes Reference → Classifieds Shortcodes](../shortcode-reference/pro-shortcodes-reference.md#classifieds-shortcodes).

After publishing the pages, go to **Pro Settings → Pages** and confirm the Classifieds, My Classifieds, My Favorites, and Following pages are mapped to the pages you just created.

## Step 5 — Add the pages to the site menu

1. Go to **Appearance → Menus**.
2. Add links for **Browse Classifieds**, **Submit a Listing**, and **My Listings** to your primary nav.
3. Save.

This is the gateway — without it sellers cannot find the submit form and buyers cannot find the browse grid.

## Step 6 — Do a submission dry run

Using a second browser or incognito window, log in as a test user (who has an advertiser account from Step 6 of [Accept Paid Ads](accept-paid-ads.md)) and:

1. Visit the **Submit a Listing** page.
2. Walk through the multi-step form: Title → Description → Category + Location → Price → Images → Review.
3. Submit.

If **Require Approval** is on, the listing enters `pending`. If off, it publishes immediately.

Confirm in your admin session that the listing now exists under **Classifieds → All Listings** (or whatever plural label you configured under **Pro Settings → Classifieds**).

## Step 7 — Moderate the listing

This is the moderation workflow you will use day to day.

1. Go to **Classifieds → All Listings** and filter by status `pending`.
2. Click the listing to open it.
3. Review the title, description, images, category, and price.
4. From the admin bar:
   - Click **Approve** to publish the listing — the status moves to `active` and the seller gets an email.
   - Click **Reject** to decline — you can attach a moderation note visible to the seller.

The complete status set is `pending`, `active`, `sold`, `expired`, `rejected`, `draft`. Details in [Setting Up Classifieds → Admin Management](../classifieds/setting-up-classifieds.md#admin-management).

### Reports and takedowns

Any visitor can report a listing as inappropriate from the single listing page. Handle reports at **WB Ads → Classifieds → Reports**:

1. Filter by status `pending`.
2. Review the reason and the reporter's note.
3. Mark as `reviewed`, `resolved`, or `dismissed`.
4. If the listing needs to come down, open it from the report row and move its status to `rejected`.

## Step 8 — Confirm the buyer side works

In a fresh incognito window (do not log in):

1. Visit the **Browse Classifieds** page. The approved listing should render in the grid.
2. Apply one of the sidebar filters (category or location). The grid should update via AJAX without a page reload.
3. Open the listing and use the inquiry form at the bottom to send a message.
4. Switch to the seller's email inbox and confirm the inquiry notification arrived.
5. Log back in as the seller, open the **Inquiries** tab on the Advertiser Dashboard, and confirm the inquiry shows with status `unread`.

Inquiry storage lives in `wbam_classified_inquiries`; statuses cycle through `unread` → `read` → `replied` → `archived`.

## Step 9 — Test a paid upgrade (optional but recommended)

If you enabled upgrade pricing in Step 2, run the paid path at least once so you know the wallet wiring works.

1. As the seller, open **My Listings** and click **Promote** on the listing.
2. Pick **Featured** (or any enabled upgrade).
3. Confirm. The upgrade cost debits the wallet as a `classified` ledger entry.
4. Reload the Browse page in incognito. The listing now shows a Featured badge and pins to the top of its category.
5. Confirm a ledger entry exists under **WB Ads → Transactions** with type `classified`.

For Featured with **Recurring Monthly** billing, the `wbam_process_classified_billing` hourly cron charges the wallet each cycle. If the wallet cannot cover a renewal, the listing is downgraded to `standard` automatically.

## Verification — how to confirm the full flow worked

All seven must be true:

- **Pro Settings → Modules** shows **Classifieds Marketplace** enabled.
- At least three categories and three locations exist in **Classifieds → Categories** and **Classifieds → Locations**.
- Browse Classifieds, Submit a Listing, My Listings, My Favorites, and Sellers I Follow are each published and mapped in **Pro Settings → Pages**.
- A test submission was moved from `pending` to `active` in **Classifieds → All Listings**.
- The approved listing renders on the Browse page and the category / location sidebar filters update it.
- An inquiry sent from the single-listing page lands in the seller's **Inquiries** tab and triggers the notification email.
- If paid upgrades are enabled: at least one upgrade purchase shows a `classified` debit in **WB Ads → Transactions**.

## What to do next

- Map BuddyPress xProfile fields to seller profiles — in **Pro Settings → BuddyPress → xProfile Field Mapping**. Details in [Setting Up Classifieds → BuddyPress xProfile Seller Fields](../classifieds/setting-up-classifieds.md#buddypress-xprofile-seller-fields).
- Override template files (`submit-form.php`, `archive.php`, `single.php`) by copying them from `wp-content/plugins/wb-ad-manager-pro/templates/classifieds/` to `wp-content/themes/your-theme/wbam/classifieds/`.
- Build a classifieds homepage hero by combining `[wbam_classified_search]` with a curated `[wbam_browse_classifieds category="5" limit="6"]`.
- If renewal emails fail or listings do not expire on schedule, work through [Pro Troubleshooting](../troubleshooting/pro-troubleshooting.md).
