---
journey: rest-permission-gates
plugin: wb-ads-rotator-with-split-test
priority: critical
roles: [anonymous, subscriber, administrator]
covers: [rest-api, permission_callback, capability-checks, wbam/v1]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "REST namespace wbam/v1 is registered (verify via $SITE_URL/wp-json/)"
  - "At least one wbam-ad post and one wbam-link row exist"
estimated_runtime_minutes: 4
---

# REST endpoints enforce permissions correctly across roles

The plugin exposes 22 REST routes under `wbam/v1`. If any of them silently allow anonymous access where they shouldn't, customers leak admin data; if they reject legitimate admin access, integrations break. This journey is the security canary for the REST layer.

## Setup

- Site: `$SITE_URL`
- Anonymous: bare curl, no cookies
- Admin: dev-auto-login (`?autologin=1` to set cookies, then re-use)
- DB:
  ```sql
  SELECT route, methods FROM information_schema.tables LIMIT 0;  -- (sanity ping)
  ```

## Steps

### 1. Discover the registered routes
- **Action**: `curl -s $SITE_URL/wp-json/wbam/v1 | jq '.routes | keys'`
- **Expect**: returns a JSON array containing `/wbam/v1/...` route names. Capture into `ROUTES`.
- **On fail**: REST namespace not registered — check `includes/API/` bootstrap

### 2. Anonymous request to a settings/admin route should be rejected
- **Action**: `curl -s -o /dev/null -w "%{http_code}" $SITE_URL/wp-json/wbam/v1/settings`
- **Expect**: HTTP 401 or 403 (NOT 200, NOT 500)
- **On fail**: settings controller has `__return_true` permission_callback — security regression

### 3. Anonymous request to a public route (e.g., ad render data) should succeed
- **Action**: pick a route documented in audit/manifest.json with `permission: __return_true` or equivalent (e.g., `/wbam/v1/ads/render` if present), curl it
- **Expect**: HTTP 200, returns expected JSON shape
- **On fail**: legitimate public route is gated incorrectly — check controller

### 4. Admin request to settings route succeeds
- **Action**: First `playwright_navigate $SITE_URL/?autologin=1` to set admin cookies. Then via Playwright run: `await fetch('/wp-json/wbam/v1/settings', { credentials: 'include' })` and capture status.
- **Expect**: HTTP 200, returns settings JSON

### 5. Subscriber-level user is rejected from admin routes
- **Action**: log out / use a subscriber test user (`?autologin=subscriber-username`), then fetch `/wbam/v1/settings`
- **Expect**: HTTP 401 or 403
- **On fail**: cap check uses too-permissive cap (e.g., `read` instead of `manage_options`)

### 6. No __return_true on non-allowlisted routes
- **Action**: `grep -rn "__return_true" includes/API/ | grep -v class-abilities`
- **Expect**: empty output, OR every match is on a controller documented in audit/FEATURE_AUDIT.md as intentionally public
- **On fail**: file the offending route as a security issue

## Pass criteria

ALL of the following hold:
1. REST namespace `wbam/v1` is reachable and lists routes.
2. Admin-only routes return 401/403 to anonymous and subscriber roles.
3. Admin-only routes return 200 to administrator role.
4. Routes documented as public return 200 to anonymous.
5. No `__return_true` permission_callback exists on a non-documented route.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Settings route 200 for anon | permission_callback wrong | `includes/API/class-settings-controller.php` |
| Public route 401 for anon | over-restrictive cap on a public route | the offending controller |
| 500 on any route | fatal in callback | wp-content/debug.log + the controller's method |
| Subscriber gets settings | cap check uses `read` not `manage_options` | controller permission_callback |
