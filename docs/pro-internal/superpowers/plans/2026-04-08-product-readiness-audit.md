# WB Ad Manager Pro — Product Readiness Audit & Gap Plan

**Date:** 2026-04-08
**Version:** 1.5.0 (dev branch)
**DB Version:** 3.0.0

---

## Overall Verdict: 85-90% Complete

Core features work well. 72 REST endpoints registered. 18 modules. 26 DB tables. 41 email templates. 5 payment gateways. All 14 portal tabs production-ready.

**Critical gaps:** Revenue dashboard UI missing, 45+ legacy admin-ajax handlers, membership REST endpoints missing, inconsistent CRUD paths.

---

## 1. REST API Coverage

### What's Good (72 endpoints)
- Campaigns: FULL CRUD + pause/resume/duplicate/stats + admin approve/reject
- Packages: FULL CRUD + reorder + create-defaults
- Classifieds: FULL CRUD + renew/bump/contact + admin approve/reject
- Wallet: balance/transactions/add-funds/confirm-payment/payment-methods
- Analytics: overview/chart/top-ads/geo/devices/export
- Custom Fields: FULL CRUD + reorder
- Rotation: share/log/export + admin settings
- Messaging: threads/reply/read
- Reviews: list/submit + admin moderate

### What's Missing (REST gaps)

| Entity | Missing REST Endpoints | Priority |
|--------|----------------------|----------|
| **Membership Plans** | No REST at all — only admin-ajax | HIGH |
| **Subscriptions** | No REST at all — only admin-ajax | HIGH |
| **Advertiser CRUD** | Only profile read/update — no admin list/create/delete | MEDIUM |
| **Transaction Management** | No approve/reject via REST — admin page only | MEDIUM |
| **Ad Submissions** | Only admin-ajax (wbam_submit_ad) — no REST | MEDIUM |
| **Message Deletion** | Can't delete threads via REST | LOW |
| **Review Deletion** | Admin can't delete reviews via REST | LOW |

### Legacy admin-ajax (45+ handlers — should migrate to REST)

**Classified handlers (15):** submit, update, delete, mark_sold, reactivate, bump, renew, inquiry, purchase_upgrade, upgrade_to_featured, get_featured_pricing, mark_inquiry_read, reply_inquiry, toggle_favorite, toggle_follow_seller, report

**Messaging (4):** send_message, get_thread, get_threads, mark_thread_read

**Membership (2):** subscribe_plan, cancel_subscription

**Analytics (4):** track_event, get_analytics, export_analytics, export_revenue

**Links Pro (8):** scan_posts_batch, run_health_check, recheck_link, dismiss_link, fix_redirect, get_analytics_data, preview_keyword_replacement, bulk_test_keywords

**Other (12):** submit_ad, update_ad, save_custom_fields, get_custom_fields, delete_custom_field, submit_review, report_admin_action, ab_test_action, import_demo_data, delete_demo_data, save_revenue_settings, bulk_link_action

### Duplicate CRUD Paths (REST + admin-ajax)

| Operation | REST Endpoint | Admin-Ajax | Action Needed |
|-----------|--------------|------------|---------------|
| Submit Classified | POST /my/classifieds | wbam_submit_classified | Deprecate ajax |
| Update Classified | PUT /my/classifieds/{id} | wbam_update_classified | Deprecate ajax |
| Delete Classified | DELETE /my/classifieds/{id} | wbam_delete_classified | Deprecate ajax |
| Bump Classified | POST /classifieds/{id}/bump | wbam_bump_classified | Deprecate ajax |
| Renew Classified | POST /classifieds/{id}/renew | wbam_renew_classified | Deprecate ajax |
| Contact Seller | POST /classifieds/{id}/contact | wbam_classified_inquiry | Deprecate ajax |
| Send Message | POST /messages/threads/{id}/reply | wbam_send_message | Deprecate ajax |
| Submit Review | POST /reviews | wbam_submit_review | Deprecate ajax |

