---
journey: admin-surfaces-render-clean
plugin: wb-ads-rotator-with-split-test
priority: high
roles: [administrator]
covers: [admin-uniformity, no-fatal-on-any-screen]
prerequisites:
  - "Free and Pro both active"
estimated_runtime_minutes: 6
---

# Every admin screen loads, and looks like the same product

A fatal on one settings tab, or one screen still wearing WordPress's default
heading while its siblings use the branded one, is invisible to lint, PHPStan and
PHPUnit alike. It is only visible by loading the pages.

This is a breadth check, not a depth one: it asserts nothing about what each
screen does, only that it renders, is free of PHP output, and carries the shared
chrome.

## Setup

- Site: `$SITE_URL`, signed in as administrator (`?autologin=1`)

## Steps

### 1. Load every plugin admin screen

Under `edit.php?post_type=wbam-ad&page=`:
`wbam-inventory`, `wbam-ab-testing`, `wbam-campaigns`, `wbam-submissions`,
`wbam-analytics`, `wbam-revenue`, `wbam-audit-log`, `wbam-pro-settings`,
`wbam-settings`, `wbam-tools`, `wbam-email-captures`, `wbam-help`

Under `admin.php?page=`:
`wbam-advertisers`, `wbam-packages`, `wbam-packages&action=add`, `wbam-transactions`

- **Expect per page**: HTTP 200; no `Fatal error` / `Uncaught`; no rendered
  `<b>Warning</b>` / `<b>Notice</b>` / `<b>Deprecated</b>`; no
  "not allowed to access this page".

### 2. Shared chrome is present
- **Expect**: each plugin screen contains `.wbam-page-header` and loads the
  `wbam-toast` handle.
- **Note**: `edit.php` and `post-new.php` for the ad CPT are WordPress's own
  screens and correctly use WP's native heading - they are excluded from the
  page-header assertion, not exempt from the fatal/notice ones.

### 3. List tables share one treatment
- **Expect**: every `.wp-list-table` on a plugin screen also carries
  `wbam-admin-table`, and its header cells render uppercase with letter-spacing.
  A list table using WordPress's default density next to one that does not is the
  regression this catches.

### 4. Sorting and bulk actions still work
- **Action**: on Advertisers, follow a sortable column header link.
- **Expect**: HTTP 200, row order changes, and the table still carries
  `wbam-admin-table`. A table that looks right and cannot sort is a regression,
  not a fix.

## Pass criteria

- 16 plugin screens: 200, no fatals, no PHP notices, no false denials.
- Page header and toast on every plugin screen.
- Sorting still functions.
