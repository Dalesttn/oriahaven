# Deploying Oria Haven to Hostinger

Target: **oriahaven.com.au** on Hostinger Business shared hosting, with
code deployed from **GitHub** by `git pull` over SSH.

The split that makes this work:

| What | How it gets to the server | Why |
|---|---|---|
| Theme `oria`, plugins `oria-*` | **git** | Our code. Changes constantly. |
| WordPress core | Hostinger's installer, then auto-updates | Never edit it, never version it. |
| ACF Pro, ACF Extended Pro, Yoast | Installed on the server | Licensed / self-updating. |
| Uploads (39MB of photos) | One-time zip upload | Too big and too churny for git. |
| Database | One-time SQL import | Content, not code. |
| Keys and passwords | Typed into the server's `wp-config.php` | Never in git. Not ever. |

---

## ⚠️ Before anything else: remove Novamira

`wp-content/plugins/novamira` is an MCP server that, in its own words,
gives AI agents **"full access to WordPress through PHP execution and
filesystem operations. For development and staging environments only."**

On a public site that is a remote-code-execution backdoor. It is excluded
from git, so it cannot reach the server through a deploy — but **do not
upload it manually**, and delete it locally once you no longer need it
(Plugins → deactivate → delete).

Same idea, lower stakes: **Query Monitor** is a developer tool that leaks
query and hook detail — don't install it on production. **WPForms Lite**
is already inactive and unused (oria-forms replaced it); it can go.

---

## Step 1 — Install WordPress on the domain

**Yes, you need a WordPress instance on the domain first.** It gives you
core files, a database, and a `wp-config.php` already holding the right
credentials.

1. hPanel → **Websites → Add website** → point it at `oriahaven.com.au`.
2. Use Hostinger's **WordPress auto-installer**. Any admin username and
   password will do — the database import in Step 4 replaces them with
   your real accounts.
3. Confirm `https://oriahaven.com.au` shows a stock WordPress site.
4. hPanel → **SSL** → issue the free Let's Encrypt certificate and turn on
   **Force HTTPS**. Do this *before* the import, so nothing gets recorded
   over plain http.

## Step 2 — Push this repo to GitHub

Run these on your machine, in `…\Local Sites\oriahaven\app\public`:

```bash
git remote add origin git@github.com:<your-username>/oriahaven.git
git push -u origin main
```

Make the repo **private** — it contains your business logic, pricing
rules and Stripe plumbing. No keys are in it, but it isn't public reading.

## Step 3 — Wire the server to GitHub

hPanel → **Advanced → SSH access**: turn SSH on and note the host, port
and username. Then connect and set the site up as a git checkout:

```bash
ssh -p <port> <user>@<host>
cd domains/oriahaven.com.au/public_html
git init -b main
git remote add origin https://github.com/<your-username>/oriahaven.git
git fetch origin
git checkout -f main
```

`checkout -f` lets the tracked files land on top of the WordPress install
that's already there. Nothing else is touched: `.gitignore` keeps git's
hands off core, uploads and third-party plugins.

For a **private** repo, generate a deploy key on the server and add it to
GitHub under Settings → Deploy keys (read-only is enough):

```bash
ssh-keygen -t ed25519 -C "oriahaven-deploy" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub     # paste this into GitHub
git remote set-url origin git@github.com:<your-username>/oriahaven.git
```

## Step 4 — Move the database

You have `oriahaven-production.sql` — the full local database with all
130 listings, events, journal posts, products and settings, with every
`http://localhost:10052` already rewritten to `https://oriahaven.com.au`
(378 replacements, serialized data handled properly).

1. hPanel → **Databases → phpMyAdmin** → open the site's database.
2. Select all existing tables → **Drop** (they're the throwaway ones the
   auto-installer made).
3. **Import** → choose `oriahaven-production.sql` → Go.
4. The dump uses the `wp_` table prefix. If Hostinger's installer chose a
   different one, edit `$table_prefix` in the server's `wp-config.php` to
   `'wp_'` so it matches.

Your local login now works on production; the auto-installer's admin
account is gone.

## Step 5 — Upload the media library

Unzip `uploads.zip` into `public_html/wp-content/` so the folders land as
`wp-content/uploads/2026/…`. hPanel's File Manager will upload and extract
the zip in one go — much faster than FTPing 336 files.

## Step 6 — Install the third-party plugins

On the server, Plugins → Add New:

- **Yoast SEO** — free, from the repository.
- **Advanced Custom Fields Pro** and **ACF Extended Pro** — upload the zips
  from your ACF account and enter your licence keys. **The site depends on
  ACF**: without it, listing fields, event fields and product fields all
  render empty.

Then activate the four `oria-*` plugins and the **Oria** theme.

## Step 7 — Add the keys

Open `public_html/wp-config.php` in hPanel's File Manager and paste in the
block from `wp-config-additions.php`, filling in your real values. Live
Stripe keys, not the `sk_test_` ones.

## Step 8 — Flush and check

```bash
wp rewrite flush --hard     # or: Settings → Permalinks → Save Changes
```

Then walk the site:

- [ ] Home page, hero search, category tiles rotating
- [ ] `/directory/` filters and pagination
- [ ] A listing page — photos, map, schema in the source
- [ ] `/whats-on-perth/` and `/this-weekend/`
- [ ] `/shop/` and a journal article
- [ ] `/list-your-practice/` — submit a real test signup, confirm the email
- [ ] Log in as a practitioner and check the dashboard

---

## Deploying a change, from here on

On your machine:

```bash
git add -A && git commit -m "what changed" && git push
```

On the server:

```bash
cd domains/oriahaven.com.au/public_html && git pull
```

That's the whole loop. Only tracked files move; uploads, database and keys
are never touched by a deploy.

---

## Still to do before you call it launched

- **SMTP.** WordPress's built-in mail lands in spam. Install WP Mail SMTP
  and point it at a real sender (Hostinger email, Brevo, Postmark). Every
  signup, claim and billing email depends on this.
- **Stripe live mode.** New live payment links for $29/$79 (redirecting to
  `https://oriahaven.com.au/wp-admin/edit.php?post_type=listing`), a live
  Customer Portal configuration, and a live webhook endpoint at
  `/wp-json/oria/v1/stripe` whose signing secret goes in `wp-config.php`.
- **Google tag.** Settings → General → Google tag ID, once the GA4
  property is created.
- **Search Console + Bing Webmaster.** Verify the domain, submit
  `https://oriahaven.com.au/sitemap_index.xml`.
- **Self-host the fonts.** The theme currently pulls Manrope and Newsreader
  from Google Fonts; self-hosting is faster and avoids the EU privacy
  question.
- **Site title.** It's `OriaHaven` in Settings → General and appears in
  every search result. Decide on `Oria Haven` before Google indexes it.
- **Daily cron.** The event aggregator runs on WP-Cron, which only fires on
  traffic. Once live, switch to a real cron in hPanel:
  `wget -q -O - https://oriahaven.com.au/wp-cron.php?doing_wp_cron`
  and add `define( 'DISABLE_WP_CRON', true );` to `wp-config.php`.
- **Backups.** Hostinger's automatic backups cover you, but take a manual
  one before each risky change.
