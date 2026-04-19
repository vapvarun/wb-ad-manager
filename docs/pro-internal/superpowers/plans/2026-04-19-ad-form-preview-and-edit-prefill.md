# Plan: Ad form — HTML preview renders + edit flow prefills package/placement/campaign

**Status:** Approved (execute immediately)
**Date:** 2026-04-19
**Target release:** `1.5.0`
**Risk class:** UI-only, revert-safe per commit.

---

## Problem 1 — HTML preview shows raw source

`assets/js/portal.js:930-935` escapes the HTML code and dumps it inside a `<pre>`:

```js
var code = this.$form.find('textarea[name="html_code"]').val() || '';
previewHtml = '<div class="wbam-code-preview"><pre>' + $('<span>').text(code).html() + '</pre></div>';
```

Customer-reported: they submitted an HTML ad and Step 6 "Ad Preview" showed `<!DOCTYPE html>…<head>…` as literal text instead of rendering the ad.

**Fix:** use a sandboxed `<iframe srcdoc="…">` so the browser renders the HTML in isolation. `srcdoc` keeps the ad's styles scoped, the `sandbox` attribute blocks scripts from reaching the parent page, and the iframe's `srcdoc` accepts the raw markup without needing a data URL.

```js
var code = this.$form.find('textarea[name="html_code"]').val() || '';
if ( code ) {
    var srcdoc = $('<span>').text(code).html();  // HTML-escape for attribute
    previewHtml = '<iframe class="wbam-html-preview" sandbox="allow-same-origin"'
                + ' style="width:100%;min-height:280px;border:0;" srcdoc="' + srcdoc + '"></iframe>';
} else {
    previewHtml = '<p class="wbam-empty-state">No HTML entered yet</p>';
}
$preview.html(previewHtml);
```

Keep a "view source" toggle as future work if customers want it — not required now.

---

## Problem 2 — Edit flow forgets Package, Placement, Campaign

Template pulls these from post meta correctly (`$ad_data['package_id']`, `$ad_data['placements']`, `$ad_data['campaign_id']` at `ad-form.php:76-80`) but the Step 3/4/5 rendering never reads those values:

- Line 313 (package radio): no `checked=`
- Line 418 (placement checkbox): no `checked=`
- Line 448, 455, 460 (campaign name / start / end): no `value=`

**Fix per step:**

### Step 3 — Package radio
```php
<input type="radio" name="package_id" value="<?php echo esc_attr( $package->id ); ?>"
    <?php checked( (int) ( $ad_data['package_id'] ?? 0 ), (int) $package->id ); ?>
    data-placements="..."
    data-allowed-formats="..."
    data-max-ads="..."
    data-price="...">
```

Also the custom-package radio (line 387): `<?php checked( 'custom', $ad_data['package_id'] ?? '' ); ?>`.

### Step 4 — Placement checkboxes
```php
<input type="checkbox" name="placements[]" value="<?php echo esc_attr( $placement_id ); ?>"
    <?php checked( in_array( $placement_id, (array) ( $ad_data['placements'] ?? array() ), true ) ); ?>>
```

Also toggle `.wbam-placement-card.selected` when the corresponding placement is checked — matches the JS state that the click handler sets.

### Step 5 — Campaign fields
- `campaign_name` — read from existing campaign if `$ad_data['campaign_id']` is set, or fall back to ad title + " Campaign".
- `start_date` / `end_date` — same lookup: if campaign exists, prefill its dates.

```php
$existing_campaign = null;
if ( $is_edit && ! empty( $ad_data['campaign_id'] ) ) {
    $existing_campaign = \WBAM_Pro\Modules\Campaigns\Campaign_Manager::get_instance()->get( absint( $ad_data['campaign_id'] ) );
}
```

Then in the fields:
```php
value="<?php echo esc_attr( $existing_campaign ? $existing_campaign->name : '' ); ?>"
value="<?php echo esc_attr( $existing_campaign ? substr( $existing_campaign->start_date, 0, 10 ) : '' ); ?>"
value="<?php echo esc_attr( $existing_campaign && $existing_campaign->end_date ? substr( $existing_campaign->end_date, 0, 10 ) : '' ); ?>"
```

---

## Verification

1. Open any existing ad in edit mode → Step 3: the ad's original package is pre-selected (radio is checked).
2. Navigate to Step 4 → the ad's placements are checked + the cards visually show selected state.
3. Navigate to Step 5 → campaign name prefills + start/end dates populate if set.
4. Navigate to Step 6 with an HTML ad → preview renders as an iframe (the actual ad visible), not as source code.
5. No console errors.

---

## Files

1. `assets/js/portal.js` — preview switch-case update (1 block).
2. `assets/js/portal.min.js` — same change re-minified (inline edit since we don't have a live build pipeline).
3. `templates/portal/ad-form.php` — three prefill edits (steps 3, 4, 5).

---

## Commits

Two focused commits:
1. `fix(preview): render HTML ads in a sandboxed iframe instead of showing source`
2. `fix(edit): prefill package / placement / campaign fields when editing an existing ad`

Both revert-safe.
