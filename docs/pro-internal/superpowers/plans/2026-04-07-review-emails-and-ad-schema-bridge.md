# Review Email Templates + Pro-to-Free Ad Schema Bridge

**Two bugs, one plan. Both block core advertiser workflows.**

---

## Bug A: Review emails render as plain text (3 Basecamp cards)

**Cards:** 9758893293, 9758886427, 9758863099

### Root Cause
`send_review_submitted_notification()` and `send_review_status_notification()` in `class-email-notifications.php` use `sprintf()` to build the email body instead of `$this->get_template()`. No `templates/emails/review-*.php` files exist.

### Fix

**Step 1:** Read the current review email methods to understand what variables they use.

**Step 2:** Create 3 template files following the existing pattern (header.php + content + footer.php):
- `templates/emails/review-submitted.php` — "New review on your listing"
- `templates/emails/review-approved.php` — "Your review has been approved"
- `templates/emails/review-rejected.php` — "Your review was not approved"

**Step 3:** Refactor both methods to use `$this->get_template()`:
- `send_review_submitted_notification()` → `get_template('review-submitted', $vars)`
- `send_review_status_notification()` → `get_template('review-approved', $vars)` or `get_template('review-rejected', $vars)` based on `$status`

**Step 4:** Remove the inline `sprintf()` message building.

**Step 5:** Run php -l, verify, commit.

### Files to modify
- `includes/Modules/Notifications/class-email-notifications.php` — lines ~864-940
- Create: `templates/emails/review-submitted.php`
- Create: `templates/emails/review-approved.php`
- Create: `templates/emails/review-rejected.php`

---

## Bug B: Pro-submitted ads don't display — schema mismatch

**Cards:** 9758159600 (analytics), 9753958139 (placements)

### Root Cause
The free plugin's `Placement_Engine::get_ads_for_placement()` queries ads with:
```sql
meta_key = '_wbam_enabled' AND meta_value = '1'
meta_key = '_wbam_placements' LIKE '%placement_id%'
```
Then `render_ad()` reads `_wbam_ad_data` for the ad type and content.

But the Pro `Ad_Submission_Manager` creates ads with DIFFERENT meta:
- `_wbam_ad_type` (instead of inside `_wbam_ad_data`)
- `_wbam_image_id` (instead of inside `_wbam_ad_data`)
- `_wbam_target_url` (instead of inside `_wbam_ad_data`)
- NO `_wbam_enabled` meta
- NO `_wbam_placements` meta
- NO `_wbam_ad_data` meta

Result: Pro-submitted ads are invisible to the display engine. No rendering → no tracking → analytics always empty.

### Fix

**Step 1:** Read `Ad_Submission_Manager::submit_ad()` and `activate_ad()` to understand the full ad creation flow.

**Step 2:** After the ad post is created and meta is set, add a bridge that writes the free-plugin-compatible meta:

```php
// Bridge: Write meta keys that the free plugin's Placement_Engine expects
update_post_meta( $ad_id, '_wbam_enabled', '1' );

// Build placements array from the package's allowed_placements
$package = Package_Manager::get_instance()->get( $package_id );
$placements = $package ? $package->allowed_placements : array( 'header', 'footer', 'before_content', 'after_content' );
update_post_meta( $ad_id, '_wbam_placements', $placements );

// Build _wbam_ad_data from Pro's separate meta keys
$ad_data = array(
    'type'      => get_post_meta( $ad_id, '_wbam_ad_type', true ),
    'image_url' => wp_get_attachment_url( get_post_meta( $ad_id, '_wbam_image_id', true ) ),
    'link_url'  => get_post_meta( $ad_id, '_wbam_target_url', true ),
);
update_post_meta( $ad_id, '_wbam_ad_data', $ad_data );
```

**Step 3:** Also add the bridge to `approve()` / `activate_ad()` since ads may be activated after initial creation.

**Step 4:** For existing Pro-submitted ads, consider a migration that backfills the missing meta.

**Step 5:** Verify by checking if ads appear on frontend after the bridge is in place.

### Files to modify
- `includes/Modules/AdSubmissions/class-ad-submission-manager.php` — `submit_ad()` and `activate_ad()`
- Possibly: `includes/Core/class-pro-admin.php` — admin ad creation if it has the same gap

### Files to read first
- `wb-ads-rotator-with-split-test/includes/Modules/Placements/class-placement-engine.php` — `get_ads_for_placement()` query and `render_ad()` data reading
- `class-ad-submission-manager.php` — full `submit_ad()` flow
- `wb-ads-rotator-with-split-test/includes/Core/class-ad-types.php` or similar — how `_wbam_ad_data` is structured

---

## Execution Order

1. **Bug A first** (review emails) — quick, same pattern we've done, unblocks QA
2. **Bug B second** (schema bridge) — deeper, needs investigation of the free plugin's data model

## Verification

### Bug A
- Trigger a review submission → check email renders with HTML template
- Approve a review → check approved email
- Reject a review → check rejected email

### Bug B
- Submit an ad via the advertiser portal
- Check that `_wbam_enabled`, `_wbam_placements`, `_wbam_ad_data` meta exists on the new ad
- Visit frontend page → verify ad renders
- Check `wp_wbam_analytics` table for new impression rows
