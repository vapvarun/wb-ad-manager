# Shortcode Reference

Shortcodes let you display ads and links anywhere on your site using simple text commands. Just paste them into any page, post, or widget.

**No coding required** - copy, paste, and you're done.

---

## Ad Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[wbam_ad id="123"]` | Display a specific ad by ID |
| `[wbam_ads ids="1,2,3"]` | Display multiple specific ads |

[Full ad shortcode reference →](01-ad-shortcodes.md)

---

## Link Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[wbam_link id="123"]Text[/wbam_link]` | Display a tracked link |
| `[wbam_links]` | Display a list of links |
| `[wbam_link_url id="123"]` | Output just the URL |
| `[wbam_partnership_inquiry]` | Partnership request form |

[Full link shortcode reference →](02-link-shortcodes.md)

---

## Common Examples

### Display a Specific Ad

```
[wbam_ad id="42"]
```

Find the ad ID in **WB Ads > All Ads** (ID column).

### Display Multiple Ads

```
[wbam_ads ids="42,55,67"]
```

### Display a Tracked Link

```
[wbam_link id="15"]Check out this deal[/wbam_link]
```

### Display Partnership Form

Add this to a "Work With Us" or "Advertise" page:

```
[wbam_partnership_inquiry]
```

---

## Parameters

### [wbam_ad]

| Parameter | Description | Default |
|-----------|-------------|---------|
| `id` | Ad post ID (required) | - |
| `class` | Custom CSS class | - |

### [wbam_ads]

| Parameter | Description | Default |
|-----------|-------------|---------|
| `ids` | Comma-separated ad IDs (required) | - |
| `class` | Custom CSS class | - |

### [wbam_link]

| Parameter | Description | Default |
|-----------|-------------|---------|
| `id` | Link post ID | - |
| `slug` | Link slug (alternative to id) | - |
| `text` | Custom anchor text | link title |
| `class` | Custom CSS class | - |
| `nofollow` | Add rel="nofollow" | - |
| `sponsored` | Add rel="sponsored" | - |
| `new_tab` | Open in new tab | - |

---

## Pro Shortcodes

[WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) adds shortcodes for:

- Advertiser dashboard and portal
- Classifieds marketplace
- Ad submission forms
- Wallet and stats widgets

[View Pro shortcodes →](https://wbcomdesigns.com/docs/)
