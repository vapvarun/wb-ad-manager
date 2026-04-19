# WB Ad Manager Pro v1.5.0 — QA Audit Checklist

Organized by functional area. Each group is independently testable.

---

## Group 1: Core Ad Management (Admin)
**Admin pages:** WB Ad Manager > All Ads, Add New Ad, Ad Settings

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1.1 | Create ad (all types) | Admin > Add New Ad > create Image, Code, Rich Text ad | Ad saved, preview works |
| 1.2 | Edit existing ad | Edit ad title, content, placement | Changes persist |
| 1.3 | Ad placements display | Create ad with each placement (header, footer, content, sidebar, popup, sticky, archive, paragraph, shortcode) | Ad renders in correct position on frontend |
| 1.4 | BuddyPress placements | Create ad with BP Activity + BP Directory placements | Ad shows in activity stream and member directory |
| 1.5 | Ad scheduling | Set start/end dates | Ad shows only within date range |
| 1.6 | Ad targeting | Set page/post targeting rules | Ad only appears on targeted pages |
| 1.7 | Disable ad | Toggle ad to disabled | Ad stops rendering on frontend |

---

## Group 2: Advertiser Portal (Frontend)
**URL:** /advertiser-dashboard/
**Tabs:** Overview, My Ads, Campaigns, Classifieds, Inquiries, Favorites, Following, Buy Links, Messages, Wallet, Membership, Analytics, Share of Voice, Profile

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 2.1 | Dashboard overview | Login as advertiser > visit dashboard | Stats cards show: Active Ads, Impressions, Clicks, Spent, Balance |
| 2.2 | Quick actions | Click each quick action button | "Create New Ad", "Post Classified", "Add Funds" navigate correctly |
| 2.3 | Tab navigation | Click each sidebar tab | Each tab loads its content without errors |
| 2.4 | Responsive layout | View dashboard at 390px, 768px, 1024px | Sidebar collapses, content stacks properly |
| 2.5 | Non-advertiser access | Visit dashboard as subscriber (no advertiser role) | Registration prompt or access denied — not a broken page |

---

## Group 3: Ad Submissions & Approval Workflow
**Admin:** WB Ad Manager > Submissions
**Frontend:** Advertiser Dashboard > My Ads > Create New Ad

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 3.1 | Submit ad (frontend) | Advertiser > My Ads > Create New Ad > select package > fill form > submit | Submission created, status "Pending Review" |
| 3.2 | Admin review queue | Admin > Submissions | Pending submissions listed with details |
| 3.3 | Approve submission | Admin clicks Approve | Ad goes live, advertiser gets "Ad Approved" email |
| 3.4 | Reject submission | Admin clicks Reject + adds reason | Ad rejected, advertiser gets "Ad Rejected" email with reason |
| 3.5 | Request changes | Admin clicks "Request Changes" + adds notes | Advertiser gets email, can edit and resubmit |
| 3.6 | Rejected status in portal | After rejection, advertiser views My Ads | Rejected badge shows (not "Active") |
| 3.7 | Trust auto-approval | Enable trust system, approve 2 ads for advertiser, submit 3rd | 3rd ad auto-approved (if trust_approvals_required=2) |
| 3.8 | Module disabled guard | Disable Ad Submissions module > try to submit via URL | Blocked with appropriate message |

---

## Group 4: Campaigns
**Admin:** WB Ad Manager > Campaigns
**Frontend:** Advertiser Dashboard > Campaigns

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 4.1 | Create campaign (frontend) | Advertiser > Campaigns > Create Campaign | Campaign created as draft |
| 4.2 | Campaign activation | Activate campaign with sufficient balance | Status changes to Active, budget reserved |
| 4.3 | Campaign pause/resume | Pause active campaign, then resume | Budget held during pause, no re-reservation on resume |
| 4.4 | Campaign completion | Campaign reaches impression/click limit | Status auto-changes to Completed, refund if applicable |
| 4.5 | Campaign pagination | Create 11+ campaigns > click page 2 / Next | Page 2 loads with correct campaigns |
| 4.6 | Module disabled | Disable Campaigns module > visit Campaigns tab as advertiser | Tab hidden, direct URL shows disabled message, create blocked server-side |
| 4.7 | Admin campaigns list | Admin > Campaigns | All campaigns listed with status, advertiser, budget |

---

