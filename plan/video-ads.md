# Plan: Video Ads — Standalone (VAST/VPAID) + In-Video Timestamp Injection

> **⚠️ SUPERSEDED — see split plans below**
>
> Per the architectural principle "ads plugin doesn't build video services," this plan's scope was split into two focused plans across the right plugins:
>
> - [`../../wb-ad-manager-pro/plan/1.7.0-in-video-ads-with-mediashield.md`](../../wb-ad-manager-pro/plan/1.7.0-in-video-ads-with-mediashield.md) — wb-ads side: video/audio ad creative types, VAST 4.x parsing, REST endpoints (`GET /wb-ads/v1/creative` + `POST /wb-ads/v1/event`), CPM-V/CPM-A pricing, frequency capping, A/B testing, Pro management UX. **Zero player code.**
> - [`../../mediashield-pro/plan/1.1.0-ads-on-videos.md`](../../mediashield-pro/plan/1.1.0-ads-on-videos.md) — mediashield side: per-video ad-slot admin UI (timeline scrubber), player runtime JS (intercept timestamps, pause/resume content, skip button countdown, quartile detection), cross-analytics writeback. **Zero ad-engine code.**
>
> The original scope below described wb-ads building player adapters for video.js / Plyr / JW Player / WP video block / mediashield. That was wrong — generic player adapters belong in player-owner plugins, not in wb-ads. Future video-player plugins that want to consume wb-ads creatives integrate via the documented REST contract, the same way mediashield does.
>
> The original file content is preserved below for git history but should not drive new work. Use the split plans above.
>
> ---

# Plan: Video Ads — Standalone (VAST/VPAID) + In-Video Timestamp Injection

> **Status:** Draft for review
> **Target releases:** FREE 2.12.0 + PRO 1.7.0 (paired — ship together)
> **Estimate:** 2 sprints (~3-4 weeks AI-accelerated)
> **Owner:** Varun
> **Roadmap reference:** [`../../wb-ad-manager-pro/plan/roadmap-5-year.md`](../../wb-ad-manager-pro/plan/roadmap-5-year.md) — Pillar 2 lead bet
>
> **⚠️ Dual driver:** This is **both** a roadmap-priority feature (per the strategic analysis) **AND a client-funded project**. The in-video injection capability has a paying client engagement attached. That changes how we run this sprint:
>
> - **Client requirements take precedence** over our speculative scope below. Before sprint start, run a kickoff session with the client to capture their exact spec — supported video sources, timestamp UX expectations, branding requirements, analytics needs, whether they need the standalone VAST capability at all or just in-video.
> - **Client gets the v1.** Anything in this plan they don't need ships in 2.12.x as a follow-up — don't bloat v1 with our speculative additions if it delays their delivery.
> - **Build the FOUNDATION their way, generalize via filters.** If their use case needs a specific player adapter or a particular tracking pixel, ship it as the canonical implementation; expose the same surface to all customers via filter so the next 100 customers benefit.
> - **Don't private-fork.** Everything we build for the client ships in the public release. The client gets the implementation; the community gets the same plugin. No "client edition" branch.
> - **Document client commitments separately.** Once spec is locked, create `plan/video-ads-client-spec.md` (gitignored if needed for confidentiality, otherwise committed) with the client's exact deliverables and dates. This plan stays the public-facing engineering plan.
> - **Architectural hooks are designed once.** The hook surface for in-video injection is part of the 5-year foundation — once shipped, we cannot rename it without `do_action_deprecated()`. Design hooks for the GENERAL case, not the client-specific case, even if the first consumer is the client.

---

## Why this is the next big bet (vs. header bidding, native ads, agency pack)

Three reasons, in order of importance:

1. **Video ad spend is the fastest-growing ad category** (~25% YoY in 2026 vs ~3% for display). A site monetizing video earns 5–10× the CPM of an equivalent display impression. Moving customers' video content from "free distribution" to "monetized inventory" is a step-change in advertiser-portal revenue.

2. **In-video timestamp injection has no direct WordPress competitor.** Adsterra and Adlinr offer this *if* you join their network (and surrender control + 30-50% margin). YouTube does it (and takes ~45% margin). Self-hosted publishers running tutorials, courses, news clips, or product demos have **no plug-and-play option** to monetize their own videos without giving up sovereignty. We are uniquely positioned: we already have the advertiser portal, wallet, and rotation engine. Adding the in-video player layer turns us into the answer.

