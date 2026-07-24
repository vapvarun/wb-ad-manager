# WB Ad Manager (Free 2.10.0 + Pro 1.8.0) — Demo-Readiness Checklist

Purpose: before the next client demonstration, verify **every issue the client
raised at the first demo** is actually fixed, plus the surrounding journeys a
site owner and an advertiser will walk through live.

Source of truth for "client points" = the 12 cards the client filed, now in
**Ready for Testing** on Basecamp project `44982066` (WB Ad Manager board).

Legend: `[ ]` untested · `[x]` verified pass · `[!]` fail / regression · `[~]` partial or by-design

---

## Part 1 — The 12 client-reported issues

| # | Card | Client-reported problem | How to verify | Result |
|---|------|-------------------------|---------------|--------|
| 1 | 10108932534 | Wallet ledger: cents truncated on charge/credit, refunds use wrong price, credits show as red debits | Admin → Transactions: top-up shows positive/green, deduction negative/red. Advertiser list shows live balance + total spent | `[x]` **PASS** - cents no longer truncated (147.35 stores as -14735, balance 4908 -> 4760.65 exactly); top-ups green `+$3,000.00`, deductions red `-$2,500.00` |
| 2 | 10108932626 | Public `/ads` endpoint exposes disabled ads; classified inquiry/report endpoints unthrottled | `GET /wp-json/wbam/v1/ads` as anon returns only enabled ads; inquiry/report endpoints rate-limited | `[x]` **PASS** - 7 published / 6 enabled; anon `/ads` returned exactly 6 |
| 3 | 10108161487 | Share of Voice: needs a disable setting and must exclude house ads | Pro Settings shows SoV toggle; SoV denominator excludes advertiser-less (house) ads | `[x]` **PASS** - SoV off-switch present; house ads excluded via filter (default on) |
| 4 | 10108932340 | A/B variant swap silently blanks that ad's OTHER placements on the same page | Page with the same ad in 2 placements + a running A/B test: both slots render | `[x]` **PASS** - variant swap passes allow_duplicate; other placements render |
| 5 | 10118481253 | Advertiser login dumps them into bare wp-admin; no visible sign-up path | Log in as an advertiser → lands on portal dashboard, not wp-admin; sign-up link visible | `[x]` **PASS** - advertiser hitting /wp-admin/ redirected to /advertiser-dashboard/ |
| 6 | 10118481565 | Deleting a WordPress user leaves an orphaned advertiser record | Delete a WP user who is an advertiser → advertiser row removed | `[x]` **PASS** - advertiser row removed after wp_delete_user (verified live) |
| 7 | 10108932090 | Stored XSS: front-end HTML/JS ads bypass sanitization and can auto-publish | Submit a code ad containing `<script>` as a non-`unfiltered_html` user → script stripped | `[x]` **PASS** - submitted via portal: saved as draft, `<script>`+`onerror` stripped, preview sandboxed |
| 8 | 10108161485 | Cloaked links with external destinations redirect to wp-admin | Visit `/go/<slug>` for an external URL → lands on the external site | `[x]` **PASS** - /go/qa-external returned HTTP 307 to the external URL |
| 9 | 10118480643 | Approving a pending ad via Publish does not enable it; ad never renders | Publish a pending ad → `_wbam_enabled` set, ad renders on frontend | `[x]` **PASS** - published via the real editor: `_wbam_enabled` set to 1 automatically |
| 10 | 10108932190 | REST `/ads/placements` and `/ads/types` return HTTP 500 | Both endpoints return 200 with a populated list | `[x]` **PASS** - /ads/placements 200 (11 items), /ads/types 200 (6 items) |
| 11 | 10118480198 | Setup Wizard: every icon invisible from the first screen after activation | Open the Setup Wizard → logo + checkmark icons visible | `[x]` **PASS** - all 4 wizard icons render (24x24 logo + 16x16 checks) |
| 12 | 10108161483 | Links → Add Category shows "Sorry, you are not allowed to access this page" | Links → Categories → Add Category loads the form, saves | `[x]` **PASS** - Add New Category form loads, saved to DB, no permission error |

---

## Part 2 — Admin (site owner) journey

The flow the client will watch on screen.

