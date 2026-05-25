---
journey: admin-create-publish-ad
plugin: wb-ads-rotator-with-split-test
priority: critical
roles: [administrator]
covers: [wbam-ad CPT, admin-ui, settings-save, install-default-roles]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user exists (user ID 1) and dev-auto-login mu-plugin is installed (?autologin=1)"
estimated_runtime_minutes: 4
---

# Administrator creates and publishes a new ad via the admin UI

The admin ad-creation flow is the entry point for every customer's first successful use of the plugin. If the CPT registration, capability mapping, or the admin-page render breaks, no ad ever gets created and every other journey is moot.

## Setup

- Site: `$SITE_URL`
- Admin login: `$SITE_URL/?autologin=1` (dev-auto-login mu-plugin)
- Test user: admin (ID 1)
- DB sanity check:
  ```sql
  SHOW TABLES LIKE 'wp_wbam%';   -- expect: links, link_categories, link_clicks, analytics, email_submissions, link_partnerships, rate_limits
  ```

## Steps

### 1. Auto-login and navigate to the ad list
- **Action**: `playwright_navigate $SITE_URL/wp-admin/edit.php?post_type=wbam-ad&autologin=1`
- **Expect**: page renders 200 OK, page title contains "Ads" or "WB Ad Manager"
- **On fail**: CPT not registered (check `Installer` / `register_post_type` call site)

### 2. Click "Add New" and capture the editor URL
- **Action**: `playwright_click 'a:has-text("Add New")'` (or navigate `$SITE_URL/wp-admin/post-new.php?post_type=wbam-ad`)
- **Expect**: post-new editor renders with title input and ad-type metabox visible

### 3. Fill in title + minimum ad payload
- **Action**: `playwright_fill 'input#title' "Journey Test Ad $(date +%s)"`; set ad type via metabox (e.g., select "Plain Text" + body "Journey test")
- **Capture**: page title text -> `AD_TITLE`

### 4. Click Publish
- **Action**: `playwright_click 'button:has-text("Publish")'`
- **Expect**: success notice "Post published" appears; URL contains `&action=edit&post=`. Capture published post ID.
- **Capture**: `AD_ID` <- from URL or `mysql_query "SELECT ID FROM wp_posts WHERE post_title=$AD_TITLE AND post_type='wbam-ad'"`

### 5. Verify post is published
- **Action**: `mysql_query "SELECT post_status FROM wp_posts WHERE ID = $AD_ID"`
- **Expect**: `publish`

### 6. Verify ad metadata persisted
- **Action**: `mysql_query "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = $AD_ID"`
- **Expect**: at least one ad-config meta row (e.g., `_wbam_ad_type`, ad payload)

## Pass criteria

ALL of the following hold:
1. The wbam-ad list page renders for admin without 403 / fatal.
2. The post-new editor loads and the ad-type UI is visible.
3. Publish succeeds and returns to the editor with `post_status='publish'`.
4. At least one ad-config meta row exists for the new post.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| 403 on edit.php?post_type=wbam-ad | Capability map incorrect for admin role | `includes/Admin/` `register_post_type` cap args |
| Editor missing ad-type metabox | Metabox registration not hooked or hidden by Gutenberg | `includes/Admin/class-admin.php` `add_meta_box` |
| Publish fails silently | save_post handler errored | `includes/Admin/` save_post hook + `error_log` |
| Meta not saved | Nonce check or sanitization rejected payload | save_post wp_verify_nonce + sanitize_* calls |
