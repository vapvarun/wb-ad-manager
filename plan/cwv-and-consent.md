# Plan: Core Web Vitals + Consent Mode v2 — WB Ads Rotator (FREE)

> **Status:** Draft for review
> **Target release:** FREE 2.11.0 (after Gutenberg blocks ships)
> **Estimate:** 2 focused sprints (~2-3 weeks of build + verify)
> **Owner:** Varun

---

## Problem statement

Two compounding problems block us from EU markets and Google AdSense customers in 2026:

### 1. Compliance — Google Consent Mode v2

Since March 2024, Google **mandates** Consent Mode v2 signals (`ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`) for any site serving AdSense or Google Ad Manager to EU traffic. Sites that don't comply have their reach in EEA + UK + Switzerland progressively reduced. Most major WordPress ad plugins integrate with the popular CMPs (Cookiebot, Complianz, CookieYes, Iubenda, OneTrust). **We don't.** A customer who plugs us in and serves AdSense to EU traffic is non-compliant out of the box. That's a defensible lawsuit and a lost market.

### 2. Performance — Core Web Vitals

Our ads render synchronously in the page response, with no slot-dimension reservation, no lazy loading, and no LCP/CLS discipline. On a content-heavy page with 5 image ads, real-world numbers from current customers:
- **CLS ≥ 0.25** (Google "Poor" threshold — visible page jumping as ads load)
- **LCP penalty** of 800ms-1.5s when an above-fold ad loads slow
- **Render-blocking** when ad images are uncompressed

Google ranking penalties for "Poor" CWV are direct and measurable. Customers who care about SEO churn off us once they wire up Search Console.

---

## Goal

Ads serve **only after consent is granted** (or in a non-personalized degraded mode), **lazy-load below the fold**, and **reserve their slot dimensions before image load** to eliminate CLS. The plugin works with the top 5 WordPress CMPs out of the box and exposes a single filter for any custom CMP.

---

## Scope

### IN scope: Consent Mode v2

| Component | Detail |
|---|---|
| **Consent gate** | New `Frontend\Consent_Gate` class. Resolves consent state per request. Filterable. |
| **CMP detectors** | Built-in detectors for: Complianz, CookieYes, Cookiebot, Iubenda, OneTrust. Each returns one of `granted` / `denied` / `unknown`. |
| **Per-ad consent requirement** | New post meta `_wbam_requires_consent` (default `true` for `code` and `image` ad types; `false` for `plain-text`). Admin UI checkbox. |
| **Denied-state behavior** | Configurable: hide the ad, OR show a CMP-aware placeholder ("Allow cookies to see this ad" → CTA opens the CMP banner). |
| **Server-side gate** | Renderer checks consent before emitting ad markup. If `denied`, output the placeholder instead. No client-side bypass. |
| **Filter API** | `wb_ads_consent_state` (default → granted/denied/unknown), `wb_ads_consent_required_for_ad` ($ad_id → bool). |
| **Settings UI** | New "Privacy & Compliance" tab: CMP picker dropdown, denied-state behavior radio, default-required-by-type matrix. |
| **Documentation** | `docs/website/consent-mode.md` with one section per supported CMP + custom-CMP filter example. |

### IN scope: Core Web Vitals

