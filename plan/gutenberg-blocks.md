# Plan: Gutenberg Block Coverage — WB Ads Rotator (FREE)

> **Status:** Draft for review
> **Target release:** FREE 2.10.0 (after 2.9.0 coupling cleanup ships)
> **Estimate:** 3 focused sprints (~3-4 weeks of build + verify)
> **Owner:** Varun

---

## Problem statement

We have **6 shortcodes and 0 blocks** today. WordPress 6.7+ ships block-only themes by default (Twenty Twenty-Five, Twenty Twenty-Four). On those themes, customers using the Site Editor literally cannot insert our ads through the editor — they have to drop a Shortcode block, type `[wbam_ad id="…"]`, and hope they remember the right ID.

Every credible 2026 WordPress ad plugin ships block coverage. Without it, we lose:
- Block-theme customers (who are now the default install path)
- Site Editor / Full Site Editing users (who can't edit shortcodes inline)
- Anyone evaluating us against Advanced Ads / Ad Inserter side-by-side

This is existential. Not nice-to-have.

---

## Goal

Every FREE shortcode gets a block-equivalent that produces **byte-identical output** when rendered. Blocks register early enough to be insertable in posts, pages, template parts, and full-site templates. Editor preview shows the real ad, not a placeholder.

PRO blocks (advertiser portal, classifieds, submission forms) are deferred to a follow-up plan and consume the FREE block infrastructure built here.

---

## Scope

### IN scope (FREE 2.10.0)

| Block | Shortcode it replaces | Render type |
|---|---|---|
| `wb-ads/ad-slot` | `[wbam_ad id]` | Dynamic (ServerSideRender) |
| `wb-ads/ad-zone` | `[wbam_ads]` | Dynamic |
| `wb-ads/partnership-link` | `[wbam_link]` | Dynamic |
| `wb-ads/partnership-links` | `[wbam_links]` | Dynamic |
| `wb-ads/partnership-inquiry` | `[wbam_partnership_inquiry]` | Dynamic |

`wbam_link_url` (URL-only) does not need a block — it's used inside other markup.

### IN scope: shared block infrastructure

- `@wordpress/scripts` build pipeline (coexists with existing Grunt — Grunt keeps owning the legacy CSS/JS pipeline; new blocks build through `wp-scripts`)
- `blocks/` source directory with one folder per block (`block.json` + `index.js` + `edit.js` + `style.scss` + `editor.scss` + `render.php`)
- `build/blocks/` output (gitignored) — registered via `register_block_type_from_metadata( WBAM_DIR . 'build/blocks/{slug}' )`
- One shared store: `wp.data` namespace `wb-ads/editor` for cross-block state (selected ad cache, etc.)
- Block category: `wb-ads` registered via `block_categories_all` filter — all 5 blocks live there
- Standard responsive attribute set per Part 9 of `/wp-plugin-development`: `uniqueId`, padding/margin/border/shadow, device visibility (`hideOnMobile`, `hideOnTablet`, `hideOnDesktop`)
- RTL stylesheets generated via `rtlcss` in the build step
- i18n: `wp-scripts` extracts strings to a JSON file; loaded via `wp_set_script_translations()`

### IN scope: editor experience

- Each block uses `<ServerSideRender>` for live preview — the actual ad serves in the editor exactly as it will on the front end
- Inspector panel: ad/zone picker (`PostSelectControl` for `wbam-ad` post type), responsive visibility toggles, alignment, optional CSS class
- Block placeholder when no ad/zone is picked yet ("Choose an ad or create a new one →" — links to `post-new.php?post_type=wbam-ad`)
- Block variations API: pre-configured variations (e.g., "Ad Slot — Banner 728×90", "Ad Slot — Sidebar 300×250") with sensible defaults

### IN scope: shortcode parity test

Every block has a PHPUnit test that renders both the shortcode and the block with the same attributes, asserts the rendered HTML is identical (or differs only in wrapper class, documented).

### OUT of scope (deferred)

- PRO blocks (advertiser portal, classifieds — separate plan, consumes this infra)
- Block patterns library ("Sidebar with featured ad + 3 latest classifieds" composite layouts)
- InnerBlocks for `ad-zone` (would let users compose zones in the editor instead of via admin)
- Variation transforms (shortcode → block converter / on-paste)
- Migration tool (auto-convert existing shortcode usage to blocks across all posts)
- @wordpress/interactivity-api integration (frontend interactivity blocks — none of our 5 blocks need it; ads are display-only)

---

## Architecture

### Block registration timing

Blocks register on `init:5` in a new file `includes/Blocks/class-block-registry.php`:

```php
namespace WBAM\Blocks;

class Block_Registry {
    public function register(): void {
        $blocks = array( 'ad-slot', 'ad-zone', 'partnership-link', 'partnership-links', 'partnership-inquiry' );
        foreach ( $blocks as $slug ) {
            register_block_type_from_metadata( WBAM_DIR . "build/blocks/{$slug}" );
        }
    }
}
```

Registered service: `services.blocks.registry` in the bootstrap. Hooked: `add_action( 'init', array( $registry, 'register' ), 5 )`.

### Render path

Each block's `render.php` calls into the **existing** rendering classes — no duplicate logic:

```php
// build/blocks/ad-slot/render.php
$attrs = $attributes;
$ad_id = isset( $attrs['adId'] ) ? absint( $attrs['adId'] ) : 0;

if ( ! $ad_id ) {
    return '';
}

// Reuse the shortcode handler so block + shortcode produce identical HTML.
return \WBAM\Modules\Placements\Shortcode_Placement::shortcode_single(
    array( 'id' => $ad_id ),
    null,
    'wb-ads/ad-slot'
);
```

This is the **byte-identical guarantee** — both code paths terminate in the same rendering function. If the renderer changes, both update at once.

### Shared editor JS

`blocks/_shared/` — utilities reused across blocks:
- `useAds( args )` hook — fetches ads via REST `wb-ads/v1/ads` with caching
- `<AdPicker />` component — `PostSelectControl` wrapper specialized for `wbam-ad`
- `<ResponsiveVisibilityControls />` — the standard mobile/tablet/desktop toggles

### Site Editor integration

- Block category `wb-ads` so blocks group together in the inserter
- Block `supports.html` is `false` (no manual HTML editing — server controls output)
- Blocks are valid in template parts, page templates, and posts (no `parent` restriction)
- Blocks declare `align: ['wide', 'full']` so they work in wide-aligned contexts

---

## Phases

### Phase 1 — Build pipeline + first block (Ad Slot) — 1 sprint

1. Add `@wordpress/scripts` to devDependencies; add `build:blocks` and `start:blocks` npm scripts
2. Configure webpack entry to emit per-block bundles into `build/blocks/`
3. Update `.gitignore` to exclude `build/blocks/` (built by CI/release)
4. Update `bin/build-release.sh` to run `npm run build:blocks` before zipping
5. Update `.distignore` to keep `build/blocks/`, exclude `blocks/` source
6. Create `includes/Blocks/class-block-registry.php` and wire it into `Plugin::boot()`
7. Build `blocks/ad-slot/` end-to-end:
   - `block.json` with all attributes + variations
   - `edit.js` with ServerSideRender + Inspector panel + AdPicker
   - `render.php` calling `Shortcode_Placement::shortcode_single`
   - `style.scss` (frontend) + `editor.scss` (editor-only — placeholder, hover state)
8. PHPUnit acceptance test: `tests/blocks/AdSlotParityTest.php` — render shortcode and block side-by-side, assert HTML equivalence
9. Browser-verify in WP admin editor + Site Editor at desktop + 390px mobile

**Phase 1 deliverable:** one block, working end-to-end, with parity test passing. Pipeline ready for the next 4.

### Phase 2 — Remaining 4 blocks — 1 sprint

10. `blocks/ad-zone/`
11. `blocks/partnership-link/`
12. `blocks/partnership-links/`
13. `blocks/partnership-inquiry/`
14. Each with parity test + browser verify

### Phase 3 — Polish + release — ½ sprint

15. Block variations: 4 variations of `ad-slot` (banner/sidebar/in-content/footer) with default dimensions
16. Editor onboarding tooltip on first install: "We have blocks now — try them in the inserter under WB Ads"
17. RTL stylesheets verified
18. i18n `.pot` regenerated, JSON translations split per block
19. Documentation: `docs/website/using-blocks.md` with screenshots
20. CLAUDE.md "Blocks" section + manifest refresh (5 new entries in `blocks` array)

---

## Acceptance criteria

| # | Criterion | Test |
|---|---|---|
| 1 | All 5 blocks insertable via post editor | Browser verify at desktop + 390px |
| 2 | All 5 blocks insertable via Site Editor template parts | Browser verify on Twenty Twenty-Five |
| 3 | Block render output is byte-identical to shortcode equivalent | PHPUnit parity tests, one per block |
| 4 | Editor preview shows the real ad (ServerSideRender) | Browser verify |
| 5 | Mobile/tablet/desktop visibility toggles work | Browser verify with viewport resize |
| 6 | Each block bundle ≤ 20 KB gzipped JS, ≤ 30 KB gzipped CSS | size-limit in CI |
| 7 | Lighthouse score ≥ 90 on demo page with 5 blocks | Lighthouse CI |
| 8 | Zero new PHPCS errors, PHPStan level 5 clean, arch-checks pass | `composer verify` in pre-push hook |
| 9 | RTL stylesheet present for each block | File existence check in release builder |
| 10 | `block_types_for_post_type` gating not used (blocks available everywhere) | Code review |

---

## Risks & mitigations

| Risk | Mitigation |
|---|---|
| Editor `ServerSideRender` is slow on placeholder-heavy posts | Cache rendered preview HTML in transient with 5-min TTL keyed by attributes; invalidate on `save_post_wbam-ad` |
| `@wordpress/scripts` build differs from Grunt build conventions | Coexist — Grunt keeps the legacy CSS/JS pipeline, `wp-scripts` only owns `blocks/`. No interference. |
| Customers with custom theme overrides break when block markup wraps in additional `<div>` | The shared `Shortcode_Placement::shortcode_single` produces identical output; block render.php just calls it. No new wrapper added. |
| Migration of existing shortcode usage to blocks | Out of scope — shortcodes continue to work indefinitely. New customers use blocks; existing customers adopt at their own pace. |
| Block adoption causes a regression in shortcode rendering (because the same code path now serves both) | Parity tests fail loudly if the shared path drifts |
| Pro blocks need to extend FREE infra | Pro plan (separate document) consumes `Block_Registry` and the `_shared/` editor components. The contract is "register your block.json files; FREE registers them in the same loop." Documented in `plan/free-pro-architecture-contract.md` Part D. |

---

## Open questions

1. **Variations API granularity** — should "Ad Slot — Banner 728×90" be a variation or its own block? Recommendation: variation. Same render code, different defaults. Easier discovery in inserter.
2. **Block locking** — should `ad-zone` lock its position attribute (to enforce placement consistency)? Defer; revisit after launch.
3. **Editor preview without an ad selected** — show example ad, or the placeholder? Recommendation: placeholder with CTA. Less misleading.

---

## Out of scope — explicit list

These come up in conversation; capturing here so we don't accidentally creep:

- @wordpress/interactivity-api — none of our 5 blocks need client-side interactivity; ad rendering is display-only. Defer until we have an interactive block (e.g., voting, polls).
- AdSense / GAM auto-block — separate sprint, planned in `plan/adsense-integration.md` (TBD).
- Sticky/floating block (anchor-positioned ads) — separate sprint, depends on CWV plan landing first.
- Block-based admin UI — admin pages stay PHP. Out of scope here.
- React-driven post editor sidebar (custom plugin sidebar for ad management) — out of scope.

---

## Dependencies

- Existing `Shortcode_Placement::shortcode_single` and `Shortcode_Placement::shortcode_multiple` continue to be the canonical render path. Nothing in this plan changes those.
- `wbam-ad` post type and meta schema unchanged.
- `WBAM_VERSION` constant bumped to `2.10.0` in main plugin file when this lands.
- @wordpress/scripts requires Node 18+. CI runners and local dev expected to have it.

---

## Definition of done

- [ ] All 5 blocks ship in `build/blocks/` of the release zip
- [ ] All parity tests green
- [ ] Browser verified on Twenty Twenty-Five + classic theme + 390px viewport
- [ ] Lighthouse ≥ 90 on demo page
- [ ] CWV regression check (CLS, LCP) within 5% of pre-blocks baseline
- [ ] `composer verify` clean
- [ ] Manifest refreshed (5 entries in `blocks` array)
- [ ] CLAUDE.md updated with Blocks section
- [ ] Release notes mention blocks under "What's new"
- [ ] Docs published at `docs/website/using-blocks.md`
