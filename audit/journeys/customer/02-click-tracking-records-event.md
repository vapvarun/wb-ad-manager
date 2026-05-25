---
journey: click-tracking-records-event
plugin: wb-ads-rotator-with-split-test
priority: critical
roles: [anonymous]
covers: [wbam_track_click, wp_wbam_analytics, ajax-nonce]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Journey 01-ad-renders-on-frontend passes (we need a rendered ad to click)"
estimated_runtime_minutes: 3
---

# Anonymous visitor clicking an ad records an event in analytics

Click attribution is the second-most-customer-visible promise after rendering. If the `wbam_track_click` AJAX action returns 0 / -1 or if the analytics row is never written, all the dashboard charts the customer paid for show zero. This journey verifies the full click path: DOM event -> AJAX -> nonce -> permission -> DB write.

## Setup

- Site: `$SITE_URL`
- Test user: anonymous
- Fixtures needed: a published `wbam-ad` ID (`AD_ID`) — same as journey 01

## Steps

### 1. Render a page with the ad and capture its tracking nonce
- **Action**: `playwright_navigate $SITE_URL/?p=<page-with-shortcode>`
- **Expect**: page renders, JS exposes a nonce (look for `wbamAjax.nonce` or similar in window globals or a localized script handle)
- **Capture**: `NONCE` <- value extracted via `playwright_run_code "return window.wbamAjax?.nonce || null"`
- **On fail**: enqueue logic broken — check `includes/Frontend/` script localization

### 2. Capture click row count BEFORE
- **Action**: `mysql_query "SELECT COUNT(*) AS c FROM wp_wbam_analytics WHERE ad_id = $AD_ID AND event_type IN ('click','clicks')"`
- **Capture**: `BEFORE_COUNT` <- the count

### 3. Trigger the AJAX click handler
- **Action**: POST to `$SITE_URL/wp-admin/admin-ajax.php` with body `action=wbam_track_click&ad_id=$AD_ID&_ajax_nonce=$NONCE`
- **Expect**: HTTP 200, JSON body `{"success": true, ...}`
- **On fail**: check `includes/Frontend/` AJAX registration; nonce mismatch typically means action name in JS != PHP

### 4. Capture click row count AFTER
- **Action**: `mysql_query "SELECT COUNT(*) AS c FROM wp_wbam_analytics WHERE ad_id = $AD_ID AND event_type IN ('click','clicks')"`
- **Expect**: count = `BEFORE_COUNT + 1`

## Pass criteria

ALL of the following hold:
1. The page exposes a usable AJAX nonce to anonymous visitors (no login required).
2. POST to `wbam_track_click` returns HTTP 200 with `success: true`.
3. The `wp_wbam_analytics` row count for that ad's click events increases by exactly 1.
4. No PHP notices or warnings appear in the AJAX response body.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| `responseText: "0"` | Action not registered for nopriv users | `includes/Frontend/` add_action wp_ajax_nopriv_wbam_track_click |
| `success: false, data: "Invalid nonce"` | Nonce action name mismatch between JS and PHP | check `wp_create_nonce` action name vs `check_ajax_referer` |
| HTTP 403 | Capability check too strict for anon | Frontend handler should not require capability for click tracking |
| Row count unchanged | Insert silently swallowed | check analytics writer for `try/catch` swallowing errors |
