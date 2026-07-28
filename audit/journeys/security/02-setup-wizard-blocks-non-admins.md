---
journey: setup-wizard-blocks-non-admins
plugin: wb-ads-rotator-with-split-test
priority: critical
roles: [subscriber, administrator]
covers: [setup-wizard-privilege-escalation]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A subscriber account exists"
  - "dev-auto-login mu-plugin installed for ?autologin="
estimated_runtime_minutes: 3
---

# The setup wizard refuses non-administrators

The wizard renders its own page and exits from `admin_init`. That hook runs
*before* `wp-admin/admin.php` resolves the capability attached to the wizard's
menu registration, so WordPress's own gate never fired for it. Its only guard
matched on `$_GET['page']`, which is satisfied on **any** admin file - including
`profile.php`, which subscribers can load.

Any logged-in user could therefore open the wizard, be issued a valid
`wbam_setup_sample` nonce (nonces are per-user, so theirs verified), and POST
`save_step` to create ad posts. Subscriber to author-of-published-ads.

A unit test covers the handler. This journey covers the thing a unit test cannot
see: that the page itself is unreachable through every admin file a low-privilege
user can load.

## Setup

- Site: `$SITE_URL`
- Subscriber: `lrn_demo_tomas` (any subscriber works)
- Administrator: user 1

## Steps

### 1. Sign in as the subscriber
- **Action**: `playwright_navigate $SITE_URL/wp-admin/?autologin=lrn_demo_tomas`
- **Expect**: wp-admin loads; no Users menu in the sidebar (confirms low privilege)

### 2. Try the wizard through profile.php - the file subscribers can load
- **Action**: `curl -b <session> "$SITE_URL/wp-admin/profile.php?page=wbam-setup"`
- **Expect**: HTTP 403. Body must NOT contain `WB Ad Manager Setup`,
  `wbam-setup-wrapper`, or `wbam_setup_nonce`.

### 3. Try the dashboard route
- **Action**: `curl -b <session> "$SITE_URL/wp-admin/index.php?page=wbam-setup"`
- **Expect**: HTTP 403, same absences as step 2.

### 4. Confirm no nonce was issued
- **Expect**: neither response contains `wbam_setup_nonce`. A nonce in the body
  is the exploit precondition - without it the POST cannot be forged.

### 5. The wizard still works for an administrator
- **Action**: `playwright_navigate $SITE_URL/wp-admin/?autologin=1` then
  `$SITE_URL/wp-admin/index.php?page=wbam-setup`
- **Expect**: the wizard renders - stepper showing Welcome / Sample Ads / Ready,
  and a "Let's Go!" button. The guard must block the right people, not everyone.

## Pass criteria

- Subscriber: 403 on both routes, no wizard markup, no nonce.
- Administrator: wizard renders and is usable.

## On failure

Check `Setup_Wizard::setup_wizard()` still calls `current_user_can( 'manage_options' )`
before `ob_start()`. Removing it restores the escalation - a subscriber reaches
`wp_safe_redirect()` having created ads.
