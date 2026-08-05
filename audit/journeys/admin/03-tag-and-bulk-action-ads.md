---
journey: tag-and-bulk-action-ads
plugin: wb-ads-rotator-with-split-test
priority: high
roles: [administrator]
covers: [ad-tags, bulk-action-by-group]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 5 ads exist"
estimated_runtime_minutes: 5
---

# Tag ads, then act on a whole group at once

Ads were the only object in this plugin with no grouping. A site owner with real
inventory could not find one sponsor's ads, stop them all when a contract lapsed,
or hand them to someone else to manage.

The fix is a tag filter, not a new bulk action. Enable/Disable already act on the
selection, so narrowing the list is what makes acting on a group possible. That
makes the scope of the action the thing to verify: it must hit the filtered ads
and nothing else.

## Setup

- Site: `$SITE_URL`, administrator (`?autologin=1`)
- Ads list: `/wp-admin/edit.php?post_type=wbam-ad`

## Steps

### 1. Tag several ads, including one in two tags
- **Action**: on the ad edit screen, add tags via the Ad Tags box. Give three ads
  `sponsor-a`; give two others `season-2026`; leave at least one ad untagged.
  Add a second tag to one of the `sponsor-a` ads.
- **Expect**: the ad carrying two tags shows both. If a second tag replaces the
  first, this is behaving as folders and the feature is broken.

### 2. The filter appears with counts
- **Action**: reload the ads list.
- **Expect**: an "All ad tags" dropdown beside the status filter, listing each
  tag with its count.
- **Note**: the dropdown is hidden entirely when no tags exist - an empty filter
  is worse than none.

### 3. Filtering narrows the list
- **Action**: choose `sponsor-a`, Filter.
- **Expect**: only the three `sponsor-a` ads listed; URL carries
  `&wbam_ad_tag=sponsor-a`; the dropdown keeps the selection.

### 4. Bulk action hits the group, and only the group
- **Action**: with the filter still applied, tick select-all, choose
  **Disable ads**, Apply.
- **Expect**: a confirmation naming the count.
- **Verify in the database, not the screen**:
  ```sql
  SELECT p.post_title, pm.meta_value AS enabled
  FROM wp_posts p
  LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_wbam_enabled'
  WHERE p.post_type = 'wbam-ad';
  ```
  The three tagged ads must read `0`. **Every other ad must be unchanged**,
  including the untagged one and the `season-2026` ones. If anything outside the
  filter moved, the filter is not constraining selection and this fails.

### 5. Known limit - pagination
- **Expect**: select-all covers the current page only. With more ads under one
  tag than the per-page setting, the action applies to that page alone. This is
  WordPress's behaviour on every post list, not specific to ads. Screen Options
  raises per-page.
- This step exists so the limit is documented rather than discovered.

### 6. Tagging does not change delivery
- **Expect**: applying or removing a tag has no effect on which ads serve. Tags
  are organisation only until tag-driven targeting is designed.

## Pass criteria

- An ad holds several tags at once.
- Filter narrows; bulk action changes the filtered ads and nothing else.
- Untagged ads are never touched.
- Ad delivery is unaffected by tagging.

## On failure

If ads outside the filter changed, check that the filter is applied to the list
query before selection. If a second tag replaces the first, check the taxonomy is
registered against `wbam-ad` and that the metabox is the tag input rather than a
single-select.
