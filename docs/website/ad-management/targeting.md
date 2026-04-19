# Targeting & Display Rules

Control when, where, and to whom your ads appear.

---

## Display Rules

### Show on All Pages (with exclusions)

Default behavior. Ad shows everywhere except excluded items.

**Exclusion options:**
- Specific posts/pages
- Categories
- Tags
- Page types (front page, blog, archives, 404)

### Show on Specific Pages Only

Ad only shows on selected items.

**Inclusion options:**
- Post types (posts, pages, custom)
- Specific posts/pages
- Categories
- Tags
- Page types

---

## Visitor Targeting

### Device Targeting

| Device | Description |
|--------|-------------|
| **Desktop** | Computers, laptops |
| **Tablet** | iPad, Android tablets |
| **Mobile** | Smartphones |

Select one or more. Ad only shows on selected devices.

**Use case:** Show mobile-optimized ads only on phones.

### User Status

| Option | Shows To |
|--------|----------|
| **All Users** | Everyone |
| **Logged In Only** | Registered users |
| **Logged Out Only** | Visitors not logged in |

**Use case:** Show signup prompts only to logged-out visitors.

### User Roles

When targeting logged-in users, specify roles:

- Administrator
- Editor
- Author
- Contributor
- Subscriber
- Custom roles

**Use case:** Show upgrade offers only to free members.

---

## Scheduling

### Date Range

| Field | Description |
|-------|-------------|
| **Start Date** | When ad begins showing |
| **End Date** | When ad stops showing |

Leave empty for no restriction.

### Day of Week

Select specific days:
- Monday through Sunday
- Or all days (default)

**Use case:** Weekend sale ads only on Sat/Sun.

### Time of Day

| Field | Description |
|-------|-------------|
| **Start Time** | Hour ad begins (e.g., 9:00 AM) |
| **End Time** | Hour ad stops (e.g., 5:00 PM) |

Based on your WordPress timezone setting.

**Use case:** Happy hour promotions 4-7 PM only.

---

## Geo Targeting

Target by geographic location using IP detection at the **country level**.

### Setup

1. Edit ad
2. Find **Geo Targeting** section
3. Choose mode: **Include** (show only in selected countries) or **Exclude** (hide in selected countries)
4. Select countries from the dropdown
5. Save

### Supported Providers

| Provider | Free Tier | Notes |
|----------|-----------|-------|
| ip-api.com | 45 req/min | Default, no key needed |
| ipinfo.io | 50K/month | API key required |
| ipapi.co | 1K/day | API key required |

### Notes

- Country-level targeting only (95-99% accurate)
- VPN users may see wrong location
- Configure provider in Settings > Geo Targeting

---

## Frequency Capping

Limit how often users see an ad within a single session.

| Setting | Description |
|---------|-------------|
| **Session Impression Limit** | Maximum times the ad is shown per browser session. Leave empty or set to 0 for unlimited. |

Uses cookies to track impressions within the current session. Resets when the user closes the browser or clears cookies.

---

## Example Configurations

### Scenario: Holiday Sale

```
Scheduling:
- Start: Dec 20
- End: Dec 26

Display Rules:
- All pages except checkout

Targeting:
- All devices
- All users
```

### Scenario: Mobile App Promo

```
Device Targeting:
- Mobile only

User Status:
- Logged in only

Display Rules:
- Show on dashboard page only
```

### Scenario: Local Business Ad

```
Geo Targeting:
- United States > California > Los Angeles

Scheduling:
- Mon-Fri
- 9 AM - 6 PM

Placements:
- Sidebar
- After paragraph 3
```

---

## Troubleshooting

### Ad not showing

1. Check if enabled (toggle is ON)
2. Check schedule (not expired, correct time)
3. Check display rules (not excluded from current page)
4. Check device (viewing on targeted device?)
5. Check user status (logged in/out as expected?)
6. Check frequency cap (not exceeded?)

### Ad showing when it shouldn't

1. Review display rules - might be set to "All"
2. Check exclusions are saved properly
3. Clear caching plugins
4. Check targeting conditions

---

## Related Guides

- [Placements](placements.md) - Where ads appear
- [Settings](settings.md) - Global plugin settings
