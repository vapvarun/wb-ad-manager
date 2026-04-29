# WB Ads Rotator (FREE) — Code Flow Maps

**Generated**: 2026-04-29
**Source**: [`audit/manifest.json`](manifest.json)

---

## Flow 1: Serve an ad (frontend)

**Entry points**:
- PHP: `[wbam_ad id="123"]` shortcode in post content
- PHP: theme calls `Placement_Engine::render_ad($id, $context)` directly
- REST: `GET /wp-json/wbam/v1/ads/serve?placement=header&post_id=42`

### Code path
```
Shortcode_Placement::shortcode_single($atts)
  → Placement_Engine::render_ad($ad_id, $context)
    → apply_filters('wbam_should_display_ad', true, $ad_id, $context)
       (Pro: Frequency_Cap_Pro@20, Campaign_Pacing@30 hook here)
    → Targeting_Engine evaluates rules (geo, device, frequency)
    → AdType handler renders HTML
       (e.g. Code_Ad::render() → optional sandbox iframe via wbam_code_ad_use_sandbox)
    → apply_filters('wbam_ad_output', $html, $ad_id, $context)
       (Pro: Track_Impression wraps with click-tracking pixel)
  → Echo HTML to page
  → Frontend JS observes element → calls AJAX wbam_track_click on click
```

### Key files
| File | Lines | Role |
|---|---|---|
| `includes/Modules/Placements/class-placement-engine.php` | full | Resolves placement → candidate ads → renders |
| `includes/Modules/Placements/class-shortcode-placement.php` | 51 | Wires `[wbam_ad]` and `[wbam_ads]` to engine |
| `includes/Modules/AdTypes/class-{type}-ad.php` | each | Renders one ad type |
| `includes/Modules/Targeting/class-targeting-engine.php` | full | Geo/device/page rule eval |
| `assets/js/frontend.js` | full | Lazy load + click handler |

### Permissions
- **Roles**: any (public)
- **Nonce**: not required for render; required for click tracking AJAX
- **Trust gates**: rate-limited tracking endpoints only

---

## Flow 2: Track a click (impression handled by JS event listener separately)

**Entry**: User clicks ad → frontend JS fires AJAX `wbam_track_click` (or REST `/ads/track`)

### Code path
```
Frontend JS click handler
  → POST /wp-admin/admin-ajax.php?action=wbam_track_click
     body: { nonce, ad_id, event_type: 'click', placement }
  → Frontend::handle_click_tracking()
    → check_ajax_referer('wbam_frontend')
    → Frontend::record_analytics($ad_id, 'click', $placement)
      → INSERT INTO {prefix}wbam_analytics (ad_id, event_type, placement, created_at)
    → do_action('wbam_ad_clicked', $ad_id, $placement)
      (Pro: Campaign_Pacing decrements budget here)
  → Returns wp_send_json_success()
```

### REST equivalent
```
POST /wp-json/wbam/v1/ads/track  (or /analytics/track)
  → Ads_API::track_event()
    → check_rate_limit('rest_track_'.md5($ip), 60, 60)  // 60/min
    → Frontend::record_analytics($ad_id, $event_type, $placement)
    → do_action('wbam_rest_event_tracked', $ad_id, $event_type, $placement)
```

### Key files
| File | Lines | Role |
|---|---|---|
| `includes/Frontend/class-frontend.php` | 38–41 | AJAX hooks |
| `includes/API/class-ads-api.php` | 495–527 | REST tracking + rate limit |

---

## Flow 3: Email capture form submission

**Entry**: User submits inline email form rendered by an `email-capture` ad type

### Code path
```
User submits form (frontend)
  → POST /wp-admin/admin-ajax.php?action=wbam_email_capture
     body: { nonce, email, name, ad_id, consent }
  → Frontend::handle_email_capture()
    → check_ajax_referer('wbam_frontend')
    → apply_filters('wbam_email_form_validation', $errors, $data)
    → do_action('wbam_email_form_submission_before', $data)
    → INSERT INTO {prefix}wbam_email_submissions
    → do_action('wbam_email_captured', $data)
    → do_action('wbam_email_form_submission_after', $data, $insert_id)
    → Returns success message (filterable via wbam_email_capture_success_message)
```

### Key files
| File | Lines | Role |
|---|---|---|
| `includes/Frontend/class-frontend.php` | 40 | AJAX hook |
| `includes/Modules/AdTypes/class-email-capture-ad.php` | full | Form template |
| `assets/js/frontend.js` | full | Form submit handler |
| `templates/email-form*` | n/a | Form template hooks (wbam_email_form_*) |

---

## Flow 4: Link cloaking + click tracking

**Entry**: User visits cloaked link `/go/{slug}` (rewrite handled in `init`)

