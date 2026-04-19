# WB Ad Manager Pro — Hook Reference

> **PRO feature reference.** This guide documents the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on. Developer hooks and APIs here are only available when the Pro plugin is active on top of the free plugin.

Every public filter and action exposed by Pro. This list is the extension surface a 3rd-party developer can rely on.

**Conventions:**

- `wbam_pro_before_*` — filters. Return `WP_Error` to abort. Some return `null` / `true` to short-circuit (documented per hook).
- `wbam_pro_after_*` — actions. Fire post-commit. Never used to mutate state.
- `wbam_pro_rest_prepare_*` — filters on REST response payloads. Receive `$data` + the source model + scope flags.
- `wbam_pro_ad_form_step_N_after_fields` — render-time action slots inside the ad submission wizard template.
- `wbam_pro_free_setting_{key}` — filter on cross-plugin Free-plugin option reads via `Settings_Helper::get_free()`.

Legacy hooks (prefix `wbam_` without `_pro_`) are preserved for back-compat. Prefer `wbam_pro_*` for new code.

---

## Write-path hooks

### Advertisers

| Hook | Type | Since | Signature |
|------|------|-------|-----------|
| `wbam_pro_before_create_advertiser` | filter | 1.5.0 | `(array $data, int $user_id)` |
| `wbam_pro_after_create_advertiser` | action | 1.5.0 | `(Advertiser $advertiser)` |
| `wbam_pro_before_update_advertiser` | filter | 1.5.0 | `(array $data, Advertiser $advertiser, int $id)` |
| `wbam_pro_after_update_advertiser` | action | 1.5.0 | `(Advertiser $advertiser, array $old_data)` |
| `wbam_pro_before_delete_advertiser` | filter | 1.5.0 | `(null\|mixed $preempt, Advertiser $advertiser, int $id)` — non-null return short-circuits delete |
| `wbam_pro_after_delete_advertiser` | action | 1.5.0 | `(int $advertiser_id, array $data)` |

### Campaigns

| Hook | Type | Since | Signature |
|------|------|-------|-----------|
| `wbam_pro_before_create_campaign` | filter | 1.5.0 | `(array $data)` |
| `wbam_pro_after_create_campaign` | action | 1.5.0 | `(Campaign $campaign)` |
| `wbam_pro_before_update_campaign` | filter | 1.5.0 | `(array $data, Campaign $campaign, int $id)` |
| `wbam_pro_after_update_campaign` | action | 1.5.0 | `(Campaign $campaign, array $old_data)` |
| `wbam_pro_before_delete_campaign` | filter | 1.5.0 | `(null\|mixed $preempt, Campaign $campaign, int $id)` — non-null return short-circuits |
| `wbam_pro_after_delete_campaign` | action | 1.5.0 | `(int $id, array $data)` |
| `wbam_campaign_status_changed` | action | 1.2.0 | `(Campaign $campaign, string $old_status, string $new_status)` — legacy, canonical |

### Classifieds

| Hook | Type | Since | Signature |
|------|------|-------|-----------|
| `wbam_pro_before_create_classified` | filter | 1.5.0 | `(array $data)` |
| `wbam_pro_after_create_classified` | action | 1.5.0 | `(Classified $classified, array $data)` |
| `wbam_pro_before_update_classified` | filter | 1.5.0 | `(array $data, Classified $classified, int $id)` |
| `wbam_pro_after_update_classified` | action | 1.5.0 | `(Classified $classified, array $data)` |
| `wbam_pro_before_delete_classified` | filter | 1.5.0 | `(null\|mixed $preempt, Classified $classified, int $id, bool $force_delete)` |
| `wbam_pro_after_delete_classified` | action | 1.5.0 | `(int $id, int $post_id)` |

### Packages

| Hook | Type | Since | Signature |
|------|------|-------|-----------|
| `wbam_pro_before_create_package` | filter | 1.5.0 | `(array $data)` |
| `wbam_pro_after_create_package` | action | 1.5.0 | `(Package $package)` |
| `wbam_pro_before_update_package` | filter | 1.5.0 | `(array $data, Package $package, int $id)` |
| `wbam_pro_after_update_package` | action | 1.5.0 | `(Package $package, array $old_data)` |
| `wbam_pro_before_delete_package` | filter | 1.5.0 | `(null\|mixed $preempt, int $id)` |
| `wbam_pro_after_delete_package` | action | 1.5.0 | `(int $id, array $data)` |

### Ad Submissions

| Hook | Type | Since | Signature |
|------|------|-------|-----------|
| `wbam_pro_before_submit_ad` | filter | 1.5.0 | `(array $ad_data, int $advertiser_id, int $package_id)` |
| `wbam_ad_submitted` | action | 1.0.0 | `(Ad_Submission $submission)` |

