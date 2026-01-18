# Ad Types

WB Ad Manager supports five ad types. Choose based on what you're displaying.

---

## Available Ad Types

| Type | Best For | Tracks Clicks |
|------|----------|---------------|
| **Image** | Banners, affiliate graphics | Yes |
| **Code** | AdSense, third-party networks | Depends on code |
| **Rich Content** | Native ads, promotions | Yes |
| **AdSense** | Google AdSense specifically | Via AdSense |
| **Email Capture** | Newsletter signups | N/A |

---

## Image Ad

Display banner images with click tracking.

![Image Ad Editor](../images/for-site-owners/ad-editor-image-type.png)
*The Image Ad editor showing image upload, destination URL, and tracking options*

### Settings

| Field | Description |
|-------|-------------|
| **Image** | Upload or select from media library |
| **Destination URL** | Where clicks go |
| **Alt Text** | Image alt attribute (accessibility) |
| **Open in New Tab** | Yes/No |

### Best Practices

- Use standard IAB sizes: 300x250, 728x90, 160x600
- Optimize images for web (compress, proper format)
- Always set alt text for accessibility
- Test click tracking after setup

---

## Code Ad

Insert HTML, JavaScript, or ad network code.

![HTML/Code Ad Editor](../images/for-site-owners/ad-editor-html-type.png)
*The Code Ad editor showing the code input area and wrapper options*

### Settings

| Field | Description |
|-------|-------------|
| **Code** | Your HTML/JavaScript code |
| **Wrap in Container** | Add wrapper div around code |

### Common Uses

- Google AdSense manual units
- Third-party ad networks (Media.net, etc.)
- Custom tracking pixels
- Embedded content

### Security Note

Only use code from trusted sources. Malicious code can compromise your site.

---

## Rich Content Ad

Create ads using the WordPress block editor.

### Settings

| Field | Description |
|-------|-------------|
| **Content** | Full block editor |
| **Destination URL** | Optional click-through |

### Best For

- Native advertising that matches your content
- Promotional announcements
- Styled call-to-action boxes
- Content that needs formatting

### Tips

- Match your site's design for native feel
- Use heading blocks sparingly
- Keep it concise - it's an ad, not an article

---

## AdSense Ad

Dedicated Google AdSense integration with responsive sizing.

### Settings

| Field | Description |
|-------|-------------|
| **Ad Unit ID** | From your AdSense account |
| **Format** | Auto, Horizontal, Vertical, Rectangle |
| **Responsive** | Enable responsive sizing |

### Setup Steps

1. Get your Ad Unit ID from AdSense dashboard
2. Create new ad in WB Ad Manager
3. Select "AdSense" type
4. Enter your Ad Unit ID
5. Choose format and save

### Requirements

- Active Google AdSense account
- Site approved by AdSense
- Publisher ID configured in Settings

---

## Email Capture Ad

Collect email addresses with a subscription form.

### Settings

| Field | Description |
|-------|-------------|
| **Headline** | Form headline |
| **Description** | Form description text |
| **Button Text** | Submit button label |
| **Show Name Field** | Include name input |
| **Success Message** | Thank you message |
| **Redirect URL** | Optional redirect after submit |

### Integration

Connect to your email service using the `wbam_email_captured` hook:

```php
add_action( 'wbam_email_captured', function( $email, $name, $ad_id ) {
    // Send to Mailchimp, ConvertKit, etc.
}, 10, 3 );
```

### Best Practices

- Offer value (ebook, discount, newsletter)
- Keep form fields minimal
- Test the form after setup
- Comply with GDPR/privacy laws

---

## Choosing the Right Type

| Scenario | Recommended Type |
|----------|-----------------|
| Affiliate banner | Image |
| Google AdSense | AdSense (or Code) |
| Third-party network | Code |
| Promotional announcement | Rich Content |
| Newsletter signup | Email Capture |
| Custom tracking pixel | Code |
| Native advertising | Rich Content |

---

## Related Guides

- [Managing Ads](01-managing-ads.md) - Create and edit ads
- [Placements](04-placements.md) - Where to display ads
- [Targeting](05-targeting.md) - When and to whom