### Admin Settings (all use form POST — no REST)
- Settings form uses `register_setting()` + `options.php` — full page reload
- **Action:** Create `PATCH /wbam-pro/v1/settings` endpoint for AJAX save

---

## 2. Module Completeness

### Documented in CLAUDE.md (13 modules)
Wallet, Campaigns, Advertisers, Ad Submissions, Packages, Classifieds, Payments, Analytics, AB Testing, Rotation, Links, BuddyPress, Notifications

### Actually exist but NOT in CLAUDE.md (5 modules)
- **Custom Fields** — dynamic field definitions for classifieds
- **Geolocation** — lat/lng for classified locations
- **Messaging** — buyer/seller direct messaging threads
- **Reviews** — seller rating system
- **Memberships** — subscription plans with listing limits

### Module File Completeness

| Module | Manager | Model | REST API | Admin | Templates | Verdict |
|--------|---------|-------|----------|-------|-----------|---------|
| Wallet | Y | Y (Transaction) | Y | Y (List Table) | Y | COMPLETE |
| Campaigns | Y | Y | Y | Y (List Table) | Y | COMPLETE |
| Advertisers | Y | Y | Y (partial) | Y (List Table) | Y | NEEDS: admin REST |
| Ad Submissions | Y | Y | N (ajax only) | Y (List Table) | Y | NEEDS: REST API |
| Packages | Y | Y | Y | Y (List Table) | N/A | COMPLETE |
| Classifieds | Y | Y | Y | Y (List + Meta Box) | Y | COMPLETE |
| Payments | N (handlers) | N | N | N | N | HANDLER-BASED |
| Analytics | Y (Tracker) | N | Y | Y (Dashboard) | N/A | COMPLETE |
| AB Testing | Y | Y | N | Y | N/A | NEEDS: REST API |
| Rotation | Y (Engine) | N | Y | Y | N/A | COMPLETE |
| Links | Y | N | N | N | N/A | FREE PLUGIN DEP |
| BuddyPress | N (Integration) | N | N | N | N/A | INTEGRATION |
| Notifications | Y (Email) | N | N | N | Y (41 templates) | COMPLETE |
| Memberships | Y | N | N | N | Y | NEEDS: REST API |
| Custom Fields | Y | N | Y | N | N/A | COMPLETE |
| Geolocation | Y | N | N | N | N/A | MINIMAL |
| Messaging | Y | N | Y (partial) | N | Y | NEEDS: delete/admin |
| Reviews | Y | N | Y (partial) | N | N/A | NEEDS: read/delete |

---

## 3. User-Facing Feature Gap Analysis

### Portal (Frontend) — What Users See

| Tab | Status | Gap |
|-----|--------|-----|
| Overview | WORKING | None |
| My Ads | WORKING | None — ad wizard with placement selector now working |
| Campaigns | WORKING | None |
| Classifieds | WORKING | None |
| Inquiries | WORKING | None |
| Favorites | WORKING | None |
| Following | WORKING | None |
| Links | PARTIAL | Depends on free plugin — fragile |
| Messages | WORKING | Polling (15s) not real-time — scale concern |
| Wallet | WORKING | None |
| Membership | WORKING | 5 QA bugs fixed today |
| Analytics | WORKING | Charts empty without data (expected) |
| Share of Voice | WORKING | Requires rotation module |
| Profile | WORKING | None — country dropdown fixed today |

### Admin — What Site Owners See

| Feature | Status | Gap |
|---------|--------|-----|
| Advertiser Management | WORKING | No bulk operations |
| Campaign Management | WORKING | None |
| Ad Approvals | WORKING | None |
| Package Management | WORKING | None |
| Transaction Management | WORKING | No refund initiation UI |
| Classified Management | WORKING | None |
| Settings | WORKING | Page reloads on save (no AJAX) |
| Setup Wizard | WORKING | None |
| Audit Log | WORKING | None |
| **Revenue Dashboard** | **MISSING** | Code exists but NO admin page renders it |
| **Bulk Import/Export** | **MISSING** | No bulk advertiser/campaign/classified import |
| **Refund Management** | **MISSING** | No admin UI to initiate refunds |
| **Advanced Filtering** | **MISSING** | No date/amount range filters on admin lists |