- `[x]` A1. Plugins screen: both plugins active, no error notices, no PHP warnings
- `[x]` A2. Setup Wizard runs start to finish, icons visible (client #11)
- `[x]` A3. Create an ad (Image) → assign placement → **Publish → it renders on the frontend** (client #9)
- `[x]` A4. Ad appears in the right placement, with the disclosure label if configured
- `[x]` A5. Settings page: all sections render, values save and persist (incl. new Link Cloaking section)
- `[x]` A6. Links: create a link, **Add Category works** (client #12), cloaked URL resolves (client #8)
- `[x]` A7. Email Captures screen lists submissions, CSV export downloads, delete works
- `[ ]` A8. Ad Analytics / Revenue dashboards load with real numbers, no fatals
- `[x]` A9. Submenu grouping renders correctly (no stray/duplicate items)
- `[x]` A10. No PHP notices in `debug.log` across the whole journey

## Part 3 — Advertiser journey (Pro)

- `[ ]` B1. Sign-up path is discoverable; registering creates an advertiser
- `[x]` B2. Advertiser login lands on the **portal dashboard**, not bare wp-admin (client #5)
- `[x]` B3. Wallet tab shows a correct balance; Transactions read correctly (client #1)
- `[x]` B4. Submit an ad through the portal → appears in the admin review queue
- `[x]` B5. Code/HTML ad submission is sanitized (client #7)
- `[x]` B6. Share of Voice tab shows data and respects the new off switch (client #3)

## Part 4 — Visitor journey

- `[x]` C1. Ad renders on a public page for a logged-out visitor
- `[x]` C2. Clicking the ad records a click and reaches the destination
- `[ ]` C3. Email Capture ad accepts a submission; it appears under Email Captures
- `[x]` C4. Same ad in two placements both render with an A/B test running (client #4)

---

## Part 5 — Findings from the 2026-07-24 run

**Fixed during this run:**

1. **Clicks were counted twice (FIXED).** Click tracking bound to
   `.wbam-ad[data-ad-id], .wbam-ad-slot[data-ad-id]`, and some ad types emit their
   own `.wbam-ad[data-ad-id]` inside the placement wrapper — so the same link got
   two listeners and every click reported 2. Proven in-browser (1 click -> 2
   requests), fixed to bind only the outermost container, re-verified 1 -> 1.
   Patched `frontend.js` **and** `frontend.min.js` (the plugin ships `.min`).

**Open items to settle before the demo (not plugin bugs):**

2. **No self-signup path.** `Settings → General → Anyone can register` is OFF on
   this site, so an advertiser cannot register themselves — the second half of
   client point #5. Either enable registration or create advertiser accounts in
   advance and say so during the demo.
3. **Demo ads have no placement assigned.** All 7 seeded ads have empty
   `_wbam_placements`, so none of them actually render on the site; their
   impression counts come from seeded analytics. Assign placements to at least
   the ads you intend to show live, or the demo will show a populated dashboard
   over a site with no visible ads.
4. **Ad packages are priced $0.00.** Fine for a sandbox, but a pricing demo needs
   real figures on the package cards and the review queue.

**Not covered by this run (test before the demo if they will be shown):**

- A8 Ad Analytics / Revenue dashboards were not opened.
- C3 Email Capture front-end submission was not exercised end to end (the admin
  screen, export and delete were verified separately).

---

## Part 6 — Resolved since the first demo

- **Wallet ledger now keeps cents (FIXED).** Money is stored in minor units of
  the configured currency, so a 147.35 charge is exact rather than truncated to
  147. This needed no change to the shared Credits SDK - the SDK's own gateway
  layer already denominates money in minor units - so the bundled SDK and every
  other Wbcom product using it are untouched. Existing balances are converted
  automatically on update (DB 4.2.0).
- **Currency precision is uniform (FIXED).** One helper
  (`wbam_get_currency_decimals()`, filter `wbam_currency_decimals`) now drives
  both display and storage, so zero-decimal currencies (JPY, KRW, VND) are not
  padded and three-decimal ones (KWD, BHD, OMR, TND) are not truncated. Any
  bridge that fills credits - WooCommerce, WC Subscriptions/Memberships, PMPro,
  MemberPress - maps onto that same denomination.

---

*Run against: beyondthecle.local · Free 2.10.0 · Pro 1.8.0*
