---
title: Ad Display Shortcodes
persona: Operator — Free
tier: free
one_job: Document the free plugin's ad display shortcodes so site owners can render specific ads manually.
outcome: Reader can use [wbam_ad] and [wbam_ads] with every supported parameter to display one or more ads by ID.
assumes: Free plugin installed and activated, at least one ad created.
---

# Ad Display Shortcodes

## What You'll Learn

- How to display ads using shortcodes
- All available parameters
- Common use cases and examples

---

## Available Shortcodes

| Shortcode | Purpose |
|-----------|---------|
| `[wbam_ad]` | Display a single specific ad by ID |
| `[wbam_ads]` | Display multiple specific ads by their IDs |

> **Note:** Shortcodes display ads by ID. For automatic placement (header, footer, content, etc.), use the **Placements** checkboxes on each ad — no shortcode needed.

---

## [wbam_ad] - Single Ad Display

Display one ad by its post ID.

### Basic Usage

```
[wbam_ad id="123"]
```

### All Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | int | Yes | The post ID of the ad to display |
| `class` | string | No | Custom CSS class added to the ad wrapper |

### Examples

**Display a specific ad:**
```
[wbam_ad id="123"]
```

**With a custom CSS class:**
```
[wbam_ad id="123" class="my-custom-ad"]
```

---

## [wbam_ads] - Multiple Ads Display

Display a set of specific ads by their IDs. All listed ads render in sequence inside a shared wrapper.

### Basic Usage

```
[wbam_ads ids="1,2,3"]
```

### All Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ids` | string | Yes | Comma-separated list of ad post IDs |
| `class` | string | No | Custom CSS class added to each ad wrapper |

### Examples

**Show three specific ads:**
```
[wbam_ads ids="10,20,30"]
```

**With a custom CSS class on each ad:**
```
[wbam_ads ids="10,20,30" class="partner-logo"]
```

---

## Finding Ad IDs

To find your ad's post ID:

1. Go to **WB Ad Manager → Ads**
2. Hover over an ad title — the ID appears in the URL at the bottom of your browser (e.g., `post=123`)
3. Or click to edit an ad and check the URL: `post.php?post=123&action=edit`

---

## Using in Different Locations

### In Page/Post Content

Add the shortcode directly in the editor:
```
[wbam_ad id="123"]
```

### In Widgets

1. Add a **Custom HTML** or **Text** widget
2. Paste the shortcode
3. Save

### In Theme Templates (PHP)

```php
<?php echo do_shortcode( '[wbam_ad id="123"]' ); ?>
```

---

## Styling Your Ads

### Default CSS Classes

```css
.wbam-ad                   /* Single ad container */
.wbam-ad-image             /* Image ad type */
.wbam-ad-rich_content      /* Rich Content ad type */
.wbam-ad-code              /* Code/HTML/JS ad type */
.wbam-ad-adsense           /* AdSense ad type */
.wbam-ad-email-capture     /* Email Capture ad type */
.wbam-placement-shortcode  /* Wrapper when using [wbam_ads] */
```

### Custom Styling Examples

```css
/* Add border to all ads */
.wbam-ad {
    border: 1px solid #ddd;
    padding: 10px;
}

/* Rounded image corners */
.wbam-ad img {
    border-radius: 8px;
}

/* Hover shadow effect */
.wbam-ad:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
```

---

## How Ad Rotation Works

Rotation is not controlled by shortcodes. It happens automatically at the placement level.

When multiple published ads are assigned to the same placement (e.g., "After Content"), the plugin selects which ad to show based on **Priority** (set per ad on a 1–10 slider). Higher priority ads are shown more often.

To display a specific ad regardless of rotation, use `[wbam_ad id="123"]` with the exact ID.

---

## Troubleshooting

### Ad not showing

1. Verify the ad is published (not draft or disabled)
2. Check the ad ID is correct
3. Verify start/end dates if set
4. Clear any caching plugins

### Shortcode shows as plain text

1. Verify the plugin is activated
2. Check shortcode spelling — it is case-sensitive
3. Ensure no extra spaces inside the shortcode brackets
4. Try on a different page/post
5. Switch to a default theme to test for conflicts

### Clicks not tracking

1. Verify tracking is enabled in settings
2. Check destination URL starts with `http://` or `https://`
3. Test in an incognito window (ad blockers may interfere)

---

## Performance Tips

1. **Optimize images** — Compress ad images before uploading
2. **Limit ads per page** — 3–5 ad placements is optimal
3. **Enable lazy loading** — Turn on in **WB Ad Manager → Settings → Performance** for ads below the fold

---

*See also: [Link Shortcodes](link-shortcodes.md)*
