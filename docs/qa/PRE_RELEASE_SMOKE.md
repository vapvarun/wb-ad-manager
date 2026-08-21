# Pre-Release Smoke — WB Ad Manager (Free + Pro)

**Who runs this:** a QA person, by hand, in a browser. Budget ~90 minutes.
**When:** before every customer-visible tag.
**Agent equivalent:** `AGENT_SMOKE_RUNBOOK.md` (same contracts, machine-executable). Running the agent walk does not replace this for a major; it does for a patch.

Tick every box. A box you did not actually look at is a box that fails.

## Setup (10 min)

- [ ] Local site on the target WP version, Reign active, free + Pro both at the release version
- [ ] `WP_DEBUG`, `WP_DEBUG_LOG` on, `WP_DEBUG_DISPLAY` off; `debug.log` deleted so it starts empty
- [ ] Demo data imported via Tools, plus `bin/seed-qa-playground.php` for messages, reviews and favourites
- [ ] Logins to hand: admin, an advertiser (`techstartup`), a buyer (`qabuyer`)

## Fresh install (10 min)

- [ ] Activating free then Pro produces no output and no debug.log lines
- [ ] Setup wizard completes and both portal pages exist
- [ ] Settings → Pages shows those pages **selected**, not "— Select a page —"
- [ ] Tools → Import Demo Data fills advertisers, ads, campaigns, classifieds, links, packages and analytics

## Anonymous visitor (15 min)

- [ ] Front page renders and an ad appears in its slot
- [ ] `/classifieds/` shows listings with images, prices, badges and working filters
- [ ] A classified detail page renders gallery, seller card, description and reviews
- [ ] **390px:** filters collapse to a button, single column, no horizontal scrolling
- [ ] Reload a few times — a different creative appears (rotation is working)

## Advertiser (20 min)

- [ ] Every portal tab opens without error
- [ ] Only this advertiser's ads, campaigns and analytics are visible
- [ ] Analytics → **Export CSV downloads a file** with their rows only
- [ ] Favourites: clicking the heart removes the card **and** it stays gone after reload
- [ ] Messages: a short message like "OK" renders as a normal bubble, not stacked letters
- [ ] Submit a classified end to end; it appears in the moderation queue

## Admin (20 min)

- [ ] All Ads: Impressions/Clicks **match Ad Analytics** for the same ad
- [ ] Ad Folders: rail counts equal All Ads; drafts and pending are listed and labelled
- [ ] Filters combine — advertiser + tag narrows correctly
- [ ] Classified Reports: the **View** link opens the report
- [ ] Approve a pending submission; it goes live
- [ ] Notices appear **once**, not twice, on Tools and A/B Testing
- [ ] No "Run Setup Wizard" nag after setup is complete

## Settings integrity (10 min)

- [ ] Enable "Delete all data on uninstall" on General, save
- [ ] Save the **Analytics** tab
- [ ] Return to General — the uninstall setting is **still enabled**
- [ ] Repeat the pattern for any other tab pair you touched this cycle

## Dark mode and theme fit (5 min)

- [ ] Toggle the theme's dark mode; portal and classifieds remain readable
- [ ] Form fields are visibly bounded — inputs must not disappear into the card

## Close out

- [ ] `debug.log` reviewed: **no** fatals, warnings, notices or deprecations
- [ ] Every failure filed on Basecamp with a repro, before the tag
- [ ] `docs/qa/.last-smoke-pass.json` written (agent walk) or this checklist attached to the release

## Known-fragile areas

Look harder here; each has broken before:

- **Analytics arithmetic** — raw events versus the daily rollup. Two screens reporting the same metric must agree.
- **Free/Pro boundaries** — anything free does *and* Pro also does. Double-writes and duplicated notices live here.
- **Settings tab contracts** — a tab that lists a boolean it doesn't render will silently clear it.
- **Stored page IDs** — a demo import replaces pages and leaves the options pointing at deleted posts.
- **Theme-dependent CSS** — the plugin must own its own borders and contrast, not inherit them and hope.