### Code path
```
Request /go/{slug}
  → init action (priority 10)
  → Link_Cloaker checks if path matches
    → Link_Manager::get_by_slug($slug)
    → if !found:
        do_action('wbam_link_not_found', $slug)
        404
    → if status != 'active':
        do_action('wbam_inactive_link_accessed', $link)
        redirect to home
    → do_action('wbam_before_link_redirect', $link, $request)
    → INSERT INTO {prefix}wbam_link_clicks (visitor_hash, referrer, clicked_at)
    → do_action('wbam_link_clicked', $link_id, $link)
    → wp_safe_redirect( apply_filters('wbam_link_redirect_url', $link->destination_url, $link), apply_filters('wbam_link_redirect_type', 302, $link))
```

### Alternative: REST or AJAX click tracking (no redirect — used by tracking pixels)
```
POST /wp-json/wbam/v1/links/{id}/track  (or AJAX wbam_track_link_click)
  → rate-limited 30/min/IP
  → INSERT click row
  → do_action('wbam_link_clicked', $id, $link)
```

### Key files
| File | Lines | Role |
|---|---|---|
| `includes/Modules/Links/class-link-cloaker.php` | full | Slug → 302 redirect |
| `includes/Modules/Links/class-link-manager.php` | full | CRUD + queries |
| `includes/Modules/Links/class-links-module.php` | 114 | AJAX track handler |
| `includes/API/class-links-api.php` | 558–605 | REST track endpoint |

---

## Flow 5: Partnership inquiry form

**Entry**: User submits `[wbam_partnership_inquiry]` shortcode form on a public page

### Code path
```
User submits form
  → AJAX wbam_submit_partnership
  → Partnership_Form::handle_ajax_submission()
    → check_ajax_referer('wbam_partnership_form')
    → apply_filters('wbam_partnership_form_data', $sanitized_data)
    → apply_filters('wbam_partnership_form_validation', $errors, $data)
    → check duplicate window: filter wbam_partnership_form_duplicate_hours
    → do_action('wbam_partnership_form_submission_before', $data)
    → Partnership_Manager::create($data)
      → INSERT INTO {prefix}wbam_link_partnerships
      → do_action('wbam_partnership_created', $id, $partnership)
    → Partnership_Emails::send_admin_notification($partnership)
      → wp_mail with filterable headers/message
    → do_action('wbam_partnership_form_submission_after', $data, $id)
    → Returns success with filtered message
```

### Admin acceptance flow
```
Admin opens Partnerships page (wbam-partnerships)
  → PUT /wp-json/wbam/v1/partnerships/{id} { status: 'approved' }
  → Links_API::update_partnership() — manage_options
  → Partnership_Manager::update($id, ['status'=>'approved'])
    → do_action('wbam_partnership_accepted', $id, $partnership)
  → Partnership_Emails::send_accepted_notification($partnership)
```

---

## Flow 6: Setup wizard → first-install onboarding

**Entry**: Plugin activation triggers redirect to `?page=wbam-setup`

### Code path
```
Plugin activate hook
  → Installer::install() — creates 7 tables via dbDelta
  → set_transient('_wbam_activation_redirect', 1, 30)
  → on next admin_init:
    → Plugin::activation_redirect()
    → if !wbam_setup_complete && !wbam_setup_dismissed:
       redirect to admin.php?page=wbam-setup

Setup wizard render
  → do_action('wbam_setup_wizard_ready_before')
  → render steps: filter wbam_setup_wizard_steps
  → on submit:
    → do_action('wbam_setup_wizard_sample_save_before', $form_data)
    → save options
    → optionally create sample ad → do_action('wbam_setup_wizard_sample_ad_created', $ad_id)
    → do_action('wbam_setup_wizard_sample_save_after', $form_data)
       (Pro: save_pro_options() listens here to sync advertiser-flag option)
  → On final step: do_action('wbam_setup_wizard_complete')
```

---

## Flow 7: Settings save (admin or REST)

**Entry**: Admin Settings page form OR REST PUT /settings

### Code path (REST)
```
PUT /wp-json/wbam/v1/settings { settings: {...} }
  → Settings_API::check_admin_permission() → manage_options
  → Settings_Helper::get() (reads wbam_settings option)
  → array_merge with sanitized incoming
  → update_option('wbam_settings', $merged)
  → response with sanitized output
```

### Code path (admin form)
```
POST /wp-admin/admin.php?page=wbam-settings
  → check_admin_referer('wbam_settings')
  → Settings::save() sanitizes + update_option
```

### Display options subset
- Same flow, but filtered to only the 8 keys in `Settings_API::$display_keys`:
  - `disable_frontend_css, lazy_load_ads, ad_label, ad_label_text, ad_container_class, responsive_ads, hide_on_mobile, hide_on_tablet`
