# Link Partnerships

Accept inbound partnership inquiries from advertisers, bloggers, and affiliate
partners — without email ping-pong. Visitors submit a structured form on your
site; you review, accept, or reject each inquiry from the WordPress admin.

Find it under **WB Ad Manager → Link Partnerships**.

---

## When to Use This

The Link Partnerships module is useful if people ask you about:

- **Paid link placements** — pay once to publish a link in an existing post
- **Link exchanges** — swap links between your site and theirs
- **Sponsored posts** — paid content on your blog

Instead of fielding every request by email, point interested people at an
inquiry form on your site. Everyone gets the same questions. You get a
structured record per request.

---

## Step 1 — Add the Inquiry Form

Drop this shortcode onto any page (a "Work with us", "Partnerships", or
"Advertise" page works well):

```
[wbam_partnership_inquiry]
```

The form renders these fields out of the box:

| Field | Required | Purpose |
|-------|----------|---------|
| Name | Yes | Contact name |
| Email | Yes | Reply-to for acceptance / rejection |
| Website URL | Yes | The partner's site |
| Partnership type | Yes | `Paid Link`, `Link Exchange`, or `Sponsored Post` |
| Target page on your site | Optional | The post/page they want the link in |
| Anchor text | Optional | The exact text they want linked |
| Budget (min / max) | Optional | For paid placements |
| Message | Optional | Free-form pitch |

### Shortcode attributes

All attributes below are optional — the defaults match the table above.

```
[wbam_partnership_inquiry
    title="Partner with Us"
    description="Tell us about your partnership idea."
    show_budget="yes"
    show_target_page="yes"
    show_anchor="yes"
    show_message="yes"
    button_text="Send Inquiry"
    class="my-custom-form"
]
```

| Attribute | Values | Default | Effect |
|-----------|--------|---------|--------|
| `title` | Any text | `Partner with Us` | Heading above the form |
| `description` | Any text | Short intro paragraph | Sub-heading |
| `show_budget` | `yes` / `no` | `yes` | Toggles the budget min/max fields |
| `show_target_page` | `yes` / `no` | `yes` | Toggles the "target page" dropdown |
| `show_anchor` | `yes` / `no` | `yes` | Toggles the anchor-text input |
| `show_message` | `yes` / `no` | `yes` | Toggles the message textarea |
| `button_text` | Any text | `Send Inquiry` | Submit button label |
| `class` | CSS class name | Empty | Extra class on the form wrapper |

### Anti-abuse

- Submissions are nonce-protected and sanitized server-side.
- The same email can only submit once per 24 hours against the same target
  page — repeat submissions inside that window are silently ignored. This
  prevents bots from hammering your inbox.

---

## Step 2 — Review Incoming Inquiries

Every submission creates a row in **WB Ad Manager → Link Partnerships**.

The list table shows:

| Column | What it is |
|--------|------------|
| Contact | Name + email (click to view full detail) |
| Website | The partner's site, linked |
| Type | Paid Link, Exchange, or Sponsored |
| Budget | Budget range if provided |
| Status | Pending / Accepted / Rejected / Spam |
| Date | When the inquiry arrived |
| Actions | View, Accept, Reject, Mark as Spam, Delete |

Use the **status tabs** at the top (All / Pending / Accepted / Rejected / Spam)
to filter. New inquiries land in **Pending**.

### Inquiry detail view

Clicking an inquiry shows:

- Everything the partner submitted
- The IP address the submission came from
- Admin-only notes (internal — never emailed)
- Buttons to **Accept**, **Reject**, or **Mark as Spam**

When you accept or reject, an email is sent to the partner automatically
(see "Email Notifications" below).

---

## Step 3 — Act on Accepted Inquiries

Accepting an inquiry does **not** automatically create the link on your
site — it only tells the partner you're interested and moves the record to
the **Accepted** tab. You still decide the terms and publish the link
yourself.

A typical flow:

1. Partner submits inquiry → lands in **Pending**
2. You review, mark **Accepted** → automatic acceptance email sent
3. You reply to their email to negotiate price / date / final anchor text
4. Once settled, create the managed link at **WB Ad Manager → Links** (see
   [Link Management](../link-management/link-management.md)) or edit the target post and
   paste the anchor manually
5. Record the agreed terms in the admin notes field for your own reference

Rejected and spam inquiries stay on file so you have a record — you don't
have to delete them.

---

## Email Notifications

Three notification emails are built in:

| Event | Sent to | Trigger |
|-------|---------|---------|
| New inquiry | Site admin email | Every new submission |
| Accepted | The partner | When you click **Accept** |
| Rejected | The partner | When you click **Reject** |

Marking an inquiry as **Spam** does not email the partner.

All three notification sends are filterable — see the
[Developer Guide → Link Partnerships hooks](../../DEVELOPER-GUIDE.md#link-partnerships)
if you want to disable any of them, change headers, or modify the body.

---

## Styling the Form

The form inherits your theme's form styles. If you need to override,
wrap it in a custom class:

```
[wbam_partnership_inquiry class="my-partnership-form"]
```

And style it:

```css
.my-partnership-form {
    max-width: 640px;
    margin: 0 auto;
}
.my-partnership-form label {
    font-weight: 600;
}
```

---

## FAQ

**Does this integrate with a CRM?**
No — all inquiries stay in your WordPress database. Use the
`wbam_partnership_created` action (see the developer guide) to forward
submissions to HubSpot, Pipedrive, or any webhook.

**Can I change the partnership types?**
The three types (`paid_link`, `exchange`, `sponsored_post`) are
hard-coded in the free plugin. Developers can remove or rename options
with form-output filters — see the developer guide.

**Are submissions deleted on uninstall?**
Only if **Settings → Advanced → Delete Data on Uninstall** is enabled.
By default, uninstalling preserves the table so re-installing restores
your history.

**How do I know if the form is working?**
Submit a test inquiry yourself. It should appear under **Pending** and
you should receive the admin notification email at the site admin
address.

---

## Related Guides

- [Link Management](link-management.md) — manage the links you publish
- [Settings](../ad-management/settings.md) — global plugin settings including data retention
- [Developer Guide → Link Partnerships](../../DEVELOPER-GUIDE.md#link-partnerships) — hooks, database schema, REST endpoint
