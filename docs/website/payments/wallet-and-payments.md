# Wallet and Payments

> **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.

Every advertiser on a Pro-enabled site has a wallet that tracks how many credits they have available to spend on ads, campaigns, classified listings, and package purchases. This guide explains how credits enter the wallet, how spending works, and how you (the site owner) configure which payment paths your advertisers can use.

## How credits reach a wallet

Pro does not ship its own Stripe, PayPal, or Razorpay integration. Instead it uses the **Wbcom Credits SDK**, which is bundled with Pro under `vendor/wbcom-credits-sdk/`. The SDK exposes a plug-in adapter pattern. Pro registers itself with the SDK as a credit consumer; the SDK then accepts credit grants from any adapter that is also registered and active on the site.

Five adapters ship out of the box, and every one of them auto-appears in **Settings → Credits** when the adapter's source plugin is active:

| Adapter | Source plugin | What it does |
|---------|---------------|--------------|
| **WooCommerce Products** | WooCommerce | Sell credit-pack products. The advertiser checks out through WooCommerce; any WC-supported gateway (Stripe, PayPal, Razorpay, Square, bank transfer, …) handles the payment. When the order reaches `completed` or `processing`, the adapter grants credits. |
| **WooCommerce Subscriptions** | WooCommerce Subscriptions | Sell recurring credit packs. Each renewal tops up the wallet automatically. |
| **WooCommerce Memberships** | WooCommerce Memberships | Bundle credits into membership plans. Credits are granted on membership activation and renewal. |
| **Paid Memberships Pro** | Paid Memberships Pro | Map PMPro levels to credit grants. Purchasing a level credits the advertiser. |
| **MemberPress** | MemberPress | Map MemberPress products to credit grants. |

### What this means for site owners

Any payment method you could already accept through your existing WooCommerce or membership-plugin setup will work for credit top-ups. You do **not** configure Stripe or PayPal inside Pro — you configure them inside WooCommerce (or the membership plugin you chose), and Pro picks up the credit on order completion.

### What advertisers see

Advertisers log in to the Advertiser Portal, open the **Wallet** tab, and click **Buy Credits**. The button routes to the purchase URL you configured in **Settings → Credits**. From that point on, the checkout experience is whatever your WooCommerce shop or membership checkout looks like. When payment succeeds, Pro grants credits immediately and the Wallet tab shows the updated balance.

## Three ways to configure credit top-ups

### Path A — WooCommerce credit-pack products (most common)

Use this when you want one-off credit purchases with a single price per pack.

1. Install and activate **WooCommerce**. Configure at least one payment gateway you already trust (Stripe, PayPal, bank transfer — any WC plugin works).
2. In WooCommerce, create Simple Products that represent credit packs. For example: "100 Credits — $10", "500 Credits — $45".
3. In the Pro admin, open **Settings → Credits**. The WooCommerce adapter appears automatically. Enable it and map each credit-pack product to its credit amount.
4. Set the Advertiser Portal's "Buy Credits" button to link to your WooCommerce shop (or a dedicated credit-packs category page).
5. Test: from an advertiser account, click **Buy Credits**, complete checkout, and confirm the Wallet tab shows the new balance.

### Path B — WooCommerce Subscriptions (recurring credits)

Use this when you want advertisers to pay monthly or yearly for a rolling credit allowance.

1. On top of WooCommerce, install **WooCommerce Subscriptions**.
2. Create Subscription Products (e.g., "50 Credits every month — $5/mo").
3. In **Settings → Credits**, enable the WooCommerce Subscriptions adapter and map each subscription product to its credit allowance.
4. Each successful renewal fires the adapter callback, which tops up the wallet.

### Path C — Membership tier grants credit (best for tiered pricing)

Use this when you want an advertiser's credit allowance to be part of their membership package rather than a separate purchase.

1. Install **Paid Memberships Pro** or **MemberPress** (not both — pick one).
2. Create membership levels / products.
3. In **Settings → Credits**, enable the matching adapter (PMPro or MemberPress). Map each level/product to its credit grant amount.
4. When an advertiser joins the membership, the adapter grants credits immediately. Renewals grant again.

Advertisers can always add credits outside their membership using a WooCommerce credit pack in parallel.