### Payment Gateways

| Gateway | Status | Notes |
|---------|--------|-------|
| Stripe | WORKING | Full: intents, webhooks, refunds, test/live |
| PayPal | WORKING | Full: sessions, verification, refunds |
| Razorpay | WORKING | Full: orders, signatures, refunds |
| WooCommerce | WORKING | Full: virtual products, order completion |
| Manual/Bank | WORKING | Full: pending→approve/cancel flow |

### Email Notifications — 41 templates, all working
- Account lifecycle (6): welcome, approved, rejected, suspended, banned, reactivated
- Ads (5): submitted, approved, rejected, paused, changes-requested
- Classifieds (5): submitted, approved, rejected, expiring, follower notification
- Inquiries (2): received, reply
- Wallet (2): credited, low balance
- Campaigns (3): started, completed, budget low
- Payments (3): receipt, approved, rejected
- Memberships (4): created, renewed, expiring, expired
- Reviews (3): submitted, approved, rejected
- Messages (1): new message notification
- System (7): admin alerts, notifications, etc.

---

## 4. Priority Action Plan

### P0 — Ship Blockers (fix before release)

- [x] ~~Profile tab fatal (wbam_get_countries)~~ — Fixed 2026-04-08
- [x] ~~Classified billing not charging wallet~~ — Fixed 2026-04-08
- [x] ~~Pending advertiser access control~~ — Fixed 2026-04-08
- [x] ~~Ad wizard missing JS handler + placement filtering~~ — Fixed 2026-04-08
- [x] ~~5 membership QA bugs~~ — Fixed 2026-04-08
- [x] ~~Status badges invisible (CSS)~~ — Fixed prior
- [x] ~~Rotation settings not saving~~ — Fixed prior
- [ ] **Revenue Dashboard admin page** — code exists, needs admin UI rendering (backend report only for site admin — frontend earnings view can be planned later using same data)
- [ ] **Migrate 45+ admin-ajax handlers to REST** — mandatory for security + consistency (see Section 1)

### P1 — High Priority (next sprint)