---

## REST response-shape filters

| Hook | Since | Signature | Fired from |
|------|-------|-----------|------------|
| `wbam_pro_rest_prepare_package` | 1.5.0 | `(array $data, Package $package, bool $is_admin)` | `Package_API::prepare_package_response()` |
| `wbam_pro_rest_prepare_classified_public` | 1.5.0 | `(array $data, Classified $classified, bool $full)` | `Classified_API::prepare_public_response()` |
| `wbam_pro_rest_prepare_classified_user` | 1.5.0 | `(array $data, Classified $classified, bool $full)` | `Classified_API::prepare_user_response()` |
| `wbam_pro_rest_prepare_classified_admin` | 1.5.0 | `(array $data, Classified $classified, bool $full)` | `Classified_API::prepare_admin_response()` |

**Fire order for admin responses:** `_public` → `_user` → `_admin`. All three run when a user has admin scope. Hook into the narrowest level you need to target.

---

## Ad submission wizard slots

Render-time actions inside `templates/portal/ad-form.php`. Inject extra fields by echoing markup — name attributes in `<input name="...">` are preserved through to `Ad_Submission_Manager::submit_ad` where they can be persisted via `wbam_pro_before_submit_ad`.

| Hook | Since | Signature |
|------|-------|-----------|
| `wbam_pro_ad_form_step_1_after_fields` | 1.5.0 | `(array $ad_data, bool $is_edit)` — Ad Type step |
| `wbam_pro_ad_form_step_2_after_fields` | 1.5.0 | `(array $ad_data, bool $is_edit)` — Content step |
| `wbam_pro_ad_form_step_3_after_fields` | 1.5.0 | `(array $ad_data, bool $is_edit)` — Package step |
| `wbam_pro_ad_form_step_4_after_fields` | 1.5.0 | `(array $ad_data, bool $is_edit, array $placements)` — Placement step |

---

## Cross-plugin bridges

| Hook | Type | Since | Signature |
|------|------|-------|-----------|
| `wbam_pro_free_setting_{key}` | filter | 1.5.0 | `(mixed $value, string $key, mixed $default)` — lets you override any Free-plugin setting read. Example: `wbam_pro_free_setting_link_cloak_prefix`. |

---

## Worked examples

### Add a custom field to the ad submission wizard (Task C from Pass 6)

```php
// Inject a new field on the Content step.
add_action( 'wbam_pro_ad_form_step_2_after_fields', function ( $ad_data, $is_edit ) {
    $value = $ad_data['promo_code'] ?? '';
    ?>
    <div class="wbam-form-row">
        <label for="promo_code">Promo Code</label>
        <input type="text" id="promo_code" name="promo_code"
               value="<?php echo esc_attr( $value ); ?>">
    </div>
    <?php
}, 10, 2 );

// Persist it via the submit filter.
add_filter( 'wbam_pro_before_submit_ad', function ( $ad_data, $advertiser_id, $package_id ) {
    if ( isset( $_POST['promo_code'] ) ) {
        $ad_data['promo_code'] = sanitize_text_field( wp_unslash( $_POST['promo_code'] ) );
    }
    return $ad_data;
}, 10, 3 );
```

### Block a classified submission for a specific category

```php
add_filter( 'wbam_pro_before_create_classified', function ( $data ) {
    if ( ! empty( $data['category_id'] ) && (int) $data['category_id'] === 99 ) {
        return new \WP_Error( 'blocked_category', 'This category is closed to new listings.' );
    }
    return $data;
} );
```

### Add a "sold" badge to the public classified REST payload

```php
add_filter( 'wbam_pro_rest_prepare_classified_public', function ( $data, $classified ) {
    $data['is_sold'] = (bool) get_post_meta( $classified->post_id, '_mystore_sold', true );
    return $data;
}, 10, 2 );
```

### Short-circuit advertiser delete (soft-delete instead)

```php
add_filter( 'wbam_pro_before_delete_advertiser', function ( $preempt, $advertiser, $id ) {
    update_post_meta( $advertiser->user_id, '_mystore_archived', time() );
    return true; // skip default delete; we archived instead.
}, 10, 3 );
```

---

## Discoverability notes

- Every filter/action is documented inline with `@since 1.5.0` and full `@param` signatures. IDE autocomplete works against the plugin source.
- Legacy hooks (`wbam_advertiser_created`, `wbam_campaign_created`, etc.) are preserved verbatim.
- Hook names follow `wbam_pro_{verb}_{action}_{entity}` — e.g., `wbam_pro_before_create_advertiser`.

## Changelog

- **1.5.0** — Initial HOOKS.md. Added ~20 before_/after_ hooks across managers, 4 REST response filters, 4 ad-wizard slots, 1 cross-plugin bridge filter.
