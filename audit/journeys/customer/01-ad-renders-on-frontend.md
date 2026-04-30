---
journey: ad-renders-on-frontend
plugin: wb-ads-rotator-with-split-test
priority: critical
roles: [anonymous, subscriber]
covers: [shortcode-rendering, placement-engine, frontend-loader]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one published wbam-ad CPT entry exists with a valid ad payload"
  - "Plugin is active (no fatal errors in error_log)"
estimated_runtime_minutes: 3
---

# Anonymous visitor sees a rendered ad on a public page

Ad rendering is the plugin's core promise. If `[wbam_ad]` shortcode or any placement-engine hook fails to output an ad on a public page for a logged-out visitor, every downstream feature (impression tracking, A/B variants, click tracking, geo-targeting) is unreachable. This journey is the canary for the entire frontend pipeline.

## Setup

- Site: `$SITE_URL`
- Test user: anonymous (no autologin)
- Fixtures needed: at least one `wbam-ad` post with `post_status='publish'` and an ad type configured (image, code, or plain-text)
- DB sanity check:
  ```sql
  SELECT ID, post_title, post_status FROM wp_posts
  WHERE post_type = 'wbam-ad' AND post_status = 'publish' LIMIT 5;
  ```

## Steps

### 1. Resolve a target ad ID
- **Action**: `mysql_query "SELECT ID FROM wp_posts WHERE post_type='wbam-ad' AND post_status='publish' ORDER BY ID DESC LIMIT 1"`
- **Expect**: returns at least one row
- **Capture**: `AD_ID` <- the returned ID
- **On fail**: no published ads — seed one via wp-admin or `wp post create --post_type=wbam-ad --post_status=publish`

### 2. Render the ad shortcode on a test page
- **Action**: `playwright_navigate $SITE_URL/?p=<page-with-shortcode>` OR create a transient page via REST that contains `[wbam_ad id="$AD_ID"]`
- **Expect**: 200 OK, response body contains an ad wrapper element (look for `class="wbam-ad"` or similar)
- **On fail**: check `includes/Frontend/class-frontend.php` shortcode handler and `templates/ad-display*.php`

### 3. Verify no PHP notices in the response
- **Action**: inspect the rendered HTML for `Notice:`, `Warning:`, `Fatal error:` strings
- **Expect**: none of those strings present
- **On fail**: WP_DEBUG should be off in production-style runs; if present, check Frontend bootstrap for missing classes

### 4. Verify impression tracking fires
- **Action**: `mysql_query "SELECT COUNT(*) FROM wp_wbam_analytics WHERE ad_id = $AD_ID AND DATE(created_at) = CURDATE()"`
- **Expect**: count >= 1 (impression recorded for today)
- **On fail**: check `Placement_Engine` and analytics writer in `includes/Frontend/`

## Pass criteria

ALL of the following hold:
1. The page renders 200 OK for an anonymous visitor.
2. The rendered HTML contains an ad wrapper element with non-empty content.
3. No PHP notice/warning/fatal strings appear in the response HTML.
4. An impression row is recorded in `wp_wbam_analytics` within 5 seconds of page load.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Empty ad wrapper or no markup | Placement_Engine resolved zero ads | `includes/Modules/Placements/class-placement-engine.php` |
| PHP fatal in response | Class not autoloaded or missing dependency | `wb-ads-rotator-with-split-test.php`, `composer.json` autoload |
| No impression recorded | Analytics writer disabled or table missing | `includes/Frontend/class-frontend.php`, installer migrations |
| Wrong ad rendered (variant mismatch) | Split-test selection drifted | `includes/Modules/Targeting/class-targeting-engine.php` |