3. **It compounds with everything we just built.** Consent Mode v2 (2.11.0) already gates ad rendering — video reuses it. Gutenberg blocks (2.10.0) get a `wb-ads/video-ad` block for free. CWV-safe slot reservation (2.11.0) extends naturally to video aspect-ratio reservation. We're not building on a shaky foundation; we're cashing in on it.

---

## Goal

Ship **two video-ad capabilities** in a single paired release:

### A. Standalone video ads (VAST 4.x / VPAID 2.0)

Customer adds a video ad as an inventory item. Renders inline as a self-contained video player with click-through, skip-after-N-seconds, and quartile tracking. Compatible with industry VAST tags (so customers can also point us at external networks like SpotX, Magnite).

### B. In-video timestamp injection ⭐ DIFFERENTIATOR

Customer points us at one of their existing videos (mp4, webm, HLS, or 3rd-party embed where supported). Defines insertion points: pre-roll (before video starts), mid-roll (at any timestamp), post-roll (after video ends). Player intercepts the customer's video at those points, plays our ad creative, returns to the customer's content. Tracks impression on view-start + quartiles + completion + click-through. Configurable skip-after.

This is the headline feature. **Nothing else in the WordPress plugin ecosystem ships this self-serve.**

---

## Scope

### IN scope: FREE 2.12.0

| Component | Detail |
|---|---|
| **Video ad type** | Existing `video` ad type becomes spec-compliant. Stores: video URL (or attachment ID), VAST tag URL (alternative), duration, dimensions, click-through URL, skip-after seconds. |
| **Video player infra** | New `assets/js/wb-ads-video-player.js` — small wrapper around the HTML5 `<video>` element. ≤15 KB gzipped, no jQuery, no external player library by default. Supports mp4/webm/HLS (via native browser HLS on Safari/iOS, hls.js auto-loaded only if needed). |
| **VAST 4.x parsing** | Server-side VAST XML parser in PHP (new `Frontend\Video\VAST_Parser` class). Resolves wrapper chains up to 5 levels (per IAB spec). Extracts MediaFile URLs, tracking events, click-through. |
| **VPAID 2.0 support** | Loaded on-demand only when the VAST response declares VPAID. Sandboxed in iframe (security). |
| **Quartile tracking** | Fire impression at 0% (start), 25%, 50%, 75%, 100% (complete). Click-through tracked. All emit `wbam_ad_event` action with event type so existing analytics persists. |
| **Standalone block** | New Gutenberg block `wb-ads/video-ad` — single-ad picker for video-type ads, ServerSideRender preview. Builds on the block infra from 2.10.0. |
| **Standalone shortcode** | `[wbam_video_ad id="X"]` — same render as block. |
| **In-video registration** | New post meta `_wbam_in_video_targets` on `wbam-ad` posts. Stores array of `{ video_source: "post:123" | "url:https://..." | "embed:youtube:abc", timestamp_seconds: 30, position: "mid_roll" }`. |
| **In-video player JS** | `assets/js/wb-ads-in-video.js` — IntersectionObserver finds `<video>` elements on page; checks if any have configured ad insertions; intercepts `timeupdate` events and triggers ad playback at configured timestamps. |
| **Player integrations** | Native HTML5 `<video>` (always supported). Auto-detect and integrate with: video.js, Plyr, JW Player. WordPress core video block. |
| **CWV reservation** | Video ad slot reserves aspect-ratio dimensions (extends 2.11.0 CLS-safe rendering). |
| **Consent gate** | Video ads inherit `_wbam_requires_consent` = true by default (extends 2.11.0 Consent Mode v2). Denied state shows poster image + "Allow cookies to play this ad" CTA. |
| **Documentation** | `docs/website/video-ads.md` — both standalone and in-video, with screenshots and player-compatibility matrix. |

### IN scope: PRO 1.7.0

