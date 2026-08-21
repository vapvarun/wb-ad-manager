# Agent Smoke Runbook — WB Ad Manager (Free + Pro)

**Audience:** a browser-capable agent (Claude Sonnet or equivalent) with Playwright MCP + WP-CLI Bash access, OR a human QA person with the same access.

Each C/E step is a **customer contract**, not a click script: what the feature promises and what "working" looks like. Read the code, pick the mechanism, verify the contract — and notice bugs we did not pre-imagine.

**D (regression guards) is different.** Every row is a real incident with a real repro. The fixture IS the contract. Do not paraphrase these.

## Global preconditions

- Free plugin path: `wb-ads-rotator-with-split-test` · Pro: `wb-ad-manager-pro`
- Site: `http://ads.local` · WP: `/Users/varundubey/Local Sites/ads/app/public`
- WP-CLI: `wp --path="/Users/varundubey/Local Sites/ads/app/public" <cmd>`
- Auto-login: `?autologin=1` (admin) or `?autologin=<user_login>`
- Version constants: `WBAM_VERSION` (free), `WBAM_PRO_VERSION` (pro) — lockstep
- Seeder for fixtures: `php wp-content/plugins/wb-ad-manager-pro/bin/seed-qa-playground.php`

> **Auto-login gotcha:** the mu-plugin must switch users even when someone is already signed in. A version that returns early on `is_user_logged_in()` silently leaves you as the previous user, and every capability check then looks like a permission bug. Confirm the active user before trusting any role-scoped result.

## Output contract

Write exactly one JSON file to `docs/qa/.last-smoke-pass.json`. Field shape is owned by the `wp-plugin-smoke` skill — do not invent fields.

## Debug log protocol

Enable `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY=false`. Baseline the byte count, diff after **every** section into `debug_log_issues[]`. Any new fatal or warning fails the release.

Deprecations count. PHP 8.1+ `Passing null to parameter` notices are how `esc_url(null)` reached customers in 3.1.0 — see D11.

## Fixture cleanup (before every walk)

```bash
wp --path="$WP" eval '
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->prefix}wbam_analytics");
foreach ( get_posts( array( "post_type"=>"wbam-ad","post_status"=>"any","posts_per_page"=>-1,"fields"=>"ids","s"=>"Smoke " ) ) as $id ) { wp_delete_post( $id, true ); }
echo "fixtures cleaned\n";'
```

---

## A — Fresh install

**A1 — Activation is silent.** Activate free then Pro on a clean DB. No output, no debug.log entries during either activation request.

**A2 — Schema is in place.** 37 `wp_wbam_*` tables exist. `wbam_db_version` and `wbam_pro_db_version` are set.

**A3 — Setup wizard completes.** The Pro wizard renders, "Full Platform" is selectable, and finishing writes `wbam_pro_setup_complete`. Both portal pages are created and their IDs land in `wbam_page_advertiser_dashboard` / `wbam_page_classifieds`.

**A4 — Demo import populates every subsystem.** Tools → Import Demo Data (confirm modal, not a native `confirm()`). Expect advertisers, ads, campaigns, classifieds, links, packages, A/B tests and daily analytics rows — not just posts.

**A5 — Lockstep.** `WBAM_VERSION === WBAM_PRO_VERSION`.

---

## B — Upgrade from previous version

**B1 — Migration is silent, data intact.** Upgrade from the prior tag with real data present. No debug.log entries during the activation request; existing ads still render and their analytics totals are unchanged.

**B2 — Backfills drain.** One-time backfills must complete rather than spin. Seed 250+ ads, run the routine, confirm it finishes and sets its completion flag. See D7 — the first attempt at batching this migrated nothing and burned 20,266 queries.

---

## C — Core customer flows

Personas: **Anonymous → Advertiser → Admin**. Desktop 1280px and mobile 390px where the UI differs. Verify the UI *and* the server-side effect — "looks right, didn't save" is a real failure mode here.

**C.anon.home** — front page renders, an ad serves in its slot.

**C.anon.classifieds** — `/classifieds/` lists listings with images, prices, badges, filters and category counts. At 390px the filter panel collapses to a button and the page must not scroll horizontally.

**C.anon.single** — a classified detail page renders gallery, price, seller card, description, reviews and the report control.

**C.anon.tracking** — ad delivery records exactly one impression per page view, with correct placement and device. Rotation serves different creatives across repeated views. Logged-in users are excluded by default; verify logged-out.

**C.advertiser.portal** — every portal tab renders for a portal-only advertiser: overview, ads, campaigns, classifieds, inquiries, favorites, following, links, messages, wallet, membership, analytics, share-of-voice, profile.

