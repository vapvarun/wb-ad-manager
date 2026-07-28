---
journey: advertiser-cannot-reach-another-advertiser
plugin: wb-ad-manager-pro
priority: critical
roles: [advertiser]
covers: [portal-ownership-enforcement]
prerequisites:
  - "Pro active; advertiser portal page published"
  - "At least two advertisers, each owning a campaign"
estimated_runtime_minutes: 4
---

# One advertiser cannot see or touch another's campaigns

The portal identifies the acting advertiser from the session and compares it to
the owner of whatever id is in the URL. That comparison is the only thing between
two paying customers' data. It is enforced in three different places - a shared
`verify_ownership()` helper, the service layer, and inline comparisons - so a
regression in any one of them is invisible to the others.

Ownership here is not a capability check. Every advertiser holds the same
capability; what differs is which rows are theirs. That distinction is easy to
"fix" wrongly, which is why this journey exists.

## Setup

- Site: `$SITE_URL`
- Advertiser A: `clio` (owns campaign 1)
- Advertiser B: any other advertiser owning campaigns 2, 3, 5

## Steps

### 1. Sign in as advertiser A
- **Action**: `playwright_navigate $SITE_URL/advertiser-dashboard/?autologin=clio`
- **Expect**: portal loads on the overview tab.

### 2. Open A's own campaign
- **Action**: `$SITE_URL/advertiser-dashboard/?tab=campaigns&action=edit&campaign_id=1`
- **Expect**: HTTP 200, the campaign renders, no access-denied notice.
  A must not be locked out of their own data.

### 3. Attempt each campaign A does not own
- **Action**: repeat for `campaign_id=2`, `3`, `5`
- **Expect**: each returns an access-denied notice, and the response body
  contains none of the other advertisers' names.

### 4. Confirm no leakage in the denial
- **Expect**: a denied response must not disclose that the campaign exists, who
  owns it, or its budget. "Not found or access denied" is deliberately ambiguous.

## Pass criteria

- Own campaign: reachable.
- Others' campaigns: denied, with no owner name, amount, or campaign title in the body.

## On failure

Check `verify_ownership()` in the classifieds AJAX handler and the
`absint( $campaign->advertiser_id ) !== absint( $advertiser->id )` comparisons in
the advertiser shortcodes. A comparison that resolves the acting advertiser from
request input rather than the session fails this journey even while looking correct.
