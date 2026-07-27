# Ad Slot Control — design

**Date:** 2026-07-27
**Repos touched:** `wb-ads-rotator-with-split-test` (Free), `wb-ad-manager-pro` (Pro)
**Origin:** a site that sells 3 banner slots and 1 in-video slot, while the plugin offers 11+ irrelevant placements and no way to close any of them. Per-site rollout notes live with that client's site records, not in this repo.

---

## 1. Problem

Three separate defects surfaced from one client request.

**1a. Hardcoded ad surfaces are not slots.** A companion plugin renders its ad
surfaces by echoing `[wbam_ad id="X"]` with an ad ID read from its own option.
This is the shape every integrator reaches for today, because §1d leaves no
alternative. Consequences:

- No rotation, no split test, no frequency cap, no session limit, no package guard.
- The surfaces do not exist in the ad edit Placements metabox, the advertiser
  portal, or packages — so nothing can be sold against them.
- The option typically has no admin UI, no REST route, and no WP-CLI command,
  violating the three-entry-points rule (frontend / backend / API).

**1b. The placement list is read two different ways and drifts.**

| Consumer | Source |
|---|---|
| Ad edit metabox (`Admin::render_placements_metabox()`, ~line 921) | `Placement_Engine::get_placements_grouped()` |
| Advertiser portal + packages | `wbam_get_placements` filter, seeded by `Placement_Format_Map::seed_from_engine()` |

Anything that filters one does not affect the other.

**1c. There is no way to close a slot, at any level.** Only the hardcoded
per-class `show_in_selector()` exists. A site that can use 4 slots still shows
advertisers all 11+ core placements plus BuddyPress / bbPress / Jetonomy ones.

**1d. No slot is addressable as a token.** `[wbam_ad id="123"]` and `[wbam_ads]`
target specific ad IDs. Nothing renders "whatever ads are assigned to slot X",
which is why 1a happened — hardcoding an ID was the only option available.

---

## 2. Model

One registry, two gates, and a way to add rows to the registry.

```
                  Placement_Engine (registry)
                            │
              ┌─────────────┴─────────────┐
        site gate                  advertiser gate
   enabled_placements         advertiser_placements
              │                             │
   ┌──────────┴────────┐         ┌──────────┴─────────┐
   │                   │         │                    │
Ad edit          Rendering    Portal slot        Package
metabox                       grid               placement picker
```

**Invariants**

- The advertiser list is a subset of the site list. Unticking a slot at site
  level removes it from the advertiser list on save.
- An empty array means "all" for both gates. This keeps every existing install
  behaving identically on upgrade.
- The site gate stops delivery. The advertiser gate does not — it only controls
  what can be *selected*, so closing a slot for sale never dark-drops a
  creative an advertiser already paid for.

**Free/Pro boundary**

| | Free | Pro |
|---|---|---|
| Slot **control** (both gates, settings UI) | ✅ | consumes |
| Slot **creation** (custom slots) | — | ✅ |

---

## 3. Free plugin — slot control

### 3.1 Single source of truth

New method on `Placement_Engine`:

```php
/**
 * Placements that may be assigned to an ad on this site.
 *
 * Applies, in order: is_available(), show_in_selector(), the site
 * allowlist. This is the ONLY method admin UI and the portal registry
 * may use — reading $this->placements directly reintroduces the drift
 * this method exists to remove.
 *
 * @return Placement_Interface[] Keyed by placement ID.
 */
public function get_selectable_placements();
```

Both drifting consumers switch to it:

- `Admin::render_placements_metabox()` — replaces the `get_placements_grouped()` loop.
  Keep grouping by calling `get_group()` over the filtered set.
- `Placement_Format_Map::seed_from_engine()` — replaces its own
  `is_available() && show_in_selector()` loop.

### 3.2 Settings

Two new keys in the existing `wbam_settings` option (`Admin\Settings::OPTION_NAME`):

| Key | Type | Default | Meaning |
|---|---|---|---|
| `enabled_placements` | `string[]` | `array()` | Slots usable on this site. Empty = all. |
| `advertiser_placements` | `string[]` | `array()` | Slots sellable to advertisers. Empty = all. Always intersected with `enabled_placements`. |

New filters, applied after the option read so code can always override:

- `wbam_enabled_placements( string[] $ids )`
- `wbam_advertiser_placements( string[] $ids )`

### 3.3 Settings UI

New **Placements** section on the WBAM Settings screen, grouped by
`get_group()`, one row per registered slot:

```
WB Ad Manager → Settings → Placements

  Slot                              Site   Advertisers   Active ads
  ─────────────────────────────────────────────────────────────────
  Custom
    Sponsor — above player           ☑         ☑             1
    Sponsor — sidebar                ☑         ☑             1
    Sponsor — in-video breaks        ☑         ☐             2
  WordPress
    Header                           ☐         ☐             0
    Footer                           ☐         ☐             0
    In content                       ☐         ☐             0
    ...
```