**C.advertiser.scoping** — an advertiser sees only their own ads, campaigns and analytics. Try to reach another advertiser's data by editing IDs in the query string; it must be refused server-side.

**C.advertiser.export** — the Analytics CSV export downloads with `text/csv` and a filename header, contains only that advertiser's rows, and rejects unauthenticated requests.

**C.admin.ads** — All Ads lists every status, and Impressions/Clicks agree with Ad Analytics for the same ad.

**C.admin.folders** — Ad Folders rail is built from real advertiser/campaign links plus free-form tags; counts match the list; filters AND together; bulk actions appear only inside a folder scope.

**C.admin.moderation** — a pending submission can be approved and a reported classified opened from the Reports list.

**C.admin.settings** — each Settings tab saves without disturbing another tab's values. See D9.

---

## D — Known-regression guards

Every row is a shipped bug. Keep the exact fixture.

**D1 — Clicks must not double-count (Basecamp 10213488578).**
With Pro active, POST `wbam_track_click` once. Expect **exactly 1** row in `wp_wbam_analytics`, not 2. Free's click handler must stay behind the `defined( 'WBAM_PRO_VERSION' )` guard that its impression path already uses.

**D2 — All Ads totals survive aggregation (10213490252).**
Insert impressions dated older than `WBAM_DEFAULT_AGGREGATE_AFTER_DAYS`, run `wp cron event run wbam_pro_daily_aggregation`, reload All Ads. The column must still show the total — aggregation deletes the raw rows, so a raw-only count silently decays to 0 while Ad Analytics keeps reporting lifetime figures.

**D3 — Setup nag clears on a Pro site (10213493286).**
With `wbam_pro_setup_complete=1` and `wbam_setup_complete` unset, no "Run Setup Wizard" notice on any admin screen.

**D4 — One `wp-header-end` per screen (10213492076).**
Every plugin admin screen must emit exactly **one** `<hr class="wp-header-end">`. Two makes WP core's `insertAfter` clone every notice. Assert the count in served HTML, not the rendered DOM.

**D5 — Classified Reports "View" opens (10181344997).**
The View link must resolve 200. It is registered under the `wbam-classifieds` parent, so its URL is `admin.php?page=…`; an `edit.php?post_type=…` URL 403s.

**D6 — Folders show unpublished ads (10213494699).**
With drafts and pending ads present, Ad Folders counts must equal All Ads, and non-published rows must carry their status label.

**D7 — Video type-meta backfill drains (10217358685).**
With 250+ ads that have no resolvable type, the backfill must complete and set its flag. `sync_type_meta()` deletes the key for typeless ads, so a naive self-draining loop never terminates.

**D8 — Portal Export CSV is wired (10181166632).**
The control must be a link to a real endpoint. A scriptless `<button>` with no handler is how this shipped.

**D9 — A tab may only own booleans it renders (10217449515).**
Enable "Delete all data on uninstall" on General, then save Analytics. The flag must remain **true**. Any tab listing a boolean in `_tab_fields` without rendering its checkbox will silently clear it.

**D10 — Portal page IDs self-heal (10217449441).**
Point `wbam_page_advertiser_dashboard` at a deleted post, load Settings → Pages. The dropdown must select the real page and repair the option, not offer only "Create Page".

**D11 — No `esc_url(null)` on orphaned reports.**
A report whose classified was deleted must render as plain text, not `href=""`, and must not emit a PHP 8.1+ deprecation.

**D12 — Advertisers can reach `admin-post.php`.**
`redirect_advertiser_from_admin()` must not bounce `admin-post.php`; portal downloads run through it. It must still redirect real wp-admin screens.

---

## E — Pro extensions

**E1 — Classifieds marketplace** — submit, upgrade (featured/bump), inquire, review, report.
**E2 — Credits and billing** — top-up via the SDK, spend on an ad, ledger reflects both. CPC spend must charge once per click.
**E3 — Campaigns and A/B testing** — split assignment holds, winner selection reports a credible result.
**E4 — Links and partnerships** — directory, keywords, health check, partnership inquiry form.
**E5 — Community integrations** — with BuddyPress active, activity placements appear; with it inactive, the plugin degrades quietly.

---

## F — Cross-browser spot pass

Chrome, Firefox, Safari iOS: classifieds list, classified detail, advertiser portal. Dark mode included — the portal's form-control contrast is theme-dependent and has regressed before.

---

## G — Post-release monitoring (first 24h)

- Support inbox for "ads not showing" or "stats went to zero"
- Error logs for `wbam_` fatals
- One live site's Ad Analytics vs All Ads for the same ad — they must agree
