---
journey: partnership-inquiry-submission
plugin: wb-ads-rotator-with-split-test
priority: high
roles: [anonymous]
covers: [wbam_submit_partnership, wp_wbam_link_partnerships, rate-limiting]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one published wbam-link entry exists, or partnership shortcode is on a public page"
estimated_runtime_minutes: 4
---

# Anonymous visitor submits a partnership inquiry and the row persists

Partnership inquiries are the FREE plugin's lead-capture mechanism. Customers integrate `[wbam_partnership_inquiry]` into their landing pages; if submissions silently 200-but-don't-persist, the customer loses leads they think they're capturing. This journey tests the AJAX/REST handler, rate-limit gate, and DB write.

## Setup

- Site: `$SITE_URL`
- Test user: anonymous
- Fixtures needed: a public page containing `[wbam_partnership_inquiry]` (or a known link with partnership inquiry attached)
- Pre-clean to prevent rate-limit collisions:
  ```sql
  DELETE FROM wp_wbam_rate_limits WHERE identifier LIKE '%partnership%' AND created_at > NOW() - INTERVAL 1 HOUR;
  ```

## Steps

### 1. Load the inquiry form page and capture nonce + form fields
- **Action**: `playwright_navigate $SITE_URL/<page-with-inquiry-form>`
- **Expect**: form is present, has hidden `_ajax_nonce` field or `wbamAjax.partnershipNonce` global
- **Capture**: `NONCE`, `FORM_ACTION_URL`

### 2. Capture inquiries row count BEFORE
- **Action**: `mysql_query "SELECT COUNT(*) AS c FROM wp_wbam_link_partnerships"`
- **Capture**: `BEFORE_COUNT`

### 3. Submit the inquiry via AJAX
- **Action**: POST to `$SITE_URL/wp-admin/admin-ajax.php` with body:
  ```
  action=wbam_submit_partnership
  _ajax_nonce=$NONCE
  name=Test User
  email=journey-test+$(date +%s)@example.com
  message=Automated journey test
  ```
- **Expect**: HTTP 200, JSON `{"success": true}`. Capture any `submission_id` returned.

### 4. Verify the inquiry row was written
- **Action**: `mysql_query "SELECT COUNT(*) AS c FROM wp_wbam_link_partnerships"`
- **Expect**: count = `BEFORE_COUNT + 1`

### 5. Verify rate-limit row was inserted (defense-in-depth)
- **Action**: `mysql_query "SELECT COUNT(*) AS c FROM wp_wbam_rate_limits WHERE identifier LIKE '%partnership%' AND created_at > NOW() - INTERVAL 1 MINUTE"`
- **Expect**: count >= 1

### 6. Replay the same submission immediately (rate-limit verification)
- **Action**: POST same body again
- **Expect**: either HTTP 429, or success=false with rate-limit message — NOT another row in `wp_wbam_link_partnerships`

## Pass criteria

ALL of the following hold:
1. Form loads with a usable nonce for anonymous visitors.
2. First submission returns `success: true` and inserts exactly one row.
3. A rate-limit row is recorded for the submitter's identifier (IP or email).
4. Second immediate submission is rejected without creating a duplicate inquiry row.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| `success: false, data: "Invalid nonce"` | Nonce mismatch | `includes/Modules/Links/class-partnership-manager.php` |
| Inquiry row not inserted but success returned | Insert failed silently | `Partnership_Manager::handle_submission` — check $wpdb->last_error |
| No rate-limit row | Rate limiter not wired into this AJAX action | `includes/Modules/Links/` rate-limit middleware |
| Second submit also succeeds | Rate-limit window mis-configured or check skipped | rate-limit table `created_at` index, ttl logic |
