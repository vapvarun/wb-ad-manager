# Soft Test Report — 1.5.0 release candidate

**Date:** 2026-04-19
**Pro:** `1.5.0` HEAD `8a3a19a`
**Free:** `2.8.0` HEAD `9f38b51`
**WordPress:** 6.9.1 (Local by Flywheel)
**Theme:** BuddyX

## Test matrix — 7 passes

### 1. Full Platform mode baseline ✅ PASS
- Portal Overview renders with 3 ad-centric stat cards (Impressions 289780, Clicks 8803, Active Campaigns 1)
- Quick Actions: Create Ad / Post Classified / New Campaign / View Stats
- Active Campaigns table with 1 row (Enterprise Package Campaign)
- Recent Activity feed with 4 entries
- Tabs: Overview, My Ads, Campaigns, Classifieds, Inquiries, Favorites, Following, Link Partnerships, Messages, Membership, Analytics, Share of Voice, Profile
- Console: 0 errors, only jQuery Migrate info log

### 2. Classifieds-only mode end-to-end ✅ PASS
- Portal collapses cleanly — 8 tabs only (Overview, Classifieds, Inquiries, Favorites, Following, Messages, Membership, Profile)
- Stat cards: only "Active Listings: 0" (ad cards hidden)
- Quick Actions: only "Post Classified"
- Active Campaigns section hidden
- Change Mode dialog now reads accurately: "Users post classified listings with featured / bump upgrades. No paid ads, no link partnerships — classifieds-only marketplace."

### REST gating in Classifieds mode ✅ PASS
```
GET /wp-json/wbam-pro/v1/packages     = 404 (packages module off)
GET /wp-json/wbam-pro/v1/classifieds  = 200
GET /wp-json/wbam-pro/v1/reviews      = 200
```
Module-gated permission callback (REST_Permissions::public_read) correctly denies disabled modules.

### 3. Ad submission wizard ✅ PASS
- `/advertiser-dashboard/?tab=ads&action=new` renders 6-step wizard
- Step 1 "Choose Ad Type" — 3 options (Image / HTML / Rich)
- 4 `wbam_pro_ad_form_step_N_after_fields` action slots silent no-ops when no listeners (correct)
- My Ads list renders with stat card + Edit/Resume/Delete buttons (DB query loader refactor working)

### 4. Classifieds archive + single ✅ PASS
- `/classifieds/` loads 16 listings in grid with sort/filter sidebar
- REST GET `/wbam-pro/v1/classifieds/15` returns 15-key payload including new fields from `wbam_pro_rest_prepare_classified_public` filter
- Theme integration clean

### 5. Admin Pro Settings ✅ PASS
- 10 tabs render (General / Ad Rotation / Modules / Credits / Pages / Analytics & Privacy / Classifieds / Geolocation / Emails / License)
- Site Mode card shows "Full Platform"
- Modules tab shows all 15 module toggles with correct Depends/Required chains

### 6. Free plugin admin + ad serving ✅ PASS (with 1 pre-existing notice)
- Free plugin Ad Settings page loads with all form fields
- Front-end home page renders with all Pro + Free scripts enqueued correctly
- ⚠️ Pre-existing notice: `wbam-pro-license` script enqueued before `wbam-toast` handle registered. Not caused by audit; logged for future fix batch.

### 7. Mode flip cycle: Full → Classifieds → Full ✅ PASS
- All REST endpoints return 200 after flip-back (packages, classifieds, reviews, trending)
- Data snapshot identical to pre-flip state
- No new rows, no lost rows, no changed IDs

---

## Data integrity — 4 snapshots taken during soft test

| Table | Count | Hash | Drift |
|---|---:|---|---|
| ad_submissions | 1 | eccbc87e4b5ce2fe28308fd9f2a7baf3 | 0 |
| advertisers | 6 | 9e6d1bf88f20f0a856f2ba1526d67dfc | 0 |
| campaigns | 14 | 2ad8eff1801cdd5bf343bef64f3dcf7b | 0 |
| classifieds | 16 | 997dec9644e30e45fb1b06a5a58531a1 | 0 |
| transactions | 28 | 6298d7b3db03456ca16ef4a3e2ab8891 | 0 |
| packages | 10 | 3227a95526e21fe9e2ae8425bd6ada53 | 0 |

**Zero data drift across all tests.**

---

## PHP debug log review

**Found during soft test:**
- Informational logs: `sanitize_settings: form submitted without _active_tab/_tab_fields contract; falling back to legacy merge.` — expected transitional logging, not an error
- 1 pre-existing notice: `wbam-pro-license` script dep ordering (see soft test #6)

**NOT found:**
- No fatals from any Batch A-E code path
- No new warnings introduced by the audit work
- No escaping / XSS warnings from the new filter or hook additions
- No missing-callback errors from the do_action slots

The 1 pre-existing notice about `wbam-toast` dep ordering is not a release blocker — noted for Batch F.

---

## REST smoke matrix

| Endpoint | Full mode | Classifieds mode | Restored Full |
|---|:---:|:---:|:---:|
| GET /packages | 200 | 404 ✅ | 200 |
| GET /classifieds | 200 | 200 | 200 |
| GET /classifieds/{id} | 200 | 200 | 200 |
| GET /reviews | 200 | 200 | 200 |
| GET /classifieds/trending | 200 | 200 | 200 |

---

## Verdict

**✅ 1.5.0 release candidate ready for tag.**

All audit work (Batch A–E) verified on live site with Free + Pro loaded. Zero regressions. REST gating works as designed. Site Mode switches preserve data and gate UI/API correctly.

### Known non-blockers (tracked for future batches)

1. `wbam-toast` script dep ordering notice (pre-existing)
2. `sanitize_settings` legacy merge fallback log (transitional, informational)
3. 290-entry PHPStan baseline (pre-existing tech debt)
4. Plugin Check 213 PCP-DEEP warnings (`printf` escaping hints — pre-existing, manual audit)

### Action required

- User to delete old `v1.5.0` tag on remote (at old commit `0d9757d`)
- Re-tag at current HEAD `8a3a19a` for the release
- Agent not authorized to delete published tags.

---

**Signed-off:** Soft test complete — 7/7 passes.