Behaviour:

- **Active ads** column — count of enabled `wbam-ad` posts carrying that slug in
  `_wbam_placements`. One grouped query for the whole screen, not one per row.
- Unticking **Site** for a slot with a non-zero count fires a confirm listing
  what will stop rendering. No silent inventory kill.
- Unticking **Site** auto-unticks **Advertisers** in the DOM, and the sanitizer
  enforces the same intersection server-side. Never trust the client.
- Ticking **Advertisers** on a slot whose **Site** box is off is not possible.

### 3.4 Rendering gate

The gate goes in `Placement_Engine::get_ads_for_placement( $placement_id )`
(line 199), which returns an empty array when the placement is not in the site
allowlist. Verified as the single funnel: every placement class calls it before
`render_ad()`. There is no `render_placement()` method — an earlier draft of
this spec named one that does not exist.

`render_ad()` is deliberately not gated. It is per-ad and is called by
`Shortcode_Placement` without a placement context, so gating there would break
`[wbam_ad id="123"]`, which is ID-targeted by design and not slot inventory.

This is the only rendering change; the advertiser gate is selection-only (§2).

### 3.5 Big-site readiness

- Active-ad counts: one `GROUP BY` query for the whole screen. No N+1 across rows.
- `get_selectable_placements()` result memoised per request — it is called by the
  metabox, the registry seed, and every `get_ads_for_placement()` call on the page.
- Allowlists are small arrays in an already-autoloaded option. No new table.
- Settings screen is a `<table>` with real headers, checkboxes labelled per row
  for screen readers, and token-driven styles for dark mode / RTL.

### 3.6 Free entry points

| Surface | Where |
|---|---|
| Backend | Settings → Placements |
| API | `enabled_placements` / `advertiser_placements` exposed via the existing `Settings_API` |
| Frontend | The gates are what the rendering path enforces |

---

## 4. Pro plugin — slot creation + advertiser gate

### 4.1 Wire the advertiser gate

`wbam_pro_get_selectable_placements()` already exists and already applies
`wbam_pro_selectable_placements`. Hook the advertiser allowlist onto that
filter. Everything downstream inherits it with no further change:

- Advertiser portal slot grid (`templates/portal/ad-form.php`, `wbam-placements-grid`)
- Package placement picker
- `wbam_pro_validate_placement_slugs()` — submissions naming a closed slot are
  already rejected by this helper

### 4.2 Reconcile with Placement_Guard

`Packages\Placement_Guard` enforces a package's `allowed_placements` at render
time. The two gates land at different moments, and conflating them would break
the §2 invariant that the advertiser gate never dark-drops paid inventory:

| Layer | Applied at | Effect |
|---|---|---|
| Site allowlist | render + selection | stops delivery |
| Advertiser allowlist | selection only | slot unsellable, existing ads keep serving |
| Package placements | render (existing `Placement_Guard`) | unchanged |

So the sellable set an advertiser sees is:

```
site allowlist  ∩  advertiser allowlist  ∩  package placements
```

each empty set meaning "no restriction from this layer" — but `Placement_Guard`
itself only gains the **site** allowlist. The advertiser allowlist stays out of
the render path. Its job is to stop a package from *offering* a slot the admin
closed, which is enforced in the package placement picker and in
`wbam_pro_validate_placement_slugs()`.

### 4.3 Custom Slots module

New module: `includes/Modules/Slots/`.

Storage: option `wbam_custom_slots` — an array of slot definitions. Config
cardinality, not data cardinality; no table.

```php
array(
    'sponsor_footer' => array(
        'id'          => 'sponsor_footer',   // sanitize_key, immutable after create
        'name'        => 'Sponsor — footer strip',
        'description' => 'Full-width strip above the site footer.',
        'group'       => 'Custom',
        'hook'        => '',                 // optional bound action, see 4.4
        'hook_args'   => 0,
        'priority'    => 10,
    ),
)
```

Each definition is registered as a `Custom_Slot implements Placement_Interface`
into the Free `Placement_Engine` on `wbam_register_placements`. That is the
whole integration — custom slots then inherit both gates, packages, targeting,
rotation, split test, frequency caps and analytics with no parallel code path.

**Always-available integration points**, shown ready-to-copy in the admin UI:

```
Slot: "Sponsor — footer strip"        id: sponsor_footer

  Shortcode   [wbam_slot id="sponsor_footer"]
  Template    do_action( 'wbam_slot', 'sponsor_footer' );
```

`[wbam_slot]` is the missing primitive from §1d — it renders whichever ads are
assigned to the slot, through `render_placement()`, so rotation and capping
apply. It is registered by the Slots module, not by `Shortcode_Placement`
(which stays ID-targeted and stays out of the selector).

### 4.4 Optional hook binding

Advanced field on the slot form: attach the slot to an existing action hook so
it can reach any theme or plugin surface with zero code.

