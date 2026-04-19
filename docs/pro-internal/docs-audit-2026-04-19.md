# Docs audit — 2026-04-19

Audit agent run on the combined Free + Pro documentation set
(`docs/website/` + `docs/DEVELOPER-GUIDE.md` + `docs/pro-developer/`).

Referenced from `docs/DOCS-PLAN.md` §1 and §6.

## Summary
- 24 user-facing docs + 1 developer guide audited.
- Overall grade: 70-75% customer-ready.
- Strong categories: Placements, Pro Shortcodes, Campaign Management.
- Weakest: Quick Setup (broken links), Payments (duplicated "not yet available" note), Pro Troubleshooting (stale migration paths).

## Top 5 block-buy issues
1. 3 broken internal links in `website/getting-started/quick-setup-guide.md` lines 121, 174, 193 (old `01-` / `02-` numbered slugs that were flattened in the last restructure).
2. "PayPal / Razorpay UI not yet available" copy appears verbatim in three Pro docs without a shared version target, creating purchase hesitation.
3. Admin navigation paths in several Pro docs reference menu items ("WB Ads -> Tools") that need verification against the live Pro_Admin registration.
4. Shortcode attribute tables drifted vs. code in at least two shortcodes — need re-verification against current attribute defaults.
5. Five sentences in category landing pages read like AI / SaaS marketing copy that conflicts with the otherwise-practical tone (full list below).

## Per-doc grades (abbreviated)
- Getting Started: `installation.md` A, `quick-setup-guide.md` C+ (broken links), `pro-installation-requirements.md` B+.
- Ad Management: all five docs A / A-.
- Link Management: A / A-.
- Advertiser Portal (Pro): all four docs A / A-.
- Analytics / Classifieds / Payments / Pro Settings / Creating Packages: A to B (classifieds has a submit-form-template path buried at line 40).
- Shortcode Reference: A / A- / A.
- Troubleshooting: B+ / A-.

## Marketing-flavour sentences flagged for rewrite
- `quick-setup-guide.md:12` — "Get your first ad live in 10 minutes - no coding required!"
- `pro-installation-requirements.md:5` — 13-feature run-on sentence.
- `advertiser-portal-overview.md:6` — "self-service frontend dashboard" redundant.
- `wallet-and-payments.md:40` — duplicates line 38 verbatim.
- `pro-settings-configuration.md:44` — unnecessary bold on a one-line fact.

## Accuracy / completeness backlog (full)
See `DOCS-PLAN.md` section 6 for the deduplicated list and severity buckets.
