# Shortcode Reference

All shortcodes available in WB Ad Manager (Free).

---

## Ad Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[wbam_ad id="123"]` | Display a specific ad by ID |
| `[wbam_ads placement="sidebar"]` | Display ads from a placement |

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

### Display Ads from a Placement

```
[wbam_ads placement="sidebar" limit="2"]
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

### [wbam_ads]

| Parameter | Description | Default |
|-----------|-------------|---------|
| `placement` | Placement slug | - |
| `limit` | Max ads to show | 1 |

### [wbam_link]

| Parameter | Description | Default |
|-----------|-------------|---------|
| `id` | Link post ID (required) | - |

---

## Pro Shortcodes

[WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) adds shortcodes for:

- Advertiser dashboard and portal
- Classifieds marketplace
- Ad submission forms
- Wallet and stats widgets

[View Pro shortcodes →](https://wbcomdesigns.com/docs/)
