# Wallet and Payments

> **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.

Every advertiser has a wallet that stores funds used to pay for ad packages and campaigns. This guide explains how the wallet system works, how advertisers add funds, and how you manage payments as an admin.

## How the Wallet Works

Each advertiser account has a wallet that tracks:

- **Balance** - Funds available to spend
- **Pending Credits** - Payments awaiting confirmation
- **Pending Debits** - Reserved funds not yet settled
- **Available Balance** - Balance minus pending debits

When an advertiser purchases a package or activates a CPM/CPC campaign, the cost is deducted from their wallet. If the balance is insufficient, the purchase or activation is blocked.

## Payment Gateways

Five payment methods are available. Enable each one under **WB Ads > Pro Settings > Payments**.

| Gateway | How It Works | Configuration Required |
|---------|-------------|----------------------|
| **Stripe** | Advertiser enters card details on your site. Funds credit instantly on payment success. | Publishable key + Secret key |
| **PayPal** *(Admin UI coming soon — currently requires manual configuration via `wbam_pro_settings` option)* | Advertiser approves payment via PayPal checkout. Funds credit after capture completes. | Client ID + Client secret. **Note:** No admin settings UI is available yet. Configure via database (`wbam_pro_settings` option) or the `wbam_pro_settings` filter. |
| **Razorpay** *(Admin UI coming soon — currently requires manual configuration via `wbam_pro_settings` option)* | Advertiser pays via Razorpay checkout (supports cards, UPI, netbanking). Funds credit after signature verification. | Key ID + Key secret. **Note:** No admin settings UI is available yet. Configure via database (`wbam_pro_settings` option) or the `wbam_pro_settings` filter. |
| **WooCommerce** | Creates a WooCommerce order. Funds credit when the order reaches "completed" or "processing" status. Requires WooCommerce to be active. | Enable in settings; uses your existing WooCommerce payment gateways |
| **Manual / Bank Transfer** | Advertiser submits a fund request. You receive an email notification and manually approve or cancel it from the admin panel. | No API keys needed |

## How Advertisers Add Funds

1. Advertiser opens the Advertiser Dashboard and clicks **Add Funds**
2. They enter an amount (minimum $5 by default) and select a payment method
3. A pending transaction is created in the database
4. The advertiser completes payment through the chosen gateway
5. On payment success, the pending transaction is confirmed and the wallet balance is credited

The minimum deposit amount is $5 by default. You can change this with the `wbam_pro_minimum_deposit` filter.

## Manual / Bank Transfer Payment Flow

This method is for offline payments such as bank transfers or cheques.

**Advertiser steps:**

1. Select **Bank Transfer** in the Add Funds dialog
2. Optionally enter notes (e.g., reference number)
3. Submit the request. A pending transaction is created and you receive an email notification

**Admin steps:**

1. Go to **WB Ads > Transactions**
2. Click the **Pending Approval** filter tab to see all pending manual payments
3. Review the transaction — advertiser name, amount, and any notes are shown
4. Click **Approve** to credit the advertiser's wallet, or **Cancel** to decline the request

Only transactions with `pending` status and type `payment` or `credit` can be approved. Approving fires the `wbam_fund_request_approved` action hook, which triggers an email notification to the advertiser.

## Admin Transactions Page

Navigate to **WB Ads > Transactions** to see all wallet activity across all advertisers.

**Available filter tabs:**

- All Transactions
- Pending Approval (manual payments awaiting your action)
- Filter by type, status, or advertiser

**Admin actions per transaction:**

| Action | When Available |
|--------|---------------|
| Approve | Transaction is pending, type is payment or credit |
| Cancel | Transaction is pending |
| Refund | Transaction is completed and of a debit type |

## Transaction Types

Every wallet movement is recorded with a specific type so you can identify what triggered it.

| Type | Direction | Description |
|------|-----------|-------------|
| `payment` | Credit | Funds received via payment gateway |
| `credit` | Credit | General credit added to wallet |
| `refund` | Credit | Refund returned to advertiser |
| `campaign_refund` | Credit | Unused campaign budget returned on cancel or completion |
| `adjustment` | Credit or Debit | Manual admin adjustment (positive or negative) |
| `campaign_adjust` | Credit or Debit | Budget adjustment for an active campaign change |
| `debit` | Debit | General debit from wallet |
| `campaign` | Debit | Hourly charge for unlimited-budget CPM/CPC campaign spend |
| `package` | Debit | Flat-rate package purchase charge |
| `campaign_reserve` | Debit | Budget reserved when a CPM/CPC campaign activates |
| `classified` | Debit | Classified listing fees (featured, premium, upgrades) |

## Budget Reservation for CPM/CPC Campaigns

CPM and CPC campaigns with a set budget use a **pre-funded reservation** system to guarantee the advertiser has sufficient funds before ads start running.

**How it works:**

1. When a CPM/CPC campaign with a budget greater than zero is activated, the full budget amount is debited from the wallet as a `campaign_reserve` transaction
2. The reserved funds are held while the campaign runs
3. If you pause a campaign, the reservation is held — it is not released on pause
4. When the campaign completes, expires, or is cancelled, the unspent portion is returned as a `campaign_refund` transaction
5. If the budget reservation fails (insufficient balance), the campaign activation is blocked and an error is shown

Campaigns with no budget set (unlimited budget) use **hourly billing** instead: actual spend is charged from the wallet every hour via the `wbam_calculate_hourly_billing` cron job. If the wallet balance is insufficient at billing time, the campaign is automatically paused.

## Data Safety

The wallet system uses database transactions (`START TRANSACTION` + `SELECT ... FOR UPDATE`) for all balance changes to prevent race conditions. Every credit and debit operation supports an **idempotency key** — if the same operation is attempted twice (e.g., a webhook fires twice), the duplicate is silently ignored and the balance is only changed once.

## Admin Balance Adjustments

You can manually credit or debit any advertiser's wallet.

1. Go to **WB Ads > Advertisers** and click the advertiser's name
2. Click **Adjust Balance**
3. Select Credit or Debit, enter the amount, and provide a reason
4. Click **Save**

The adjustment is recorded as an `adjustment` transaction with your reason as the description.