- [ ] **Wbcom Credits SDK integration** (Basecamp card #9764754643) — this one change covers:
  - Wallet_Manager replacement (credit/debit/balance/transactions)
  - Membership billing via WooCommerce Subscriptions adapter (built-in)
  - Single payment + recurring subscription handling via WC/PMPro/MemberPress adapters
  - Stripe + PayPal direct gateways (SDK GatewayInterface — build once, all plugins benefit)
  - REST API for balance/history/topup (SDK provides these out of the box)
  - Transaction management (append-only ledger replaces custom Transaction model)
  - Hold→Deduct→Refund lifecycle (maps to ad/classified approval flow)
  - **Cross-plugin reuse:** same SDK powers credits in all Wbcom plugins
- [ ] Create REST endpoints for Ad Submissions (migrate from admin-ajax)
- [ ] Migrate settings save to AJAX (no page reload)
- [ ] Add admin refund initiation UI
- [ ] Update CLAUDE.md for 5 undocumented modules

### P2 — Medium Priority (upcoming sprints)

- [ ] Create Advertiser admin REST CRUD endpoints
- [ ] Add AB Testing REST endpoints
- [ ] Add admin bulk import/export for advertisers/campaigns
- [ ] Add advanced date/amount filtering on admin list tables
- [ ] Add REST API documentation (OpenAPI/Swagger)

### P3 — Nice to Have (future)

- [ ] Real-time messaging (WebSocket/SSE instead of 15s polling)
- [ ] Multi-language .po/.mo translation files
- [ ] Admin performance reports per advertiser
- [ ] Campaign budget alerts via webhook/SMS
- [ ] Two-factor auth for advertiser accounts

---

## 5. Wbcom Credits SDK — What It Replaces (Basecamp Card #9764754643)

**Current:** 7 wallet classes (~3000 lines) + 5 payment handlers + custom transaction system
**Target:** Wbcom Credits SDK — reusable across ALL Wbcom plugins

### Why SDK First (covers multiple P1/P2 items at once)

The SDK isn't just a wallet replacement. It's a **unified credit/billing platform** that eliminates:

| Current Problem | SDK Solution |
|----------------|--------------|
| Custom Wallet_Manager (3000 lines) | `Credits::topup/hold/deduct/refund()` — 1 line each |
| Custom Transaction model + DB table | SDK append-only Ledger (auto-created per plugin) |
| Custom Wallet REST API (5 endpoints) | SDK REST API built-in (/balance, /history, /topup) |
| Custom Stripe_Integration | SDK GatewayInterface — implement once at SDK level |
| Custom WooCommerce_Integration | SDK WooCommerce adapter (built-in, handles orders + subscriptions) |
| Custom membership billing logic | SDK WC Subscriptions + PMPro + MemberPress adapters (built-in) |
| Membership REST endpoints (missing) | SDK REST covers balance/history — membership logic stays in plugin |
| 8 duplicate CRUD paths (REST + ajax) | SDK standardizes credit operations — only one path |
| Per-plugin payment code duplication | Build gateway ONCE in SDK — all plugins use it |

### Architecture Principle: ZERO Payment Code in Plugin

**The plugin has NO payment system.** It only understands credits.

```
┌─────────────────────────────────────────────────┐
│ Wbcom Credits SDK (shared across ALL plugins)   │
│                                                 │
│ Credit Operations:                              │
│   Credits::topup/hold/deduct/refund/get_balance │
│                                                 │
│ How Users Get Credits (SDK handles ALL of this):│
│   - Admin manual topup                          │
│   - WooCommerce one-time purchase               │
│   - WC Subscriptions (recurring monthly/yearly) │
│   - PMPro / MemberPress membership levels       │
│   - Stripe direct checkout (SDK gateway)        │
│   - PayPal direct checkout (SDK gateway)        │
│   - Any future gateway via GatewayInterface     │
│                                                 │
│ REST API (SDK provides):                        │
│   GET /balance, GET /history, POST /topup       │
│                                                 │
│ Frontend (SDK provides):                        │
│   Buy credits page, balance widget, history     │
└──────────────────┬──────────────────────────────┘
                   │ Credits::hold/deduct/refund
                   ▼
┌─────────────────────────────────────────────────┐
│ WB Ad Manager Pro (this plugin)                 │
│                                                 │
│ Plugin ONLY knows:                              │
│   - How many credits things cost                │
│   - When to hold (on submit)                    │
│   - When to deduct (on approve)                 │
│   - When to refund (on reject/cancel)           │
│                                                 │
│ Plugin does NOT know:                           │
│   - How users buy credits (Stripe/PayPal/WC)    │
│   - Payment processing, webhooks, refund APIs   │
│   - Subscription renewal logic                  │
│   - Gateway configuration or API keys           │
└─────────────────────────────────────────────────┘
```

**What plugin keeps (credit consumption only):**
- `Billing_Manager` cron (hourly campaign billing) → `Credits::deduct()`
- Campaign budget reservation → `Credits::hold()` on activate, `deduct()` on complete, `refund()` on cancel
- Ad submission billing → consumer: hold on submit, deduct on approve, refund on reject
- Classified billing → same consumer pattern
- Membership plan logic (limits, switching) — cost charged via `Credits::deduct()`
- Frontend wallet/credits tab → queries SDK REST API
- Admin transactions page → queries SDK ledger

**What plugin REMOVES entirely (SDK owns this):**
- ALL payment gateway code (Stripe, PayPal, Razorpay, WooCommerce handlers)
- ALL wallet top-up logic (add-funds modal, payment intents, webhooks)
- ALL payment configuration UI (Stripe keys, PayPal settings)
- Manual payment approval flow (SDK admin topup replaces this)

### Consumer Registration (how the plugin integrates)

```
Plugin fires WordPress actions → SDK handles the credit lifecycle:

Ad submitted      → do_action('wbam_ad_submitted', $post_id)       → SDK: Credits::hold()
Ad approved       → do_action('wbam_ad_approved', $post_id)        → SDK: Credits::deduct()
Ad rejected       → do_action('wbam_ad_rejected', $post_id)        → SDK: Credits::refund()

Classified submit → do_action('wbam_classified_submitted', $id)    → SDK: Credits::hold()
Classified approve→ do_action('wbam_classified_approved', $id)     → SDK: Credits::deduct()
Classified reject → do_action('wbam_classified_rejected', $id)     → SDK: Credits::refund()

Campaign activate → do_action('wbam_campaign_activated', $id)      → SDK: Credits::hold()
Campaign complete → do_action('wbam_campaign_completed', $id)      → SDK: Credits::deduct()
Campaign cancel   → do_action('wbam_campaign_cancelled', $id)      → SDK: Credits::refund()
```

### Files to Remove After Migration (entire Wallet + Payments modules)

**Wallet module (entire directory):**
- `includes/Modules/Wallet/class-wallet-manager.php` (~800 lines)
- `includes/Modules/Wallet/class-transaction.php` (~200 lines)
- `includes/Modules/Wallet/class-wallet-api.php` (~400 lines)
- `includes/Modules/Wallet/class-stripe-integration.php` (~500 lines)
- `includes/Modules/Wallet/class-woocommerce-integration.php` (~400 lines)
- `includes/Modules/Wallet/class-billing-manager.php` — KEEP but refactor to use `Credits::deduct()`

**Payments module (entire directory):**
- `includes/Modules/Payments/class-stripe-handler.php`
- `includes/Modules/Payments/class-paypal-handler.php`
- `includes/Modules/Payments/class-razorpay-handler.php`
- `includes/Modules/Payments/class-woocommerce-handler.php`

**Settings UI (payment config sections):**
- Remove Stripe settings tab from Pro Settings
- Remove PayPal settings tab from Pro Settings
- Remove Razorpay settings from Pro Settings
- Remove WooCommerce payment settings
- Remove manual/bank transfer settings
- Replace with: "Configure credits at [SDK Settings Page]" link

**Frontend (wallet top-up UI):**
- Remove "Add Funds" modal from wallet tab (SDK handles purchase flow)
- Remove payment method selection UI
- Keep: balance display, transaction history (from SDK REST)

**DB:** `wbam_transactions` table → migrated to SDK `wbam_credit_ledger`

**Estimated code removal:** ~4000+ lines of payment/wallet code replaced by ~50 lines of SDK consumer registration

### Phased Execution
1. Bundle SDK + register 3 consumers (ad, classified, campaign)
2. Create `WbamCreditsAdapter` (advertiser_id → user_id mapping)
3. Replace all `Wallet_Manager` calls with `Credits::` (behind feature flag)
4. Implement Stripe + PayPal as SDK GatewayInterface (shared across plugins)
5. Data migration script (existing `wbam_transactions` → SDK ledger)
6. Remove old wallet/payment code + DB table
7. Update frontend wallet tab to use SDK REST API

---

## 6. Metrics Snapshot

| Metric | Count |
|--------|-------|
| REST Endpoints | 72 |
| Admin-Ajax Handlers | 45+ |
| Modules | 18 |
| DB Tables | 26 |
| Email Templates | 41 |
| Payment Gateways | 5 |
| Portal Tabs | 14 |
| Admin List Tables | 8 |
| CSS Files | 5 (portal, classified, backend, setup-wizard, membership) |
| JS Files | 8+ (portal, classified, backend, membership, ad-submission, etc.) |

---

*This plan should be reviewed and updated after each sprint. Check off items as completed.*
