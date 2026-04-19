# Round 3 Bug Fixes — Plan

**4 new bugs from QA testing. All pre-existing, not caused by our changes.**

---

## Bug 1: Advertiser can't view Ads listing after create/edit
**Card:** 9758426595
**Severity:** Medium

### Root Cause (browser-verified)
The sidebar tab links use `add_query_arg()` which inherits the current URL's query params. When the advertiser is on `?tab=ads&action=new`, clicking "My Ads" in sidebar links to `?tab=ads&action=new` instead of just `?tab=ads`.

### Fix
In `class-advertiser-shortcodes.php`, find where sidebar tab links are generated. The links should use a clean base URL with ONLY the `tab` param, stripping `action`, `ad_id`, `campaign_id`, etc.

Find the sidebar rendering code. It likely uses:
```php
add_query_arg('tab', $tab_slug)
```
Which inherits `action=new` from the current URL. Change to:
```php
add_query_arg('tab', $tab_slug, wbam_get_portal_url())
```
Using the portal's base URL (no query params) as the third argument.

### Files to modify
- `includes/Modules/Advertisers/class-advertiser-shortcodes.php` — sidebar link generation

---

## Bug 2: Selected package not updating during ad creation
**Card:** 9758383756
**Severity:** High (blocks advertiser from selecting packages)

### Root Cause (browser-verified)
- HTML form uses `<input type="radio" name="package_id" value="5">`
- JS `portal.js:721` listens for `input[name="listing_package"]` (wrong name)
- JS `portal.js:953` reads `input[name="listing_package"]:checked` for cost summary
- The `change` event never fires because the selector doesn't match

### Fix
In `assets/js/portal.js`, change ALL references from `listing_package` to `package_id`:
- Line 721: `$(document).on('change', 'input[name="listing_package"]'` → `input[name="package_id"]`
- Line 953: `$('input[name="listing_package"]:checked')` → `$('input[name="package_id"]:checked')`
- Search for any other references to `listing_package` in the file

This is a JS-only fix. The PHP form is correct (`package_id`). The JS was written for an older form field name and never updated.

### Files to modify
- `assets/js/portal.js` — field name references
- Note: `portal.min.js` will need rebuild

---

## Bug 3: Ads tab pagination not working
**Card:** 9758183332
**Severity:** Medium (missing feature, not broken feature)

### Root Cause (from QA analysis)
The ads tab template uses `get_posts(array('posts_per_page' => -1))` — loads ALL ads with no pagination. There's no `paged` parameter handling or `paginate_links()` output.

A separate paginated implementation exists in `render_tab_ads()` (the inline shortcode version), but the template-loaded version (`templates/portal/tabs/ads.php`) doesn't have pagination.

### Fix
Same pattern as the campaign pagination fix we already did:
1. Use `get_query_var('paged')` with `$_GET['paged']` fallback
2. Change `posts_per_page` from `-1` to `10` (or configurable)
3. Add `paginate_links()` after the ads grid
4. Build stable base URL with `tab=ads` preserved

### Files to modify
- `includes/Modules/Advertisers/class-advertiser-shortcodes.php` — `render_tab_ads()` method (the inline version that actually renders)

---

## Bug 4: Analytics stats not tracking clicks and views
**Card:** 9758159600
**Severity:** High (core feature broken)

### Root Cause (code-traced)
The wiring EXISTS and is correct:
- Free plugin calls `apply_filters('wbam_ad_output', $output, $ad_id, $placement)` in `Placement_Engine::render()`
- Pro plugin hooks `track_impression()` at priority 10
- `track_impression()` calls `track_event()` which checks settings and writes to DB

BUT `track_event()` at line 123 skips tracking for logged-in users when `track_logged_in` is false. The QA tester was logged in. The setting `track_logged_in` is `true` in general settings, but `get_analytics('track_logged_in')` may read from a SEPARATE analytics-specific settings key that could be empty/false.

### Investigation needed
1. Check `Settings_Helper::get_analytics('track_logged_in', null)` — what does it return?
2. Check if there's a separate analytics settings option that overrides the general setting
3. Check if `is_admin()` short-circuits the front-end tracking (the tester was logged in as admin)
4. Check the DB table `wbam_analytics` — is it empty, or does it have data?
5. Check if the `wbam_ad_output` filter actually fires by adding a temporary debug log

### Possible causes (ranked by likelihood)
1. `get_analytics('track_logged_in')` returns `false` (separate settings key)
2. Bot filtering catches the request
3. The analytics DB table doesn't exist (migration not run)
4. The free plugin's `Placement_Engine::render()` code path is different from what QA tested

### Files to investigate
- `includes/Modules/Analytics/class-analytics-tracker.php` — `track_event()` method
- `includes/Core/class-settings-helper.php` — `get_analytics()` method
- Free plugin: `includes/Modules/Placements/class-placement-engine.php` — `render()` method

---

## Execution Order

1. **Bug 2** (package selector) — quick JS fix, high impact
2. **Bug 1** (sidebar links) — quick PHP fix, medium impact  
3. **Bug 4** (analytics tracking) — needs investigation first, then fix
4. **Bug 3** (ads pagination) — medium effort, follows campaign pagination pattern

## Process
For each bug:
1. Read the code, understand the full flow
2. Fix the root cause
3. Browser-verify the fix
4. Commit with clear message
5. Comment on Basecamp card
6. Move to Ready for Testing