## Group 5: Classifieds Marketplace
**Admin:** Classifieds > All Classifieds, Categories, Locations
**Frontend:** Advertiser Dashboard > Classifieds, /classifieds/ browse page

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 5.1 | Post classified (wizard) | Advertiser > Classifieds > Post New > Step 1-6 wizard | Classified created with all fields |
| 5.2 | Image upload in wizard | Upload 3 images in Step 2 | Images appear in preview AND in final listing |
| 5.3 | Edit classified | Edit existing classified > change title, images, price | Changes persist, no PHP warnings |
| 5.4 | Classified categories/locations | Select category + location during creation | Classified appears in correct category/location archive |
| 5.5 | Browse classifieds | Visit /classifieds/ page | Grid of active classifieds with images, prices, filters |
| 5.6 | Single classified page | Click a classified | Full details with gallery, contact form, seller info |
| 5.7 | Classified inquiry | Submit contact form on classified | Inquiry saved to DB, seller gets HTML email notification |
| 5.8 | Classified approval | Admin approves pending classified | Classified goes live, advertiser gets email |
| 5.9 | Classified rejection | Admin rejects classified with reason | Advertiser gets email with reason |
| 5.10 | Classified expiration | Classified reaches expiry date | Status changes, advertiser gets expiring warning email |
| 5.11 | Favorites | Click favorite on a classified | Appears in Favorites tab |
| 5.12 | Following sellers | Follow a seller | New listings from seller trigger follower email |
| 5.13 | Classified packages | Select different packages (Free/Standard/Premium) | Package limits (duration, features) applied correctly |

---

## Group 6: Wallet & Payments
**Admin:** Advertisers > Transactions
**Frontend:** Advertiser Dashboard > Wallet

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 6.1 | Add funds (manual/bank transfer) | Advertiser > Wallet > Add Funds > Manual | Pending transaction created, admin notified |
| 6.2 | Admin approve payment | Admin > Transactions > Approve pending payment | Balance credited, advertiser gets "Wallet Credited" email |
| 6.3 | Admin cancel payment | Admin > Transactions > Cancel pending payment | Transaction cancelled, balance unchanged |
| 6.4 | Transaction status display | View wallet transactions list | Each transaction shows status badge (Completed/Pending/Failed) |
| 6.5 | Transaction receipt | Click receipt link on completed transaction | Receipt page renders with details |
| 6.6 | Low balance alert | Set threshold=100, balance drops below 100 | Advertiser gets "Low Balance Alert" email |
| 6.7 | Stripe payment (if configured) | Add funds via Stripe | Payment processed, balance credited, no duplicate credits |
| 6.8 | WooCommerce integration (if configured) | Purchase via WooCommerce | Wallet credited with idempotency |

---

## Group 7: Packages
**Admin:** Advertisers > Packages

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 7.1 | Create package | Admin > Packages > Add New > fill all fields | Package saved with name, price, duration, limits |
| 7.2 | Allowed placements | Create package > check BuddyPress Activity + Directory | Both placements available in checkbox list |
| 7.3 | Package placement enforcement | Advertiser selects package with limited placements > creates ad | Ad only displays in allowed placements |
| 7.4 | Pricing models | Create Flat, CPC, CPM packages | Each pricing model calculates costs correctly |
| 7.5 | Package status | Deactivate a package | Package no longer available for selection on frontend |
| 7.6 | Frontend package selection | Advertiser > create ad > Step 4 (Package) | Available packages displayed with prices and features |

---

## Group 8: Membership Plans
**Admin:** Advertisers > Membership Plans
**Frontend:** Advertiser Dashboard > Membership

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 8.1 | Create membership plan | Admin > Membership Plans > Add New | Plan with name, price, listing limits, billing cycle |
| 8.2 | Subscribe to plan | Advertiser > Membership > select paid plan | Wallet debited, subscription active |
| 8.3 | Plan limits | Post classifieds up to plan limit | Blocked when limit reached with upgrade message |
| 8.4 | Plan switching | Switch from Basic to Pro plan | Old subscription cancelled, new one active |
| 8.5 | Cancel subscription | Advertiser cancels subscription | Status changes, features limited |
| 8.6 | Renewal emails | Subscription approaching renewal | Email notification sent |

---

## Group 9: Reviews & Ratings
**Admin:** Classifieds > Reviews
**Frontend:** Single classified page

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 9.1 | Submit review | Visit classified > rate seller (1-5 stars) + review text | Review submitted, admin notified |
| 9.2 | Review moderation | Admin > Reviews > approve/reject | Approved reviews visible, rejected hidden |
| 9.3 | Seller rating display | View seller profile | Average star rating calculated and displayed |

---

## Group 10: Private Messaging
**Frontend:** Advertiser Dashboard > Messages, Single classified page

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 10.1 | Send direct message | Visit classified > Send Direct Message | Thread created, seller gets email |
| 10.2 | Reply in thread | Seller opens Messages > reply | Reply appended, buyer gets email |
| 10.3 | Unread badge | New message arrives | Unread count badge on Messages tab |
| 10.4 | Thread privacy | Non-participant tries to access thread URL | Access denied |

---

## Group 11: Geolocation & Maps
**Frontend:** Classified creation/browsing

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 11.1 | Location picker | Create classified > use map/address input | Lat/lng saved to classified |
| 11.2 | Map on single listing | View classified with location | Map with marker displayed |
| 11.3 | Radius search | Browse classifieds with geo_lat/geo_lng/geo_radius params | Only nearby classifieds shown |
| 11.4 | "Use my location" | Click browser geolocation button | Current location populated |

---

