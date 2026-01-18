# Settings

Configure WB Ad Manager at **WB Ad Manager > Settings**.

---

## General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Disable for Logged-in Users** | Hide all ads from logged-in users | Off |
| **Disable for Admins** | Hide all ads from administrators | Off |
| **Minimum Content Length** | Minimum characters required to show paragraph ads | 300 |
| **Disabled Post Types** | Post types where ads won't show | None |
| **Maximum Ads Per Page** | Limit how many ads display per page | 10 |

### When to Use

- **Disable for Admins**: See your site without ads while testing
- **Disable for Logged-in**: Premium member perk (ad-free experience)
- **Minimum Content Length**: Prevents ads from appearing on very short content. Set to 0 to disable this check.
- **Maximum Ads Per Page**: Prevents ad overload. Set to 0 for unlimited.

---

## Display Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Ad Label Text** | Optional label shown with ads (e.g., "Advertisement") | Advertisement |
| **Label Position** | Show label above or below the ad | Above |
| **Custom Container Class** | Additional CSS class for ad containers | Empty |

### Ad Label

Adding a label helps with:
- FTC/advertising disclosure compliance
- User transparency
- Ad identification

Leave the label text empty to disable the label entirely.

### Custom Container Class

Add your own CSS class to style ads consistently:
```css
.my-custom-ad-class {
    border: 1px solid #eee;
    padding: 10px;
    margin: 20px 0;
}
```

---

## Performance Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Lazy Load Ads** | Load ads only when scrolled into view | On |
| **Cache Ad Queries** | Cache database queries using transients | On |

### Lazy Loading

When enabled, ads below the fold won't load until the user scrolls to them. This improves:
- Initial page load time
- Core Web Vitals scores
- User experience

### Cache Ad Queries

Caches ad selection queries to reduce database load. Uses WordPress transients with automatic invalidation when ads change. Recommended for sites with many ads.

---

## Geo Targeting Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Primary Provider** | Service for IP geolocation | ip-api |
| **ipinfo.io API Key** | API key for ipinfo.io (optional) | Empty |

### Available Providers

| Provider | Requests | Accuracy | API Key Required |
|----------|----------|----------|------------------|
| **ip-api.com** | 45/minute | Good | No |
| **ipinfo.io** | 50K/month | Excellent | Yes (free tier available) |

### Getting an ipinfo.io API Key

1. Go to [ipinfo.io](https://ipinfo.io)
2. Sign up for a free account
3. Copy your API key from the dashboard
4. Paste into the settings

The free tier provides 50,000 requests per month, sufficient for most sites.

---

## AdSense Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Publisher ID** | Your AdSense publisher ID (ca-pub-xxx) | Empty |
| **Auto Ads** | Enable AdSense Auto Ads site-wide | Off |

### Getting Your Publisher ID

1. Log into [Google AdSense](https://www.google.com/adsense/)
2. Click your account icon
3. Find "Publisher ID" (starts with ca-pub-)

### Auto Ads

When enabled, Google automatically places ads throughout your site. This works alongside any manually placed ads.

**Note:** Auto Ads may place ads in locations you don't control. Test thoroughly before enabling.

---

## Privacy & GDPR Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Require Consent for AdSense** | Only load AdSense after user consent | Off |
| **Anonymize IP Addresses** | Store hashed IPs instead of raw addresses | On |

### Consent for AdSense

When enabled, AdSense scripts won't load until the user gives consent. Works with popular consent plugins:
- Cookie Notice
- CookieYes
- Complianz
- GDPR Cookie Consent
- And others that fire `wp_set_consent()` or use standard consent APIs

### IP Anonymization

When enabled, IP addresses are hashed before storage, making them non-identifiable. This helps with:
- GDPR compliance
- Privacy protection
- Reduced data liability

Recommended to keep this ON for sites with EU visitors.

---

## Advanced Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Delete Data on Uninstall** | Remove all plugin data when uninstalling | Off |

### Delete Data on Uninstall

When enabled, uninstalling the plugin will permanently delete:
- All ads and ad data
- All tracked links
- All statistics and analytics
- All plugin settings

**Warning:** This cannot be undone. Only enable if you're certain you want complete removal.

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

**Important:** Changing the prefix will break existing links. Update any hardcoded URLs after changing.

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

Useful for migrating settings between sites or backing up your configuration.

---

## Performance Tips

### For Speed

- Enable lazy loading for ads below the fold
- Enable ad query caching
- Disable unused modules
- Use image optimization for ad images
- Consider fewer ad placements

### For Accuracy

- Exclude bots from tracking
- Exclude admins from tracking
- Set appropriate data retention

### For Privacy

- Enable IP anonymization
- Require consent for AdSense (if targeting EU)
- Review data retention settings

---

## Related Guides

- [Targeting](05-targeting.md) - Per-ad targeting options
- [Placements](04-placements.md) - Available placements
