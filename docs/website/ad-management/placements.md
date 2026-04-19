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

### Archive / Loop

| Placement | Location |
|-----------|----------|
| **Before Archive** | Just before the first post on archive/blog pages (fires on `loop_start`) |
| **After Archive** | Just after the last post on archive/blog pages (fires on `loop_end`) |

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

### Community — BuddyPress

Registered automatically when BuddyPress is active.

| Placement | Location |
|-----------|----------|
| **BuddyPress Activity** | Injected into the activity stream between posts |
| **Directory — Before Members** | Top of the members directory |
| **Directory — Between Members** | Between member cards in the members directory |
| **Directory — After Members** | Bottom of the members directory |
| **Directory — Before Groups** | Top of the groups directory |
| **Directory — Between Groups** | Between group cards in the groups directory |
| **Directory — After Groups** | Bottom of the groups directory |

### Community — bbPress

Registered automatically when bbPress is active. All positions belong
to the single `bbpress` placement — pick positions per ad.

| Position | Location |
|----------|----------|
| **Before Forums** | Top of the forums list |
| **After Forums** | Bottom of the forums list |
| **Before Topics** | Top of the topics list in a forum |
| **After Topics** | Bottom of the topics list in a forum |
| **Before Single Topic** | Just above the topic title on a topic page |
| **After Single Topic** | Just below the opening post, before replies |
| **Between Replies** | Every N replies inside a topic (configurable per ad — default 5) |

### Community — Jetonomy

Registered automatically when the Jetonomy community plugin is active
(detected via `class_exists('\\Jetonomy\\Jetonomy')`). All seven
placements are free-plugin features.

| Placement | Location |
|-----------|----------|
| **Sidebar Top** | Top of the Jetonomy sidebar, above everything |
| **Sidebar After About** | Between the About card and the next widget (space pages only) |
| **Sidebar Bottom** | Bottom of the Jetonomy sidebar |
| **After Topic Body** | Directly under the topic body, before replies |
| **Before Replies** | Above the first reply |
| **Between Replies** | Every N replies (configurable per ad — default 5) |
| **After Replies** | Below the last reply |

**Between-reply frequency (bbPress and Jetonomy):** each ad assigned
to a "between replies" position has two extra options:

- **Show after** — reply number to anchor on (default: 5)
- **Repeat every** — if checked, the ad repeats every N replies instead
  of showing once at the anchor position

### bbPress Widgets

Two dedicated widgets for bbPress sidebar ads:

| Widget | Description |
|--------|-------------|
| **WBAM: bbPress Forum Ad** | Display ads on bbPress forum pages |
| **WBAM: bbPress Topic Sidebar Ad** | Display ads on single topic pages only |

#### bbPress Forum Ad Widget

Settings:
- **Title** - Optional widget title
- **Select Ad** - Choose which ad to display
- **Show On** - All bbPress pages, Forum pages only, or Topic pages only

#### bbPress Topic Sidebar Ad Widget

Settings:
- **Title** - Optional widget title
- **Select Ad** - Choose which ad to display

This widget only displays on single topic pages, making it ideal for topic-specific advertising.

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

The popup/modal placement renders inside `wp_footer` and is shown by
JavaScript based on the trigger you choose.

| Setting | Values | Default | Description |
|---------|--------|---------|-------------|
| **Trigger** | `delay`, `scroll`, `exit` | `delay` | What causes the popup to appear |
| **Delay (seconds)** | Integer ≥ 0 | `5` | Only used when trigger is `delay` — wait this long after page load |
| **Scroll %** | 0 – 100 | `50` | Only used when trigger is `scroll` — fire after the user scrolls this much of the page |

**Exit intent** shows the popup when the visitor's mouse moves toward the
browser-tab area — a classic "about to leave" signal. Not available on
touch devices (no mouse to track), so popup falls back to delay behavior
on mobile.

**Close behavior:** a close button (`.wbam-popup-close`) dismisses the
popup for the current page view. Reloading the page shows the popup
again unless you're also using a per-ad session limit (see
[Targeting → Frequency Capping](05-targeting.md#frequency-capping)).

### Sticky Bar Settings

The sticky/floating placement also renders inside `wp_footer` and stays
pinned to the viewport.

| Setting | Values | Default | Description |
|---------|--------|---------|-------------|
| **Position** | `bottom-right`, `bottom-left`, `bottom-bar`, `top-bar` | `bottom-right` | Where the element is pinned |

- `bottom-right` / `bottom-left` — floating card anchored to the corner
- `bottom-bar` / `top-bar` — full-width bar spanning the viewport

**Close behavior:** a close button (`.wbam-sticky-close`) hides the bar
for the current page view. Combine with a session limit to persist the
dismissal across pages.

---

## Multiple Ads Per Placement

When multiple ads share a placement, they rotate automatically. The ad shown is selected based on **Priority**, set per ad using a slider from 1 to 10 in the **Ad Status** metabox.

Higher priority ads are shown more often. No zone configuration is needed — simply assign multiple ads to the same placement.

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
[wbam_ad id="123"]                    # Display a specific ad
[wbam_ad id="123" class="featured"]   # With custom CSS class
[wbam_ads ids="1,2,3"]                # Display multiple specific ads
```

| Parameter | Description | Default |
|-----------|-------------|---------|
| `id` | Specific ad ID (required) | - |
| `ids` | Comma-separated ad IDs | - |
| `class` | Custom CSS class | - |

---

## Related Guides

- [Ad Types](03-ad-types.md) - What kind of ads to create
- [Targeting](05-targeting.md) - Control when ads show
- [Shortcodes](../shortcode-reference/ad-shortcodes.md) - Display options