## Group 12: Analytics & A/B Testing
**Admin:** WB Ad Manager > Analytics, A/B Testing
**Frontend:** Advertiser Dashboard > Analytics, Share of Voice

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 12.1 | Impression tracking | View page with ad | Impression count increments |
| 12.2 | Click tracking | Click an ad | Click count increments, redirect works |
| 12.3 | Analytics dashboard (admin) | Admin > Analytics | Charts and stats render |
| 12.4 | Advertiser analytics | Advertiser > Analytics tab | Per-ad stats visible |
| 12.5 | A/B test creation | Admin > A/B Testing > create test | Test created with variants |
| 12.6 | A/B test display | Visit page with A/B test ad | Variant randomly selected |

---

## Group 13: Ad Rotation
**Admin:** Pro Settings > Ad Rotation

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 13.1 | Enable rotation | Pro Settings > Ad Rotation > enable | Rotation engine active |
| 13.2 | Equal share model | Multiple ads on same placement | Each ad gets approximately equal impressions |
| 13.3 | Budget weighted model | High-budget vs low-budget campaign on same placement | Higher budget gets proportionally more impressions |
| 13.4 | Rotation settings save | Change model, reset period, save | Settings persist after page reload |

---

## Group 14: Links Pro
**Admin:** Links > All Links, Categories, Partnerships, Analytics, Keywords, Health, Import

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 14.1 | Create link | Admin > Links > Add New | Link created with tracking |
| 14.2 | Link tracking | Click tracked link | Click recorded in link analytics |
| 14.3 | Link health check | Admin > Links > Health | Broken links detected |
| 14.4 | Auto-linking keywords | Configure keyword > auto-link | Keywords in content auto-linked |
| 14.5 | Buy Links (frontend) | Advertiser > Buy Links tab | Available link packages displayed |

---

## Group 15: Email Notifications
**Admin:** Pro Settings > Emails

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 15.1 | Email enable/disable toggles | Toggle each email type on/off | Disabled emails don't send |
| 15.2 | HTML template rendering | Trigger any email | Email renders with styled header/footer, logo, primary color |
| 15.3 | Theme override | Copy template to theme/wb-ad-manager-pro/emails/ > modify | Modified version used |
| 15.4 | All email types send | Trigger each: ad approved, rejected, submitted, classified approved/rejected, wallet credited, low balance, campaign started/completed, inquiry, follower notification | Each sends correctly with proper data |

---

## Group 16: BuddyPress Integration
**Requires:** BuddyPress plugin active

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 16.1 | Activity stream ads | View BP activity page | Ads injected after every N activities |
| 16.2 | Member directory ads | View members directory | Ads displayed before/after directory |
| 16.3 | BP activity placement in package | Package allows bp_activity only | Ads from this package show only in activity |
| 16.4 | Advertiser profile on BP | View advertiser's BP profile | Classified listings tab visible |

---

## Group 17: Settings & Configuration
**Admin:** Pro Settings (all tabs)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 17.1 | General tab save | Change all checkboxes (on and OFF) > save > reload | All values persist including unchecked boxes |
| 17.2 | Modules tab save | Disable a module > save > reload | Module disabled, features hidden |
| 17.3 | Payments tab save | Configure payment methods > save | Settings persist |
| 17.4 | Pages tab save | Assign dashboard/classified pages > save | Pages mapped correctly |
| 17.5 | Classifieds tab save | Change labels, slugs, limits > save | Settings persist, labels update across UI |
| 17.6 | Geolocation tab save | Configure map provider > save | Settings persist |
| 17.7 | Emails tab save | Toggle email types > save | Toggles persist |
| 17.8 | Rotation tab save | Change model, reset period > save | Settings persist (not overwritten) |
| 17.9 | Admin as Advertiser | Enable setting > access ad manager as admin | Advertiser account + role assigned automatically |

---

## Group 18: Reports & Moderation (Admin)
**Admin:** Classifieds > Reports, Inquiries

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 18.1 | Report page loads | Admin > Classifieds > Reports | Page renders without fatal errors |
| 18.2 | View report details | Click a report | Detail view with classified info, reporter, reason |
| 18.3 | Moderate report | Mark as reviewed/resolved/dismissed | Status updates, classified action taken |
| 18.4 | Inquiries management | Admin > Classifieds > Inquiries | List of all inquiries with status |

---

## Priority Order for QA

1. **Group 3** (Ad Submissions) — core revenue flow
2. **Group 5** (Classifieds) — core marketplace flow
3. **Group 6** (Wallet & Payments) — money handling
4. **Group 4** (Campaigns) — ad delivery
5. **Group 2** (Advertiser Portal) — user-facing hub
6. **Group 7** (Packages) — pricing/placement logic
7. **Group 15** (Email Notifications) — all 29 templates just rebuilt
8. **Group 17** (Settings) — recently fixed, verify persistence
9. **Group 1** (Core Ad Management) — stable, lower risk
10. **Groups 8-14, 16, 18** — feature modules, test if enabled
