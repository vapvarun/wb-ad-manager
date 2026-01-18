# Settings

Configure WB Ad Manager at **WB Ad Manager > Settings**.

---

## General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Disable for Logged-in Users** | Hide all ads from logged-in users | Off |
| **Disable for Admins** | Hide all ads from administrators | Off |
| **Disabled Post Types** | Post types where ads won't show | None |

### When to Use

- **Disable for Admins**: See your site without ads while testing
- **Disable for Logged-in**: Premium member perk (ad-free experience)

---

## AdSense Settings

| Setting | Description |
|---------|-------------|
| **Publisher ID** | Your AdSense publisher ID (ca-pub-xxx) |
| **Auto Ads** | Enable AdSense Auto Ads site-wide |

### Getting Your Publisher ID

1. Log into Google AdSense
2. Click your account icon
3. Find "Publisher ID" (starts with ca-pub-)

---

## Link Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Link Prefix** | URL prefix for cloaked links | `go` |
| **Default NoFollow** | Add nofollow to all links | On |
| **Default Sponsored** | Add sponsored attribute | Off |
| **Enable Tracking** | Track clicks by default | On |

### Link URL Format

With prefix "go": `yoursite.com/go/link-slug`

You can change to: `yoursite.com/out/link-slug` or `yoursite.com/visit/link-slug`

---

## Tracking Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Track Impressions** | Count ad views | On |
| **Track Clicks** | Count ad clicks | On |
| **Exclude Bots** | Don't count bot traffic | On |
| **Exclude Admins** | Don't count admin views | On |

### Data Retention

| Setting | Description |
|---------|-------------|
| **Keep Stats For** | Days to retain detailed stats |
| **Archive Old Stats** | Summarize old data to save space |

---

## Display Settings

| Setting | Description |
|---------|-------------|
| **Default Ad Container** | Wrapper class for all ads |
| **Lazy Loading** | Load ads when scrolled into view |
| **Mobile Breakpoint** | Pixel width for mobile detection |

---

## Modules

Enable or disable plugin features:

| Module | Description |
|--------|-------------|
| **Ad Types** | Image, Code, Rich Content, AdSense, Email |
| **Placements** | Header, Footer, Content, Sidebar, etc. |
| **Link Management** | Cloaked links and tracking |
| **Geo Targeting** | Location-based targeting |
| **BuddyPress** | BuddyPress integration |
| **bbPress** | bbPress integration |

Disable modules you don't use to simplify the admin interface.

---

## Import/Export

### Export Settings

1. Go to Settings > Import/Export
2. Click **Export Settings**
3. Save the JSON file

### Import Settings

1. Go to Settings > Import/Export
2. Click **Choose File**
3. Select your JSON file
4. Click **Import**

Useful for migrating settings between sites.

---

## Performance Tips

### For Speed

- Enable lazy loading for ads below the fold
- Disable unused modules
- Use image optimization for ad images
- Consider fewer ad placements

### For Accuracy

- Exclude bots from tracking
- Exclude admins from tracking
- Set appropriate data retention

---

## Related Guides

- [Targeting](05-targeting.md) - Per-ad targeting options
- [Placements](04-placements.md) - Available placements
