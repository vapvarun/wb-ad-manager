# Common Issues & Solutions

## What You'll Learn

- How to fix common problems
- Where to find error information
- When to contact support

---

## Quick Fixes

Before diving into specific issues, try these:

1. **Clear caches** - Browser cache, WordPress cache, CDN cache
2. **Flush permalinks** - Settings → Permalinks → Save Changes
3. **Check plugin conflicts** - Deactivate other plugins temporarily
4. **Update WordPress** - Ensure you're on the latest version

---

## Installation Issues

### Plugin won't activate

**Error:** "Plugin could not be activated because it triggered a fatal error"

**Solutions:**
1. Check PHP version (requires 7.4+)
   - Go to Tools → Site Health → Info → Server
2. Check WordPress version (requires 5.8+)
3. Increase PHP memory limit to 128MB+
4. Check error log for specific message

### Menu not appearing after activation

**Solutions:**
1. Clear browser cache and refresh
2. Log out and log back in
3. Check user role has administrator capabilities
4. Deactivate/reactivate the plugin

---

## Ad Display Issues

### Ads not showing

**Checklist:**
- [ ] Ad is published (not draft or disabled)
- [ ] Ad has at least one placement checked, or you are using a shortcode with the correct ID
- [ ] Start date has passed (if set)
- [ ] End date has not passed (if set)
- [ ] Session Impression Limit has not been reached for this visitor

**Debug steps:**
1. Try the shortcode directly: `[wbam_ad id="123"]` (use the actual ad ID)
2. Check ad status in **WB Ad Manager → Ads**
3. View page source to check if the ad container renders
4. Test in an incognito window to rule out session limits

### Shortcode shows as plain text

**Symptoms:** You see `[wbam_ad id="123"]` instead of an ad

**Solutions:**
1. Verify the plugin is activated
2. Check shortcode spelling — `wbam_ad` not `wbam-ad` (case-sensitive)
3. Ensure no extra spaces in shortcode
4. Try in a different page/post
5. Switch to a default theme temporarily

### Ad not showing even though it's published

**Common causes:**
- The ad is disabled — check the **Ad Status** metabox (Enabled/Disabled toggle)
- No placements are checked and there is no shortcode for that ad
- The **Session Impression Limit** has been reached for this visitor — test in incognito mode
- The **Disable for Admins** setting is on — go to Settings and turn it off temporarily

### Same ad always showing

**Causes:**
- Only one ad is assigned to that placement
- Aggressive page caching

**Solutions:**
1. Add more published ads with the same placement checked
2. Clear all caches
3. Test in incognito/private browsing

---

## Click Tracking Issues

### Clicks not being tracked

**Solutions:**
1. Go to Settings and verify tracking is enabled
2. Check destination URL is valid (starts with http or https)
3. Test in incognito mode — ad blockers can interfere
4. Check for JavaScript errors in browser console
5. Verify the link is not cached by a CDN

### Analytics showing zero

**Solutions:**
1. Wait a few minutes (stats may be delayed)
2. Clear any caching
3. Check database tables exist (deactivate and reactivate plugin if missing)
4. Verify the tracking script is loading (view page source)

---

## Link Management Issues

### Links not displaying

**Solutions:**
1. Verify link ID is correct
2. Check the link is published
3. Verify shortcode syntax: `[wbam_link id="123"]`

### Partnership form not working

**Symptoms:** Form submits but nothing happens

**Solutions:**
1. Check browser console for JavaScript errors
2. Verify AJAX URL is accessible
3. Check email settings in WordPress
4. Look for form validation errors

### Partnership emails not sending

**Solutions:**
1. Check WordPress email works (test with other plugins)
2. Verify email address in settings is correct
3. Check spam/junk folder
4. Use an SMTP plugin (WP Mail SMTP recommended)
5. Check if your server is blocking outbound mail

---

## Performance Issues

### Pages loading slowly

**Solutions:**
1. Enable lazy loading in **WB Ad Manager → Settings → Performance**
2. Enable cache ad queries in the same section
3. Reduce number of ad placements per page
4. Optimize ad images before uploading

### High server resource usage

**Solutions:**
1. Enable ad query caching in Settings
2. Reduce the number of active placements per page
3. Optimize database tables (use a plugin like WP-Optimize)

---

## Styling Issues

### Ads breaking layout

**Solutions:**
1. Add container width CSS:
```css
.wbam-ad {
    max-width: 100%;
    overflow: hidden;
}
```
2. Check for responsive issues on mobile
3. Use browser inspector to find conflicts

### Ads not responsive

**Solution CSS:**
```css
.wbam-ad {
    width: 100%;
    max-width: 100%;
}

.wbam-ad img {
    max-width: 100%;
    height: auto;
}

@media (max-width: 768px) {
    .wbam-ad {
        text-align: center;
    }
}
```

---

## Database Issues

### "Table doesn't exist" error

**Solutions:**
1. Deactivate and reactivate plugin — this re-runs the table creation
2. Check database prefix matches `wp-config.php`
3. Contact hosting for database access issues

### Stats not saving

**Solutions:**
1. Check database write permissions
2. Verify tables exist (deactivate and reactivate)
3. Check available disk space
4. Look for database errors in the error log

---

## Debugging

### Enable WordPress Debug Mode

Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check logs at: `/wp-content/debug.log`

### Check JavaScript Console

1. Open browser Developer Tools (F12)
2. Go to Console tab
3. Look for red error messages
4. Note any errors mentioning `wbam`

### Check Network Tab

1. Open Developer Tools → Network tab
2. Reload the page
3. Look for failed requests (shown in red)
4. Check AJAX calls are returning 200 responses

---

## Conflicts with Other Plugins

### Common Conflict Sources

| Plugin Type | Potential Issue |
|-------------|----------------|
| Caching | Stale ads, tracking issues |
| Security | Blocked AJAX, false positives |
| Optimization | Broken JavaScript |
| Page builders | Shortcode rendering |
| Ad blockers | Hidden ads, no tracking |

### Testing for Conflicts

1. Activate only WB Ad Manager and a default theme
2. Test if issue persists
3. Reactivate plugins one by one
4. Identify the conflicting plugin
5. Contact support with findings

---

## Getting Help

### Before Contacting Support

Gather this information:
- WordPress version
- Plugin version
- PHP version
- Theme name
- List of active plugins
- Exact error message
- Steps to reproduce

### Checking Plugin Version

1. Go to **Plugins → Installed Plugins**
2. Find "WB Ad Manager"
3. Note the version number

### Support Resources

- **Documentation:** Read relevant docs first
- **WordPress Forum:** Community support
- **GitHub Issues:** Bug reports

---

## Upgrade to Pro

Many issues are solved in the Pro version:
- Advanced debugging tools
- Priority support
- More configuration options
- Better error handling

[Learn About Pro →](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/)

---

*Still having issues? Make sure you've tried all the quick fixes at the top of this page.*