| Component | Detail |
|---|---|
| **Video creative submission UI** | Advertiser portal: new "Video" tab. Upload mp4/webm OR paste VAST tag URL. Validation: file size limit, duration limit, dimensions, codec check. |
| **In-video editor UI** | Admin screen `Tools → In-Video Ad Editor`. Lists publisher's videos (from media library + WordPress video block usage on posts). Pick video → timeline scrubber → click to add insertion point → assign ad creative from inventory. Visual timeline UI. |
| **In-video editor for advertisers** | Advertisers can request in-video placement on the publisher's videos (subject to publisher approval). Goes through existing approval flow. |
| **Video ad analytics** | Extends `wbam_pro_analytics` with quartile-level breakdowns. New analytics dashboard tab: "Video Performance" with completion rate, skip rate, average view duration, in-video position attribution (which timestamp slots perform best). |
| **VAST tag library** | Pre-configured VAST tag templates for common networks (SpotX, Magnite, AdsWizz). Customer picks template → fills in network-specific params → done. Avoids each customer reinventing the tag URL. |
| **Pricing model: CPM-V** | New campaign pricing model `cpm_v` (CPM-Video) — billed per 1,000 *completed* video views, not impressions. Industry standard for video. |
| **Wallet integration** | Video creatives consume credits from advertiser wallet on completion event (integrates with existing Credits SDK migration in 1.8.0). |

### OUT of scope (deferred)

- **OVP (Online Video Platform) integration** — Brightcove, Vimeo OTT, Wistia. Big-publisher use case; defer until 5+ customers ask.
- **Live-stream ad insertion** — DAI (Dynamic Ad Insertion). Different infrastructure entirely.
- **VAST 4.2 ad pods** (multiple ads in sequence) — supported by spec; defer first version, add in 1.8.x.
- **MOAT / IAS / DoubleVerify viewability vendors** — enterprise-only requirement; not in v1.
- **AI-generated video ads** — separate plan (1.11.0). The infrastructure here makes it possible later.
- **Companion banner ads** that display alongside video — defer to v1.8.x.
- **Skippable interactive ads** (VPAID 2.0 interactive features beyond skip) — VPAID 2.0 spec is huge; we ship the 80% subset that 99% of advertisers use.
- **Server-side ad insertion (SSAI)** — operates at video transcoding layer, not a plugin concern. We're a publisher tool, not a CDN.
- **YouTube embed support for in-video injection** — YouTube blocks 3rd-party ad insertion in their embed (per their TOS). Show a clear error message in the editor when a YouTube embed is detected.

---

## Architecture

### Standalone video ad rendering

```
Frontend\Renderer::render_ad( $ad )
  └─ if ad_type === 'video':
       └─ Video\Renderer::render( $ad )
            ├─ resolve_video_source()  →  direct URL | VAST_Parser->resolve($vast_url)
            ├─ render slot markup with reserved aspect-ratio
            ├─ enqueue wb-ads-video-player.js (lazy via 2.11.0 lazy-load infra)
            └─ emit <div data-wbam-video-config="{...}"> placeholder
                 └─ JS swaps in <video> element on viewport entry
```

### In-video timestamp injection rendering

```
Page load:
  wb-ads-in-video.js scans DOM for <video> elements
  For each <video>:
    └─ resolve identity (post ID, URL, or embed source)
    └─ REST: GET /wb-ads/v1/in-video/insertions?video_source={id}
        ←  [{ ad_id, timestamp_seconds, position, skip_after }, ...]
    └─ attach timeupdate listener
       At configured timestamp:
         ├─ pause customer video, remember currentTime
         ├─ overlay ad player (same player as standalone)
         ├─ on ad complete (or skip):
         │     └─ remove overlay, resume customer video at remembered time
         ├─ fire wbam_ad_event quartile tracking (impression, q25, q50, q75, complete)
         └─ if skip pressed: fire wbam_ad_event 'skip' + resume immediately
```

### REST surface (FREE)

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/wb-ads/v1/video-ad/{id}` | Resolve video ad config (URL, dimensions, click-through, skip_after, vast_tag_url if applicable) |
| `GET` | `/wb-ads/v1/in-video/insertions` | Given `?video_source=post:123` (or URL/embed), return all configured insertions for that source |
| `POST` | `/wb-ads/v1/in-video/event` | Fire quartile/click/skip events. Authenticated by nonce for logged-in users; rate-limited for anonymous |

### REST surface (PRO)

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/wbam-pro/v1/video-creatives` | List advertiser's video creatives |
| `POST` | `/wbam-pro/v1/video-creatives` | Submit new video creative (file upload OR VAST tag) |
| `GET` | `/wbam-pro/v1/in-video/editor/videos` | List publisher's videos (admin-only) |
| `POST` | `/wbam-pro/v1/in-video/insertions` | Create insertion (admin pick OR advertiser request flow) |
| `PATCH` | `/wbam-pro/v1/in-video/insertions/{id}` | Update / approve / reject |

### Database schema additions