| Component | Detail |
|---|---|
| **Slot dimension enforcement** | Required `_wbam_dimensions` meta (W×H) on every ad. Migration backfills from image attachment metadata for existing ads; admin pre-save validation for new ads. |
| **CLS-safe markup** | Renderer emits `<div class="wb-ads-slot" style="aspect-ratio: W/H; min-height: Hpx">` reserving space before the ad loads. Frontend swaps in real ad keeping dimensions. |
| **Lazy loading via IntersectionObserver** | Below-fold ads render as a placeholder; real ad swaps in on viewport entry. Above-fold (configurable count, default 1) load eagerly. |
| **Lazy-load JS** | New `assets/js/wb-ads-lazy.js`, ≤5KB minified, no dependencies. Uses native IntersectionObserver (no polyfill — IE11 is dead). |
| **REST endpoint for lazy fetch** | `GET /wb-ads/v1/render/ad/{id}` returns rendered HTML for one ad. Public, rate-limited. Alternative: emit full HTML in `<template>` server-side and swap on intersect (no extra request — preferred for first round). |
| **Impression tracking timing** | Currently fires on render. Move to fire on **viewport entry** (matching IAB's MRC viewability standard — 50% pixels visible for 1+ second). |
| **Performance budget gate** | Optional — admin can configure "if measured LCP > 2.5s, suppress ads on this page." Implemented via `wp_register_script` + a tiny inline LCP measurement script (or skip server-side and just provide the toggle). Defer to Phase 3 if too speculative. |
| **CWV CI** | Add Lighthouse CI to `.github/workflows/cwv.yml`. Demo page with 10 ads must score Lighthouse ≥ 90 perf and CLS ≤ 0.05. |

### OUT of scope (deferred)

- Advanced CMP integrations beyond the 5 listed (custom integrations via filter)
- Content-aware ad refresh (auto-reload after N seconds for engaged users) — separate sprint
- Header bidding / Prebid.js — separate sprint
- AdSense / GAM auto-loader — separate sprint, interacts with this work but isolated
- Per-ad performance budgets (different thresholds per ad) — over-engineered for v1
- A/B testing integration with CWV (winner = best CTR within CLS budget) — too complex for first pass
- Cookie consent UI of our own (we're a CMP **consumer**, not a CMP)

---

## Architecture

### Consent_Gate class

```php
namespace WBAM\Frontend;

class Consent_Gate {
    public function get_state(): string {
        // Returns 'granted', 'denied', or 'unknown'.
        $cmp_handler = $this->detect_cmp();
        $state       = $cmp_handler ? $cmp_handler->resolve() : 'unknown';

        /**
         * Filter consent state.
         *
         * @param string $state 'granted' | 'denied' | 'unknown'
         * @param string $cmp   Detected CMP slug ('complianz', 'cookieyes', ...) or null
         */
        return apply_filters( 'wb_ads_consent_state', $state, $cmp_handler ? $cmp_handler->slug() : null );
    }

    public function ad_requires_consent( int $ad_id ): bool {
        $required = (bool) get_post_meta( $ad_id, '_wbam_requires_consent', true );

        /** @param bool $required */
        return apply_filters( 'wb_ads_consent_required_for_ad', $required, $ad_id );
    }

    public function should_render_ad( int $ad_id ): bool {
        if ( ! $this->ad_requires_consent( $ad_id ) ) {
            return true;
        }
        return 'granted' === $this->get_state();
    }

    private function detect_cmp(): ?CMP_Handler_Interface {
        // Returns the first CMP whose plugin/script is detected, or null.
    }
}
```

### CMP detection

`includes/Frontend/CMP/` directory with one class per supported CMP, all implementing `CMP_Handler_Interface`:

```
includes/Frontend/CMP/
├── interface-cmp-handler.php
├── class-complianz.php       # detect: function_exists('cmplz_user_has_consent') -> resolve via that
├── class-cookieyes.php       # detect: cookie 'cky-consent'
├── class-cookiebot.php       # detect: window.Cookiebot global, mirrored to a cookie our PHP can read
├── class-iubenda.php         # detect: cookie '_iub_cs-*'
└── class-onetrust.php        # detect: cookie 'OptanonConsent'
```

Each handler exposes `slug()`, `is_present()`, and `resolve(): 'granted'|'denied'|'unknown'`.

### Lazy-load architecture

**Server-side markup** (per ad, regardless of fold):

```html
<div class="wb-ads-slot wb-ads-slot--lazy"
     data-wbam-ad-id="42"
     data-wbam-ad-content="{base64-encoded HTML}"
     style="aspect-ratio: 728/90; min-height: 90px;"
     aria-busy="true">
    <noscript>
        <!-- Real ad HTML for no-JS clients -->
    </noscript>
</div>
```

Above-fold ads (first N, default 1) get `data-wbam-eager="1"` and the JS swaps them immediately on `DOMContentLoaded` instead of waiting for intersect.

**Frontend JS** (`assets/js/wb-ads-lazy.js`):

```js
const observer = new IntersectionObserver( ( entries ) => {
    entries.forEach( ( entry ) => {
        if ( entry.isIntersecting ) {
            swapInAd( entry.target );
            observer.unobserve( entry.target );
            // Fire impression event AFTER swap, on 1s+ visibility.
            scheduleImpression( entry.target );
        }
    } );
}, { rootMargin: '200px 0px', threshold: 0.5 } );

document.querySelectorAll( '.wb-ads-slot--lazy:not([data-wbam-eager])' ).forEach( ( slot ) => observer.observe( slot ) );
document.querySelectorAll( '.wb-ads-slot--lazy[data-wbam-eager]' ).forEach( swapInAd );
```

`swapInAd()` decodes the base64 HTML from `data-wbam-ad-content`, swaps it into the slot, removes `aria-busy`, and fires a `wb-ads:rendered` custom event.

**Why base64 inline rather than a fetch:**
- One round trip per page instead of N
- Works without REST availability (REST 401, throttling, etc.)
- The HTML payload is already cheap (we control its size)
- Trade-off: increases initial HTML response size. Acceptable for typical 5-ad pages (≈10KB extra, gzipped much less).

If a customer has 50+ ads per page (rare), they can opt into REST-fetch mode via `add_filter( 'wb_ads_lazy_strategy', fn() => 'rest' );`.

### Impression tracking move

Currently `Frontend\Renderer` fires `wbam_ad_impression` on render. After this plan, it fires on viewport entry + 1s visible, matching the IAB MRC viewability standard. This makes our analytics directly comparable to AdSense/GAM data.

This is a behavior change for existing customers. Document in CHANGELOG with migration note: "Impression counts will drop ~30-50% relative to pre-2.11.0 because we now only count viewable impressions. CTR will go up correspondingly. This is a one-time recalibration."

### Settings UI

New tab in admin Settings → "Privacy & Performance":

```
[Privacy]
  CMP integration: [Auto-detect ▼]   (Complianz, CookieYes, Cookiebot, Iubenda, OneTrust, None)
  When consent denied:
    ( ) Hide ads completely
    (•) Show placeholder with consent CTA
    ( ) Show non-personalized fallback (text-only)
  Default consent requirement by ad type:
    [✓] Image ads
    [✓] Code ads (AdSense, GAM, custom HTML)
    [ ] Plain-text ads (no tracking — never required)

[Performance]
  Lazy-load ads below the fold  [✓]
  Above-fold eager count: [1]
  CLS-safe slot dimensions:
    [✓] Reserve slot height before ad loads (recommended)
  Impression tracking:
    (•) Viewable (50% visible for 1s — IAB MRC standard, recommended)
    ( ) On-render (legacy 2.10 and earlier behavior)
```

---

## Phases

### Phase 1 — Consent infrastructure — 1 sprint

1. Create `includes/Frontend/CMP/` with interface + 5 handlers
2. Create `Frontend\Consent_Gate`
3. Wire `Consent_Gate::should_render_ad()` into `Frontend\Renderer::render_ad()` — gates emission
4. Per-ad meta `_wbam_requires_consent` + admin UI checkbox in ad edit screen
5. Settings → Privacy tab (CMP picker, denied-state behavior)
6. Acceptance tests:
   - Each CMP handler resolves correctly when its CMP is "present" (mock cookies/functions)
   - Denied state suppresses ads with `_wbam_requires_consent = true`
   - Granted state renders normally
   - `wb_ads_consent_state` filter overrides everything
7. Documentation: `docs/website/consent-mode.md`

### Phase 2 — Lazy-load + CLS-safe slots — 1 sprint

8. New `_wbam_dimensions` meta + admin UI (W×H input on ad edit)
9. Migration: backfill `_wbam_dimensions` from image attachment metadata for existing image ads (idempotent, runs in `Installer` on version bump)
10. Renderer change: emit lazy slot markup with reserved aspect-ratio
11. New `assets/js/wb-ads-lazy.js` (≤5KB, no deps)
12. Above-fold eager count setting
13. Impression tracking moved to viewport-entry-and-1s
14. Settings → Performance tab
15. Acceptance tests:
    - Lazy slot reserves dimensions before ad swaps in (CLS test)
    - Eager count works (first N ads load on DOMContentLoaded, rest on intersect)
    - Impression fires only after 1s+ visibility
    - No-JS noscript fallback renders the ad immediately

### Phase 3 — CWV CI + polish — ½ sprint

16. Add `.github/workflows/cwv.yml` running Lighthouse CI on a demo page with 10 ads
17. Define perf budgets: Lighthouse ≥ 90, CLS ≤ 0.05, LCP ≤ 2.5s on 4G simulation
18. CHANGELOG migration note about impression count recalibration
19. Refresh manifest (new endpoints, hooks, settings)
20. Update CLAUDE.md "Privacy & Performance" section

---

## Acceptance criteria

| # | Criterion | Test |
|---|---|---|
| 1 | Consent state resolves correctly for all 5 CMPs | PHPUnit per handler |
| 2 | Denied consent + `_wbam_requires_consent = true` → ad hidden / placeholder | Browser verify |
| 3 | Custom CMPs supported via `wb_ads_consent_state` filter | Documentation example + integration test |
| 4 | All ads have `_wbam_dimensions` after migration | Migration test on existing fixture data |
| 5 | CLS ≤ 0.05 on demo page with 10 ads (Google "Good") | Lighthouse CI |
| 6 | LCP ≤ 2.5s on 4G simulation, demo page | Lighthouse CI |
| 7 | Lighthouse perf score ≥ 90 on demo page | Lighthouse CI |
| 8 | Lazy-loaded ads fire impression only on 1s+ visibility | Browser verify with throttling |
| 9 | Above-fold eager count works | Browser verify, scroll behavior |
| 10 | No regression in shortcode rendering | Existing shortcode tests pass |
| 11 | `composer verify` clean | Pre-push hook |
| 12 | RTL stylesheets unaffected | Browser verify in RTL theme |

---

## Risks & mitigations

| Risk | Mitigation |
|---|---|
| Existing customers see impression counts drop ~30-50% | Document in CHANGELOG. Add a setting to keep legacy on-render behavior for one major version (2.11.0–2.12.x). Remove in 2.13.0. |
| CMP detection brittleness — every CMP has different APIs and update cadences | Each CMP handler is a separate class; updates are isolated. Filter `wb_ads_consent_state` lets customers override entirely. Test fixtures pin known CMP behavior. |
| Lazy-loading conflicts with AdSense/GAM serving rules | AdSense **prefers** lazy-loaded ads (officially documented since 2019). For GAM, header bidding integration (separate plan) needs to coordinate timing. Out of scope here — the lazy infra is compatible. |
| `_wbam_dimensions` migration fails on edge-case ads (no attachment, broken meta) | Migration logs failures, doesn't crash. Affected ads get `_wbam_dimensions = '0x0'` and the renderer falls back to non-CLS-safe markup with a deprecation notice. Customer can fix via admin UI. |
| Above-fold detection is hard server-side | We don't try — eager count is a simple "first N ads on the page, in DOM order" heuristic. Configurable. Customers tune for their layouts. |
| Increased HTML response size (base64 ad content inline) | Typical impact: +5-15 KB gzipped on a 5-ad page. Negligible vs. the LCP wins. Customers with extreme ad density can opt into REST fetch via filter. |
| Settings UI bloat | One new tab, two sections (Privacy, Performance). Existing settings unchanged. |

---

## Dependencies

- `Frontend\Renderer` is already the central rendering choke point (verified in audit). All emission goes through it.
- `Installer` runs on version-bump activation hook — used for `_wbam_dimensions` backfill.
- `wbam-ad` post type schema gets one new meta key (`_wbam_dimensions`) and one updated meta key (`_wbam_requires_consent`).
- IntersectionObserver: 99.5% browser support per caniuse. No polyfill.
- Lighthouse CI requires a public demo URL or a CI-hosted WP. We can run it against `http://wp-ads.local` via Local-by-Flywheel CLI in dev; CI uses `wp-env`.

---

## Definition of done

- [ ] All 5 CMPs detected and resolved correctly
- [ ] Custom CMP integration documented with code example
- [ ] `_wbam_dimensions` migration runs on existing installs without data loss
- [ ] CLS ≤ 0.05 on demo page (Lighthouse)
- [ ] LCP ≤ 2.5s on demo page (Lighthouse, 4G)
- [ ] Lighthouse perf ≥ 90 on demo page
- [ ] CWV CI workflow green
- [ ] Privacy & Performance settings tab functional + browser-verified at 390px
- [ ] CHANGELOG includes migration note about impression recalibration
- [ ] `composer verify` clean
- [ ] Manifest refreshed (new endpoint, new settings, new hooks)
- [ ] CLAUDE.md updated with Privacy & Performance section
- [ ] `docs/website/consent-mode.md` published
- [ ] `docs/website/performance.md` published
