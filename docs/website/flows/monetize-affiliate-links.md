---
title: Monetize Affiliate Links
persona: Operator — Free
tier: free
one_job: Take a site owner from one raw affiliate URL to a cloaked, tracked link displayed in-content with a click registered in the admin.
outcome: Reader has a link category, a managed affiliate link with nofollow/sponsored attributes, and a confirmed click recorded on the Links screen.
assumes: Free plugin installed and activated, one affiliate destination URL on hand.
---

# Monetize Affiliate Links

![Links list with populated click counts per cloaked link](../images/flows/monetize-affiliate-links.png)

This flow cloaks an affiliate URL behind a tracked slug, drops it into content in three ways, and confirms the first click registers in the admin. Budget about 15 minutes.

## Before you start

- The free plugin **WB Ad Manager** is installed and activated.
- You have one affiliate destination URL (for example an Amazon Associates URL, a ShareASale link, a partner referral URL).
- You know which category you want to file the link under (Sponsors, Partners, Affiliates, Resources, or a custom category you will create).

## Step 1 — Create a link category

Categories let you reuse and group links; set them up once up-front.

1. Go to **WB Ad Manager → Links → Categories**.
2. Enter the category name (e.g., `Affiliates`) and a URL-friendly slug (e.g., `affiliates`).
3. Click **Add New Category**.
4. Confirm the category shows in the right-hand list with a link count of 0.

## Step 2 — Create the managed link

1. Go to **WB Ad Manager → Links → Add New**.
2. Fill in:
   - **Title** — the default anchor text (e.g., `Our recommended hosting`).
   - **URL** — the raw affiliate URL.
   - **Description** — one sentence describing the partner. Visible to readers when you use list shortcodes.
3. Under **Category**, pick the category you created in Step 1.
4. Under SEO, check **Nofollow** and **Sponsored** — affiliate links should always carry these `rel` attributes.
5. Under **Target**, leave **Open in new window** enabled.
6. Click **Publish**.
7. After publish, note the link ID from the URL bar (`post=123`). You need it for shortcodes.

The full field reference lives in [Link Management](../link-management/link-management.md).

## Step 3 — Display the link in a post

There are three shortcode patterns. Pick whichever matches your use case.

### Pattern A — inline anchor in post copy

Drop the shortcode directly inside any paragraph or heading.

```
[wbam_link id="123" text="see our hosting pick"]
```

The `text` attribute overrides the default title. Leave it off to use the link's title as the anchor.

### Pattern B — a card grid of every affiliate link

Create or edit a page (e.g., `/recommended/`) and drop in:

```
[wbam_links category="5" format="grid" limit="12"]
```

Replace `5` with your category's numeric ID from **WB Ad Manager → Links → Categories**.

### Pattern C — custom HTML button around the raw URL

When you need the tracked URL inside your own markup:

```html
<a href="[wbam_link_url id='123']" class="btn btn-primary">
    Sign up and save
</a>
```

`[wbam_link_url]` outputs just the `/go/...` redirect URL, so you keep your button's markup while still getting click tracking.

Full parameter lists are in [Link Shortcodes](../shortcode-reference/link-shortcodes.md).

## Step 4 — Trigger the first click

1. Open the page containing your shortcode in an incognito window.
2. Click the tracked link once. You should be redirected to the destination URL.
3. Check the browser URL bar on the click — it briefly shows `yoursite.com/go/<slug>` before redirecting. That is the cloaked redirect endpoint.

## Step 5 — Confirm the click was counted

1. Go back to **WB Ad Manager → Links** in the admin.
2. In the row for your link, the **Clicks** column should show at least 1.
3. Click the link's title to open its detail screen. You should see:
   - **Total Clicks** — cumulative count.
   - **Unique Clicks** — deduplicated visitor count.
   - **Referrers** — the URL of the page you clicked from.

If all three are still zero after 30 seconds, work through [Common Issues → Links not tracking clicks](../link-management/link-management.md#troubleshooting).

## Step 6 — Make the link discoverable

Pick at least one of the following so the link gets traffic:

- **In-content mentions.** Drop Pattern A shortcodes inside your highest-traffic posts.
- **A dedicated resources page.** Publish a page with Pattern B's grid and link to it from your main navigation.
- **A sidebar widget.** Use Pattern B with `format="list" limit="5"` inside a Text/HTML widget.

## Verification — how to confirm the full flow worked

All four must be true:

- The link post in **WB Ad Manager → Links** shows status **Published** and the category column matches the one you created.
- The link renders on the frontend via at least one of shortcodes A, B, or C.
- Clicking the rendered link goes through `/go/<slug>` before landing on the affiliate URL.
- The **Total Clicks** and **Unique Clicks** values increment after your test click.

## What to do next

- Enable the `[wbam_partnership_inquiry]` form so visitors can propose their own partnerships — see [Link Partnerships](../link-management/partnership-inquiries.md).
- Sort the link grid by performance: add `orderby="clicks" order="DESC"` to Pattern B.
- If you have Pro, you get keyword auto-linking and broken-link health checks. See [Pro Installation & Requirements](../getting-started/pro-installation-requirements.md) for the Links Pro module.