```sql
-- FREE: extend existing wbam-ad post type with new meta
-- _wbam_video_url           (string)
-- _wbam_video_vast_tag_url  (string, alternative)
-- _wbam_video_dimensions    (W x H)
-- _wbam_video_skip_after    (int seconds, 0 = no skip)
-- _wbam_in_video_targets    (serialized array of insertion configs)

-- PRO: new dedicated table for in-video insertions (vs. post meta — needs FK + status workflow)
CREATE TABLE {wp_prefix}wbam_pro_in_video_insertions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ad_id BIGINT UNSIGNED NOT NULL,
    video_source_type ENUM('post', 'url', 'embed') NOT NULL,
    video_source_id VARCHAR(255) NOT NULL,
    timestamp_seconds INT UNSIGNED NOT NULL,
    position ENUM('pre_roll', 'mid_roll', 'post_roll') NOT NULL,
    skip_after INT UNSIGNED DEFAULT 5,
    status ENUM('pending', 'approved', 'rejected', 'paused') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ad_id (ad_id),
    KEY video_source (video_source_type, video_source_id),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Hook ownership

Per `free-pro-architecture-contract.md` Part C:

- `wbam_video_ad_played` — FREE fires when a video ad starts playing
- `wbam_video_ad_quartile` — FREE fires at 25/50/75/100%
- `wbam_video_ad_skipped` — FREE fires when user skips
- `wbam_video_ad_clicked` — FREE fires on click-through
- `wbam_pro_in_video_insertion_approved` — PRO fires after admin approval
- `wbam_pro_in_video_insertion_rejected` — PRO fires after rejection
- `wbam_pro_video_creative_submitted` — PRO fires when advertiser submits

### Architecture-checks invariants (extend `bin/architecture-checks.sh`)

- Video ad rendering happens in `Frontend\Video\Renderer` only (FREE) — PRO never directly renders video
- VAST parsing happens in `Frontend\Video\VAST_Parser` only (FREE) — PRO consumes via service locator
- The `wbam_pro_in_video_insertions` table is owned by PRO; FREE never writes to it (queries via REST only)

---

## Phases

### Phase 1 — Standalone video ads — 1 sprint (AI-accelerated)

1. **FREE:** `Frontend\Video\Renderer` + `Frontend\Video\VAST_Parser` classes. Direct mp4/webm playback first, VAST parsing second.
2. **FREE:** `assets/js/wb-ads-video-player.js` — HTML5 `<video>` wrapper with quartile tracking and skip-after.
3. **FREE:** Video ad type fields in admin `wbam-ad` edit screen (video URL or VAST tag, dimensions, click-through, skip after).
4. **FREE:** REST endpoints `/wb-ads/v1/video-ad/{id}` and `/wb-ads/v1/video-ad/event`.
5. **FREE:** Block `wb-ads/video-ad` + shortcode `[wbam_video_ad]`.
6. **PRO:** Advertiser portal video submission UI (file upload + VAST tag option).
7. **PRO:** VAST tag library (5 pre-configured templates).
8. **PRO:** Wallet/credit integration — `cpm_v` pricing model, deduct on completion event.
9. **PRO:** Video Performance analytics dashboard tab.
10. **Acceptance:** PHPUnit + browser test — render a video ad, fire quartile events, verify analytics + wallet debit.

### Phase 2 — In-video timestamp injection — 1 sprint

11. **FREE:** `assets/js/wb-ads-in-video.js` — `<video>` discovery + REST insertion lookup + `timeupdate` interception.
12. **FREE:** Player integrations (HTML5 native, video.js, Plyr, JW Player, WP video block) — feature-detect, attach.
13. **FREE:** REST `/wb-ads/v1/in-video/insertions`.
14. **FREE:** YouTube embed detection → user-friendly "YouTube blocks 3rd-party injections — try a different video source" error in editor.
15. **PRO:** `wbam_pro_in_video_insertions` table + Installer migration.
16. **PRO:** Admin In-Video Editor UI (timeline scrubber, video picker, ad picker, save).
17. **PRO:** Advertiser-side flow: request in-video placement on publisher's videos → publisher approves/rejects.
18. **PRO:** Email notifications (extend `Email_Notifications`): "Your in-video ad request was approved/rejected".
19. **Browser-verify on real videos:** mp4 in WP video block, video.js gallery, Plyr embed, JW Player. Mobile (390px) + desktop. Quartile events fire correctly. Skip works. Resume position correct.
20. **Documentation + manifest refresh.**

---

## Acceptance criteria

| # | Criterion | Test |
|---|---|---|
| 1 | Standalone video ad renders inline with playable video | Browser verify |
| 2 | VAST 4.x tag URL renders the resolved creative | Browser verify w/ public test VAST tag (e.g., Google IMA sample) |
| 3 | Quartile events (0/25/50/75/100%) fire at correct timestamps | Browser verify with throttled playback |
| 4 | Skip-after-N-seconds button appears and works | Browser verify |
| 5 | Click-through opens advertiser URL + tracks event | Browser verify |
| 6 | In-video injection plays at configured timestamp | Browser verify on a 3-min video with insertions at 30s + 90s |
| 7 | Resume position is correct after ad completes | Browser verify |
| 8 | Resume position is correct after user skips ad | Browser verify |
| 9 | Works with native HTML5 video, video.js, Plyr, JW Player, WP video block | Browser verify each |
| 10 | YouTube embed shows "not supported" error in editor | Browser verify |
| 11 | Quartile events deduct credits via Credits SDK on completion (PRO) | PHPUnit |
| 12 | CPM-V campaign pricing model bills correctly | PHPUnit |
| 13 | Mobile (390px) playback works (autoplay-muted; per browser policy) | Browser verify |
| 14 | Consent denied → poster + CTA, no video load | Browser verify |
| 15 | Lighthouse perf ≥ 90 on demo page with 5 video ads + 1 in-video insertion | Lighthouse CI |
| 16 | Architecture invariants pass (Video\Renderer is FREE-only, in-video table is PRO-only) | `composer arch-checks` |
| 17 | `composer verify` clean both repos | Pre-push gate |
| 18 | RTL stylesheets present | Build verification |
| 19 | Manifest refreshed (new endpoints, hooks, table, services) | `/wp-plugin-onboard --refresh` |

---

## Risks & mitigations

| Risk | Mitigation |
|---|---|
| HLS support without bundling hls.js bloats first-paint | hls.js loaded on-demand only when an HLS stream is detected. Native HLS on Safari/iOS path doesn't trigger the load. |
| VAST wrapper chains can have arbitrary depth → DoS via infinite redirects | Cap at 5 levels (IAB recommendation), with explicit error |
| VPAID 2.0 ads are JavaScript executing in iframe — security risk | Sandboxed iframe with `sandbox="allow-scripts"` only. No `allow-same-origin`. Served from a dedicated subdomain in production. |
| Player integration matrix is large (5 supported players × Multi-version) | Each integration is a separate adapter class with its own test. New player support is additive — `wb_ads_register_video_player_adapter` filter. Customers can ship custom adapters for niche players without forking us. |
| `timeupdate` events fire on a coarse interval (~250ms) → ad triggers slightly off | Acceptable. We trigger when `currentTime >= configured_timestamp` — slight delay is negligible for ad UX. |
| Customer videos with a configured insertion get pre-rolled even if the page is in an iframe / preview / RSS feed → wrong context | Detect document context (`document.body.classList.contains('wbam-preview')` or similar) and disable in-video for non-public contexts. |
| YouTube blocks 3rd-party injection per TOS — customer expects it to work | Detect YouTube embed in editor, show explicit "YouTube embeds cannot be modified — host the video yourself for in-video ads" message. Document why. |
| Existing `video` ad type users have data in old format | Migration in `Installer` reads old shape, writes new meta keys. Idempotent. Old keys retained for one major version. |
| Mobile autoplay policy varies (Safari iOS especially restrictive) | Default autoplay=muted; if browser blocks, show "Click to play ad" overlay. Documented per-browser behavior. |
| Quartile tracking + REST event POSTs spam analytics on bot-heavy sites | Rate limit at REST middleware (existing `wbam_pro_security` infra). Anonymous events capped at 100/min/IP. |
| In-video editor UX complexity (timeline scrubber on touch) | Desktop-first for v1. Mobile editing is hard; defer to a follow-up. Most publishers manage from desktop anyway. |

---

## Why this is shippable in ~2 sprints (AI-accelerated)

Per-task estimates with AI assistance:
- VAST parser: ~4 hours (well-documented IAB spec, parser is mostly XML traversal)
- HTML5 video wrapper: ~6 hours
- VPAID iframe sandbox: ~4 hours
- Quartile tracking + REST events: ~3 hours (mirrors existing analytics pattern)
- In-video player JS (timeupdate intercept + resume): ~6 hours
- Player adapters (5): ~12 hours total (~2.5h each)
- In-video editor UI: ~8 hours
- DB table + migrations: ~2 hours (we have the pattern)
- Block + shortcode: ~3 hours (block infra exists from 2.10.0)
- Browser verification matrix: ~6 hours
- Documentation: ~4 hours

Total: ~58 hours of focused work. Two sprints with buffer = realistic.

---

## Definition of done

- [ ] Standalone video ads (mp4, webm, HLS, VAST 4.x, VPAID 2.0)
- [ ] In-video injection works on native HTML5, video.js, Plyr, JW Player, WP core video block
- [ ] Block + shortcode parity (per 2.10.0 contract)
- [ ] CWV-safe slot reservation (per 2.11.0 contract)
- [ ] Consent gate (per 2.11.0 contract)
- [ ] PRO: video creative submission, VAST tag library, in-video editor
- [ ] PRO: CPM-V pricing model + Credits SDK integration
- [ ] PRO: Video Performance analytics dashboard
- [ ] Email notifications: in-video request approved/rejected
- [ ] All 19 acceptance criteria green
- [ ] `composer verify` clean (both repos)
- [ ] Manifest refreshed (new entries in REST, hooks, services, tables)
- [ ] CLAUDE.md sections updated (Video Ads sections in both)
- [ ] `docs/website/video-ads.md` published
- [ ] Roadmap entry status changes from "Planned" to "Done" with commit refs

---

## Open questions

1. **Default skip-after value.** YouTube uses 5s, IAB recommends "after 5s OR 25% complete, whichever first." Recommendation: ship with `skip_after = 5` (industry default), filterable via `wb_ads_video_skip_after_default`.
2. **Should standalone video ads autoplay?** Recommend `autoplay="muted"` by default (browsers allow this), with admin toggle to disable. Customer can opt out per ad.
3. **VAST tag library — which networks first?** Recommend the 5 with the most documented WP-customer usage: SpotX, Magnite, AdsWizz, Google IMA (sample tags), Tremor. Customers can add custom via filter.
4. **In-video on RSS/AMP feeds?** Detect and skip — these contexts don't have a JavaScript runtime that can run our player. Do not attempt.

## Client-project intake — must complete before sprint starts

Before we begin coding, we need answers from the client to the following. These determine actual scope vs the speculative scope above. Capture in `plan/video-ads-client-spec.md` once the kickoff happens.

| # | Question | Why it matters |
|---|---|---|
| 1 | What video player(s) does the client use? (HTML5 native, video.js, Plyr, JW Player, custom?) | Determines which adapter ships v1; others slide to follow-up |
| 2 | What video sources? Self-hosted mp4, HLS, Vimeo embed, YouTube, mixed? | YouTube blocks 3rd-party injection — client must know the limitation |
| 3 | Pre-roll, mid-roll, post-roll, or all three? | Mid-roll is the hardest UX (resume position, scrub-back behavior) |
| 4 | Skippable, non-skippable, or both? Default skip-after seconds? | Skippable changes UX significantly |
| 5 | Click-through behavior — same tab, new tab, native player overlay? | Affects player adapter design |
| 6 | What ad formats? Direct video file (mp4), VAST tag (point at network), or both? | If "VAST tag only," we don't need the file-upload path in v1 |
| 7 | What analytics does the client need to see? Quartiles, completion rate, skip rate, click-through, all of the above? | Determines whether the existing analytics dashboard is sufficient or needs new fields |
| 8 | Branding — does the player need a branded skin, custom CTA text, custom skip button? | If yes, we need a theming layer; if no, ship default UX |
| 9 | What is the client's WordPress version + PHP version? | Confirms our PHP 7.4 / WP 6.7 minimums match their stack |
| 10 | What is the client's delivery deadline? | Drives sprint cadence + scope cuts |
| 11 | Does the client need standalone VAST video ads (separate from in-video)? | If they only need in-video, we can DEFER standalone to 2.12.1 |
| 12 | Will the client review the plan before we start coding? | Strongly recommend yes — surfaces #1-11 ambiguities upfront |
| 13 | NDA / confidentiality on the client's name in commit messages and docs? | Determines whether `plan/video-ads-client-spec.md` is committed or kept local |

Don't start the sprint until these have answers. A 2-sprint estimate with unknowns becomes a 4-sprint reality.
