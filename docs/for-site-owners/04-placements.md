# Placements

Placements control where ads appear on your site. Assign multiple placements per ad.

---

## Available Placements

### Page Positions

| Placement | Location |
|-----------|----------|
| **Header** | Top of every page, before content |
| **Footer** | Bottom of every page, after content |
| **Before Content** | Before post/page content starts |
| **After Content** | After post/page content ends |

### In-Content

| Placement | Location |
|-----------|----------|
| **After Paragraph X** | After a specific paragraph (e.g., after paragraph 3) |
| **Between Posts** | In archive/blog post lists |

### Widgets & Manual

| Placement | Location |
|-----------|----------|
| **Sidebar** | Via widget in sidebar areas |
| **Shortcode** | Manual placement with `[wbam_ad]` |

### Overlays

| Placement | Location |
|-----------|----------|
| **Popup** | Modal overlay (with trigger conditions) |
| **Sticky** | Fixed position bar (top or bottom) |

### Comments

| Placement | Location |
|-----------|----------|
| **Before Comments** | Above comment section |
| **After Comments** | Below comment section |

### Community (BuddyPress/bbPress)

| Placement | Location |
|-----------|----------|
| **BuddyPress Activity** | In activity stream |
| **BuddyPress Profile** | On member profiles |
| **bbPress Forum** | In forum listings |
| **bbPress Topic** | In topic threads |

---

## Setting Up Placements

### Method 1: In Ad Editor

1. Edit your ad
2. Find **Placements** metabox
3. Check desired placements
4. Save

### Method 2: Widget

1. Go to **Appearance > Widgets**
2. Add **WBAM Ad Widget** to sidebar
3. Select specific ad or "Random from placement"
4. Save

### Method 3: Shortcode

```
[wbam_ad id="123"]
```

Place in any post, page, or widget area.

---

## Placement Settings

### After Paragraph X

Configure which paragraph:

1. Edit ad
2. In Placements, check "After Paragraph"
3. Set paragraph number (e.g., 3)
4. Save

Ad appears after the 3rd paragraph in post content.

### Popup Settings

| Setting | Description |
|---------|-------------|
| **Trigger** | On load, scroll %, exit intent, time delay |
| **Delay** | Seconds before showing |
| **Frequency** | Once per session, once per day, always |

### Sticky Bar Settings

| Setting | Description |
|---------|-------------|
| **Position** | Top or bottom of screen |
| **Close Button** | Allow users to dismiss |
| **Mobile** | Show/hide on mobile |

---

## Multiple Ads Per Placement

When multiple ads share a placement:

| Rotation Mode | Behavior |
|---------------|----------|
| **Random** | One random ad shown |
| **Weighted** | Higher weight = more likely |
| **Sequential** | Rotate through in order |

Set weight in each ad's settings (1-100, higher = priority).

---

## Best Practices

### High-Performing Placements

| Placement | Typical CTR | Notes |
|-----------|-------------|-------|
| After paragraph 1-3 | High | Users engaged with content |
| Before content | Medium | Visible but before engagement |
| Sidebar | Low-Medium | Depends on sidebar visibility |
| Popup | High | Interruptive but effective |
| Footer | Low | Most users don't scroll down |

### Recommendations

- **Don't overload** - Too many ads hurts user experience
- **Test placements** - Use A/B testing to find winners
- **Consider mobile** - Some placements work better on desktop
- **Match ad size to placement** - 300x250 for sidebar, 728x90 for header

---

## Shortcode Parameters

```
[wbam_ad id="123"]                    # Specific ad
[wbam_ads placement="sidebar"]        # Random from placement
[wbam_ads placement="sidebar" limit="2"]  # Multiple ads
```

| Parameter | Description | Default |
|-----------|-------------|---------|
| `id` | Specific ad ID | - |
| `placement` | Placement slug | - |
| `limit` | Max ads to show | 1 |

---

## Related Guides

- [Ad Types](03-ad-types.md) - What kind of ads to create
- [Targeting](05-targeting.md) - Control when ads show
- [Shortcodes](../shortcode-reference/01-ad-shortcodes.md) - Display options