## Spending — how credits leave the wallet

Credits leave the wallet through four entry points. Every spend is recorded in the advertiser's ledger with a transaction type so you can see what happened and when.

| Event | Transaction type | When it fires |
|-------|------------------|---------------|
| Package purchase | `package` | Advertiser buys an ad package from **Advertiser Portal → Packages**. Full package cost is charged at purchase time. |
| CPM/CPC campaign activation | `campaign_reserve` | Advertiser activates a CPM or CPC campaign with a set budget. The full budget is pre-charged against the wallet. |
| Unlimited campaign hourly billing | `campaign` | For campaigns with no budget cap, the `wbam_calculate_hourly_billing` cron charges the advertiser every hour for the prior hour's impressions/clicks. |
| Classified listing fee or upgrade | `classified` | Advertiser submits a paid classified, or buys a Featured / Highlighted / Urgent / Bump upgrade. |

### Campaign budget pre-charge and refund

When an advertiser activates a CPM or CPC campaign with a budget > 0, Pro charges the full budget to the wallet as a `campaign_reserve` ledger entry. This is a genuine charge (not a hold) so it is atomic with the activation and cannot get lost if the site crashes mid-flow.

The `Campaign_Manager` then tracks how much of the budget actually gets spent on served impressions/clicks. When the campaign completes, expires, or is cancelled, any unspent portion is credited back as a `campaign_refund` ledger entry. Pausing a campaign does **not** refund — the reservation stays held so that un-pausing does not risk activation failing due to the advertiser spending the money elsewhere in the meantime.

If the advertiser's balance is too low to cover a budget at activation time, the activation fails with a clear error. They top up and try again.

### All transaction types

Everything that moves a wallet balance gets one of these types. Use the `WB Ads → Transactions` admin page's type filter to audit any specific flow.

| Type | Direction | Description |
|------|-----------|-------------|
| `topup` | Credit | Credits added by an adapter (WooCommerce order / subscription renewal / membership purchase). |
| `credit` | Credit | General credit added to the wallet. |
| `adjustment` | Credit or Debit | Manual admin adjustment with a reason. |
| `campaign_refund` | Credit | Unused campaign budget returned on completion or cancellation. |
| `campaign_adjust` | Credit or Debit | Budget change on an active campaign edit. |
| `package` | Debit | Flat-rate package purchase. |
| `campaign_reserve` | Debit | Full budget pre-charged when a CPM/CPC campaign activates. |
| `campaign` | Debit | Hourly spend on an unlimited-budget campaign. |
| `classified` | Debit | Classified listing fee or paid upgrade. |

## Offline payments — manual / bank transfer

When you would rather accept bank transfers, cheques, or any other out-of-band method, you can approve top-ups yourself.

**What the advertiser does:**
1. Opens the Wallet tab and selects **Bank Transfer** (or whichever label you configured).
2. Enters a reference note (invoice number, transaction ID, etc.).
3. Submits. A pending ledger entry is created and you get an email.

**What you do as admin:**
1. Go to **WB Ads → Transactions** and open the **Pending Approval** filter.
2. Review the request — contact the advertiser if needed.
3. Click **Approve** once the funds have cleared in your bank account, or **Cancel** to decline.

Approving fires the `wbam_fund_request_approved` action and emails the advertiser that their wallet has been topped up. Only transactions with `pending` status and type `payment` or `credit` can be approved from this screen.

## Admin adjustments

You can credit or debit any advertiser's wallet directly.

1. **WB Ads → Advertisers** → click the advertiser name.
2. **Adjust Balance**.
3. Choose Credit or Debit, enter an amount, type a reason.
4. Save.

Every adjustment lands in the ledger as an `adjustment` transaction with your reason text. The minimum deposit amount enforced on advertiser-initiated top-ups is $5 by default and is filterable via `wbam_pro_minimum_deposit`.

## What's next

- [Creating Ad Packages](../settings/creating-ad-packages.md) — how to price what advertisers spend their credits on.
- [Campaign Management](../advertiser-portal/campaign-management.md) — the budget reservation lifecycle from the advertiser's side.
- [Pro Settings Configuration](../settings/pro-settings-configuration.md) — the Credits settings tab with adapter mappings.
