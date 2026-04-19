# Campaign Management

> **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.

Campaigns control how ads run — their budget, pricing model, duration, and delivery limits. This guide explains every campaign status, how budget reservation works, and how campaigns move through their lifecycle.

## What Is a Campaign?

A campaign links an ad to its delivery rules. Each campaign tracks:

- **Budget** - Total spend limit (0 = unlimited)
- **Pricing model** - How charges are calculated
- **Impressions and clicks** - Running totals with optional caps
- **Date range** - Start and end dates for delivery
- **Status** - Current state in the campaign lifecycle

A campaign is created automatically when an advertiser submits an ad through a package. You can also create and manage campaigns manually from **WB Ads > Campaigns**.

## Campaign Statuses

| Status | Meaning |
|--------|---------|
| **Draft** | Created but not yet submitted for review |
| **Pending** | Awaiting admin approval (set when package requires approval) |
| **Active** | Running and delivering ads |
| **Paused** | Temporarily stopped by the advertiser or system |
| **Completed** | Budget exhausted or all limits reached |
| **Expired** | End date passed while campaign was active |
| **Cancelled** | Stopped by admin or advertiser before completion |

## Status Transition Rules

Campaigns follow a strict state machine. Not every status change is permitted.

| From | Can move to |
|------|------------|
| Draft | Pending, Active, Cancelled |
| Pending | Active, Cancelled |
| Active | Paused, Completed, Expired, Cancelled |
| Paused | Active, Completed, Cancelled |
| Completed | (none — final state) |
| Expired | (none — final state) |
| Cancelled | (none — final state) |

Attempts to make an invalid transition return an error and the campaign status is not changed.

## Pricing Models

### Flat Rate

A single fixed charge for the campaign duration. The package price is debited from the wallet at submission time. No per-impression or per-click billing applies.

Best for: Sponsored placements, newsletter ads, homepage takeovers.

### CPM (Cost Per Mille)

The advertiser is charged per 1,000 ad impressions. Set a rate (e.g., $2.50 per 1,000 views) and optionally an impressions limit.

**Budget calculation:** `rate × (impressions_limit ÷ 1000)`

Best for: Brand awareness campaigns.

### CPC (Cost Per Click)

The advertiser is charged per click on their ad. Set a rate (e.g., $0.50 per click) and optionally a clicks limit.

**Budget calculation:** `rate × clicks_limit`

Best for: Lead generation, direct response campaigns.

### CPM + CPC Combined (`cpm_cpc`)

The advertiser is charged for both impressions and clicks simultaneously. A CPM rate applies to every 1,000 impressions served, and a separate CPC rate applies to every click. Both charges draw from the same campaign budget.

Best for: Campaigns where both reach and engagement matter.

## Budget Reservation (CPM and CPC Campaigns)

CPM and CPC campaigns with a budget greater than zero use a **pre-funded reservation** system.

**Reservation lifecycle:**

1. **Activate** - The full budget is deducted from the wallet as a `campaign_reserve` transaction. If the wallet balance is insufficient, activation is blocked.
2. **Pause** - The reservation is held. No funds are released when pausing.
3. **Resume** - The campaign resumes without a new reservation charge. Funds were already held.
4. **Complete, expire, or cancel** - The unspent portion (budget minus actual spend) is returned to the wallet as a `campaign_refund` transaction.

This ensures advertisers always have enough funds before their ads go live, and they only pay for what is actually spent.

## Campaigns Created from Packages

When an advertiser submits an ad using a package, a campaign is created automatically via `Campaign_Manager::create_from_package()`.

**Flow:**

1. Submission is validated and the ad post is created in draft status
2. The campaign is created with budget and pricing model derived from the package
3. If the package **does not require approval**, the campaign activates immediately. For CPM/CPC packages, budget reservation happens at this step.
4. If the package **requires approval**, the campaign enters pending status. Budget reservation happens when you approve it.
5. If activation fails (e.g., insufficient balance), the ad and campaign are cleaned up and the advertiser sees an error.

## Auto-Pause and Auto-Completion

A cron job runs every 15 minutes (`wbam_do_check_campaign_budgets`) and checks all active campaigns. If a campaign has exhausted its budget or reached its impressions or clicks limit, it is automatically moved to **Completed** and the associated ad is unpublished.

A daily cron job (`wbam_daily_aggregation`) checks for campaigns whose end date has passed and moves them to **Expired**.

For unlimited-budget CPM/CPC campaigns, an hourly billing job (`wbam_calculate_hourly_billing`) charges the advertiser's wallet for accrued spend. If the wallet balance is insufficient at billing time, the campaign is paused automatically.

## Managing Campaigns as an Admin

Navigate to **WB Ads > Campaigns** to see all campaigns.

**Available columns:** Campaign name, advertiser, pricing model, budget (spent / total), impressions, clicks, status, date range.

**Actions per campaign:**

- **Approve** — Move a pending campaign to active (triggers budget reservation for CPM/CPC)
- **Reject** — Cancel a pending campaign
- **Pause / Resume** — Toggle active campaigns
- **View Stats** — See impressions, clicks, CTR, and daily spend chart

## Tracking Accuracy

Impression and click recording uses database-level row locking (`START TRANSACTION` + `SELECT ... FOR UPDATE`) to prevent duplicate counts under concurrent traffic. Per-day tracking cookies (`wbam_camp_imp`, `wbam_camp_clk`) prevent the same visitor from being counted more than once per calendar day per campaign. These are not session cookies — they persist until midnight of the current day. Bot traffic is filtered based on user-agent patterns before any count is recorded.
