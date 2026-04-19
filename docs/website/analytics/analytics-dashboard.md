# Analytics Dashboard

> **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.

WB Ad Manager Pro includes two analytics dashboards: the **Ad Analytics** dashboard for impression and click tracking, and the **Revenue Dashboard** for earnings reporting. Both are accessible from the WB Ads admin menu.

---

## Enabling Analytics

Analytics tracking is **off by default**. Enable it at **Pro Settings → General → Analytics → Enable analytics tracking**.

Once enabled, configure tracking behaviour at **Pro Settings → Analytics & Privacy**.

---

## Ad Analytics Dashboard

**Location:** WB Ads → Analytics

### Overview Metrics

The top row shows four summary cards for the selected date range:

| Metric | Description |
|--------|-------------|
| Total Impressions | How many times ads were displayed. Includes a percentage change vs. the previous equivalent period |
| Total Clicks | Total ad clicks. Includes percentage change vs. the previous period |
| Click-Through Rate (CTR) | `(clicks / impressions) × 100` |
| Unique Visitors | Distinct visitors (based on anonymised IP hash) who saw at least one ad |

### Date Range Filter

Filter all metrics and charts by selecting a start date, end date, and optionally a specific ad. Click **Filter** to apply, or **Reset** to return to the default range.

### Charts

| Chart | Description |
|-------|-------------|
| Impressions & Clicks Over Time | Line chart showing daily impressions and clicks across the selected period |
| By Device | Pie/doughnut chart of traffic split by device type (desktop, mobile, tablet) |
| Top Countries | Bar chart of top countries by impression volume |

Geographic and device data is only collected when **Anonymize IP Addresses** is disabled in Privacy settings.

### Ad Performance Table

Lists all ads with their impression, click, CTR, and placement data for the selected period. Click any ad name to open its edit screen.

### Exporting Analytics Data

Click **Export** to download analytics data as a CSV file for the selected date range and ad filter.

The AJAX action `wbam_export_analytics` handles the export. Exports are restricted to users with `manage_options` capability.

---

## Data Retention and Aggregation

Configured at **Pro Settings → Analytics & Privacy**.

| Setting | Default | Description |
|---------|---------|-------------|
| Data Retention | 365 days | Raw event records older than this are deleted by a scheduled cleanup. Valid range: 30–3,650 days |
| Aggregate After | 7 days | After this many days, raw events are summarised into daily stats and the raw rows are removed. Aggregated stats are kept permanently |

**Effect:** Recent data (within the aggregation window) provides full event-level detail. Older data is available as daily totals. This approach keeps the database lean on high-traffic sites while preserving long-term trend reporting.

---

## Bot Filtering

When **Bot Filtering** is enabled in Analytics settings, the tracker inspects the `User-Agent` header and rejects the request before recording any event if it matches a known crawler. Empty or missing `User-Agent` is also rejected.

The filter checks the lowercased user-agent string for these 40 substrings:

| Group | Patterns |
|-------|----------|
| Search engines | `googlebot`, `bingbot`, `slurp` (Yahoo), `duckduckbot`, `baiduspider`, `yandexbot`, `sogou`, `exabot`, `applebot` |
| Social | `facebot`, `facebookexternalhit`, `twitterbot`, `linkedinbot`, `pinterest` |
| SEO / monitoring | `semrushbot`, `ahrefsbot`, `mj12bot`, `dotbot`, `petalbot`, `bytespider`, `ia_archiver` (Alexa), `mediapartners`, `lighthouse` |
| Generic | `crawler`, `spider`, `bot/`, `bot;` |
| Headless / automation | `headless`, `phantomjs`, `selenium`, `puppeteer`, `scraper` |
| HTTP libraries | `wget`, `curl/`, `python-requests`, `python-urllib`, `java/`, `httpclient`, `go-http-client`, `apache-httpclient`, `libwww-perl` |

Matching is case-insensitive and uses substring match, so variant user-agent strings containing any of the above are filtered. The list lives in `includes/Modules/Analytics/class-analytics-tracker.php`.

---

## GDPR / Privacy Controls

| Setting | Effect |
|---------|--------|
| Require Cookie Consent | Events are only recorded after the visitor consents via a supported cookie consent plugin (CookieYes, Complianz, Moove GDPR, Cookie Notice) |
| Anonymize IP Addresses | IP addresses are hashed with a salt that rotates at UTC midnight (a new salt per UTC calendar day). User IDs and country data are not stored. Device type (non-PII) is still recorded |

---

## Revenue Dashboard

**Location:** WB Ads → Revenue

The Revenue Dashboard tracks earnings from both ad campaigns and classified listings. All revenue figures are estimates based on configurable CPM/CPC rates unless the wallet system provides actual transaction amounts.

### Overview Cards

| Card | Description |
|------|-------------|
| Total Revenue | Combined ad revenue + classified revenue for the selected period |
| Classified Revenue | Revenue from classified listing transactions, with period-over-period percentage change |
| CPM Revenue | Revenue attributed to CPM (impression-based) campaigns |
| CPC Revenue | Revenue attributed to CPC (click-based) campaigns |
| Page RPM | Revenue per 1,000 page views |

Total Revenue shows the breakdown: "Ads: $X | Classifieds: $Y".

### Date Range Filter

Quick-range buttons: **7 Days**, **30 Days**, **90 Days**, **This Month**, **Year to Date**. A custom date picker is also available.

### Revenue Chart

Line chart showing CPM revenue and CPC revenue over time for the selected period.

### Top Earning Ads Table

Lists the top 10 ads by revenue for the selected period, with columns: Ad name, Revenue, Impressions, Clicks.

### Revenue by Placement Table

Revenue attributed to each placement, with percentage of total revenue.

### Classified Revenue Breakdown

Appears only when classified revenue exists. Shows revenue split by type:

| Revenue Type | Description |
|--------------|-------------|
| Featured Listings | Income from advertisers purchasing featured status |
| Premium Listings | Income from premium-tier listing fees |
| New Listings | Income from paid listing submissions |
| Renewals | Income from listing renewal fees |
| Upgrades (Bump, Highlight) | Income from bump, highlight, urgent, and top-of-category upgrades |

### Revenue Calculation Settings

Located at the bottom of the Revenue Dashboard page:

| Setting | Default | Description |
|---------|---------|-------------|
| Default CPM Rate | $2.50 | Revenue per 1,000 impressions used for estimation |
| Default CPC Rate | $0.10 | Revenue per click used for estimation |

These rates are used when actual wallet transaction data is not available (e.g., flat-rate packages where the revenue calculation is estimated).

### Exporting Revenue Data

Click **Export CSV** to download revenue data for the selected date range. The export covers the same breakdown shown on screen: by ad, by placement, and classified revenue breakdown.

The AJAX action `wbam_export_revenue` handles the export and requires `manage_options` capability.