```
▾ Advanced
  Attach to existing hook:
  [ learnomy_lesson_sidebar_top          ]
  ✓ fired 1× on the last page load
```

The validator is the point — a mistyped hook renders nothing with no error,
which is the failure mode this feature would otherwise ship. Implementation:

- A lightweight recorder, active only while an admin with `manage_options` has
  the Slots screen open (short-lived transient flag), notes whether each bound
  hook fired during the next front-end request and how many args it carried.
- The Slots screen reads that back and shows fired / never-fired per slot.
- Never-fired slots get a warning badge in the list, not just on the edit form.
- `hook` is stored raw but only ever passed to `add_action()`. It is never
  echoed unescaped, and only `manage_options` may write it.

Recommended hooks are offered as datalist suggestions sourced from the active
integrations (Learnomy, BuddyPress, bbPress, WooCommerce, Jetonomy) so the
common cases do not require typing.

### 4.5 Pro entry points

| Surface | Where |
|---|---|
| Backend | WB Ad Manager → Slots (list, add, edit, delete) |
| API | `GET/POST/PATCH/DELETE /wbam/v1/slots` |
| Frontend | `[wbam_slot id="x"]` and `do_action( 'wbam_slot', 'x' )` |

Deleting a slot that has assigned ads warns with the count first, same rule as
§3.3.

---
## 5. Site integration — how a slot reaches a third-party surface

Nothing in this design is specific to any one site. A slot can reach a theme or
plugin surface three ways, in increasing order of coupling:

1. **Pro Custom Slots UI** (§4.3) — the site owner creates a slot and drops
   `[wbam_slot id="x"]` into a template, or binds it to an existing action hook.
   No code. Right for ad-hoc inventory.
2. **A companion plugin registers slots in code** — on `wbam_register_placements`,
   implementing `Placement_Interface`, following the `Jetonomy_Module` pattern
   (one parameterised class, one row per slot). Right when a slot must express a
   condition the UI cannot, such as `is_available()` returning true only while a
   given integration is active, or when the slot is contracted inventory that
   should be versioned and deployable rather than deletable click-config.
3. **A built-in module in this plugin** — for integrations broad enough to
   warrant shipping in the product (BuddyPress, bbPress, Jetonomy today).

Route 2 is also how an existing hardcoded ad surface should be migrated. A
surface that echoes `[wbam_ad id="123"]` gets no rotation, no split test, no
frequency cap, no package guard and no per-sponsor tracking; re-registering it
as a real slot and moving the ad ID onto `_wbam_placements` fixes all of that in
one change.

Per-site rollouts are documented outside this repo, with the client's other
site records. This repo carries the product design only.

## 7. Sequencing

Free ships first — Pro's advertiser gate reads Free's setting.

1. **Free** — `get_selectable_placements()`, both settings, Settings → Placements
   UI, rendering gate, both consumers switched over.
2. **Pro** — advertiser gate wired to `wbam_pro_selectable_placements`,
   `Placement_Guard` reconciled, Custom Slots module, `[wbam_slot]`.
Each ships as a PR merged to `main` after its local CI gate
(`composer verify-no-test`) is green. Nothing here is blocked on another team.

A site-integration plugin adopting §5 route 2 follows afterwards, on its own
schedule, since it consumes the registration API rather than changing it.

---

## 8. Upgrade safety

| Risk | Mitigation |
|---|---|
| Existing WBAM sites lose placements | Empty allowlist = all. Nothing changes until an admin ticks something. |
| Existing Pro advertiser portals go empty | Migration seeds `advertiser_placements` from the site list, so portals show exactly what they show today. |
| An integrator's existing hardcoded ads go dark on migration | Migrating a surface must map its ad IDs onto `_wbam_placements` before retiring the old option. |
| Admin closes a slot with live ads | Active-ad count shown inline; confirm-on-save lists what stops rendering. |
| Bound custom-slot hook never fires | Fired/never-fired validator on the Slots screen, warning badge in the list. |

---

## 9. Testing

**Unit**

- `get_selectable_placements()` honours each gate independently and the intersection.
- Sanitizer enforces advertiser ⊆ site even when the client posts otherwise.
- Empty allowlist means all, for both gates.

**Integration**

- Metabox and portal registry return the identical set for the same settings —
  the regression test for §1b.
- A package cannot sell a slot closed at site or advertiser level.
- `[wbam_slot]` and `do_action('wbam_slot', …)` render the same thing and both
  apply frequency caps.

**Browser** (Playwright MCP, per-item, at 1440px and 390px)

- A custom slot renders on its target surface, with rotation observable across reloads.
- Settings → Placements: counts correct, confirm fires, dark mode and RTL clean.
- Advertiser portal: closed slots absent from the grid.
- Slots screen: bound-hook validator reports fired and never-fired correctly.

**Contract audit** — `/wp-contract-audit` across both plugins before tagging.
Both new settings keys are written and read, and `wbam_slot` is fired and
consumed.
