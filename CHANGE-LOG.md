# Change log — Semrush audit fixes, 13 August 2026

Branch: **`seo/semrush-audit-fixes`** (branched from `main` at `8efc9b0`)

Three commits, each one self-contained and independently revertible. Nothing
here touches the database, uploads, WordPress core, or any third-party plugin —
only `oria-core` and the `oria` theme, both of which reach the server by git.

| # | Commit | Scope |
|---|---|---|
| 1 | `c3e6d5c` | Emit LocalBusiness schema once, from oria-core |
| 2 | `468bcd5` | Give the four bare pages a meta description |
| 3 | `ecae64f` | Let long event names have the whole title tag |

Revert any one of them on its own:

```bash
git revert c3e6d5c        # or 468bcd5, or ecae64f
```

Or abandon the lot without touching `main`:

```bash
git checkout main && git branch -D seo/semrush-audit-fixes
```

---

## Before you read the fixes: the audit was stale

The Semrush crawl finished **13 Aug 12:12 AWST**. Commit `8efc9b0` landed
**12:51**, 39 minutes later. Two of the six "missing meta description" pages
and one of the four "title too long" pages were already fixed on the live site
before this work started, verified against production HTML:

- `/acupuncture-in-perth/` — 48-char title, 141-char description, both live
- `/list-your-practice/` — 156-char description, live

So the real defect count was **4 missing descriptions and 3 long titles**, not
6 and 4. Re-crawl before trusting any other number in that report.

---

## 1 — `c3e6d5c` Emit LocalBusiness schema once

**File:** `wp-content/themes/oria/single-listing.php` (−25 lines, +2)

**The problem.** `single-listing.php` carried its own `LocalBusiness` JSON-LD
block, written before `Oria\Core\Schema\listing_schema()` existed. Both ran, so
every listing page shipped **two** `LocalBusiness` blocks for the same business.

The template's copy only attached an `address` when the ACF `address` field was
filled. `/listing/ellenbrook-remedial-massage/` is a home clinic with a suburb
but no street address, so its second block had no address at all — an
incomplete `LocalBusiness`. That was the single ERROR-severity finding in the
audit (issue 45, 1 of 364 items). Every other listing had an address, so its
duplicate was merely redundant rather than malformed, which is why only one
page errored.

**The fix.** Delete the template's block. `listing_schema()` is better on every
property it shares — it carries `@id`, `priceRange`, `sameAs`, `image`, and
falls back to the suburb when there is no street address.

**Also fixed, unintentionally but importantly.** The template's block published
`aggregateRating` built from the ACF `rating` / `review_count` fields. Those
numbers come from Google Places, and the header of `schema.php` names
`aggregateRating` as a deliberate omission — *"our ratings come from Google
Places and must never be re-published as structured data"* — while
`single-listing.php:172` says they must be *"never presented as our own."*
Putting them in our own JSON-LD did exactly that. It is now gone.

I briefly ported `aggregateRating` into `listing_schema()` to avoid losing it,
then reverted that once I found the policy. The final commit does **not** add
it anywhere.

**Verified locally.** Every listing now emits exactly one `LocalBusiness` block
carrying the `#business` `@id`, and zero `aggregateRating`. The visible star
ratings are untouched — all six rated listings still render theirs, still
labelled "Google reviews":

```
wattleseed-centre-wa   5.0  1 Google reviews
dancing-dhevas         4.0  10 Google reviews
bridies-music-therapy  5.0  12 Google reviews
sol-music-therapy      5.0  5 Google reviews
mkla-creative-arts…    5.0  5 Google reviews
art-of-therapy         5.0  1 Google reviews
```

---

## 2 — `468bcd5` Meta descriptions for the four bare pages

**File:** `wp-content/plugins/oria-core/includes/seo.php`

**The problem.** Yoast emits a meta description only when one is typed; it has
no automatic fallback. Four pages had neither a typed value nor an entry in
`page_defaults()`, so they shipped with no description tag at all:

- `/terms/`
- `/privacy-policy/`
- `/sauna-ice-bath-or-float/`
- `/perth-hills-wellness-destination/`

**The fix, two parts.**

*Explicit entries* in `page_defaults()` for all four. Lengths: 146, 154, 155
and 157 characters, all inside the 158 ceiling `entity_description()` already
uses.

*A general fallback* — new `excerpt_description()`, wired into
`seo_description()` for singular posts and pages. It reads only the **typed**
excerpt, because WordPress fabricates one from the opening of the body when the
field is empty and that reads like a truncated article rather than a
description. It cuts on a word boundary at 158 characters.

Both guides have a good hand-written standfirst, so the fallback would have
covered them — but their standfirsts run 162 and 213 characters, and the
automatic cut drops the clause that makes them worth clicking. They keep an
explicit entry; the fallback exists so the *next* guide is not bare.

The two legal pages have no typed excerpt at all, so they need their explicit
entry regardless.

**Safety.** Every path stays behind the existing `! $desc` guard, so a
description typed into Yoast always wins. Nothing you have written by hand can
be overwritten by this.

**Verified locally.** `/sauna-ice-bath-or-float/` serves the new 155-character
description. The other three pages **do not exist in the local database** (see
the note at the bottom) so they could only be verified by the mechanism they
share with the sauna guide, which is the same code path keyed by a different
slug.

`excerpt_description()` was unit-tested separately against a long excerpt, a
short one, an empty one, one containing HTML and messy whitespace, and a
200-character string with no spaces at all. All five behaved.

---

## 3 — `ecae64f` Long event names get the whole title tag

**File:** `wp-content/plugins/oria-core/includes/seo.php`

**The problem.** Three event pages ran past Semrush's title ceiling. Single
events had no branch in `seo_title()`, so they fell through to Yoast's default
of `{name} - OriaHaven`.

The length is in the event names themselves — *"Sound Healing & Guided
Meditation with Tibetan & Crystal Singing Bowls"* is 69 characters before we
append anything. Those names are the search term, so cutting them would cost us
the words that win the click.

**The fix.** Drop our own `" - OriaHaven"` suffix instead, and only on titles
past 70 characters.

**Verified locally**, with the blast radius confirmed to be exactly the three
flagged pages:

| Event | Before | After | Changed |
|---|---|---|---|
| Thriving Through Change: Understanding Women's Health… | 78 | 66 | brand dropped |
| Sound Healing & Guided Meditation with Tibetan… | 82 | 70 | brand dropped |
| How to Adult: Fresh Meals, Small Bills… | 77 | 65 | brand dropped |
| 9D Breathwork Journey | 33 | 33 | unchanged |
| Floating Hammock Sound Bath & Meditation | 52 | 52 | unchanged |
| The Language of Your Body - A Women's Workshop | 58 | 58 | unchanged |

A title typed into Yoast still wins, via the same `_yoast_wpseo_title` guard
the listing branch above it uses.

---

## Deliberately not changed

**Unminified CSS — 522 of the 624 warnings.** Your call: handled at the
Hostinger CDN layer instead of in the repo, so the six source files in
`wp-content/themes/oria/assets/css/` stay readable and no build step enters the
deploy. This is also the whole of the `+85` warning delta and the reason all 87
pages report a low text-to-HTML ratio.

**HSTS — confirmed missing on the live site.** `.htaccess` is **not tracked in
git** (`.gitignore:41` excludes it), so an edit here would never reach the
server. This has to be done on Hostinger: hPanel → SSL → enable HSTS, or add
`Strict-Transport-Security` to the server's own `.htaccess`. I did not edit the
local file, because doing so would have looked like a fix while changing
nothing.

**`llms.txt` not found.** Would need a virtual route like the existing
`robots_txt` filter, plus decisions about what belongs in it. Left alone — it
is a notice, and it needs content direction rather than code.

**5 directory URLs with one internal link** (`/directory/?cat=…`, `?q=…`).
These faceted URLs are consuming your crawl budget, and the audit hit its
100-page cap, so real pages may be going uncrawled. Worth a `noindex` — but
whether those filter pages should rank is an SEO strategy call, not a defect,
so I left it to you.

**178 external nofollow links.** Expected for a directory that links out to
practitioner sites. No action.

**`aggregateRating` in structured data.** Deliberately still absent, per the
policy in `schema.php`. Do not let this come back.

---

## Deploy

Merge and deploy the normal way from `DEPLOY.md`:

```bash
git checkout main
git merge seo/semrush-audit-fixes
git push origin main
# then on the server:
cd ~/public_html && git pull
```

Then purge the Hostinger CDN (`hcdn` — the live response headers show a Sydney
edge in front of the site) or the fixes will not be visible immediately.

### Post-deploy checks

```bash
# one LocalBusiness block, no aggregateRating
curl -s https://oriahaven.com.au/listing/ellenbrook-remedial-massage/ | grep -c '"@type":"LocalBusiness"'   # expect 1
curl -s https://oriahaven.com.au/listing/ellenbrook-remedial-massage/ | grep -c aggregateRating            # expect 0

# descriptions present
for p in terms privacy-policy sauna-ice-bath-or-float perth-hills-wellness-destination; do
  echo -n "$p: "; curl -s "https://oriahaven.com.au/$p/" | grep -o '<meta name="description"[^>]*>' | head -1
done

# event titles without the brand suffix
curl -s https://oriahaven.com.au/events/sound-healing-guided-meditation-with-tibetan-crystal-singing-bowls/ | grep -o '<title>[^<]*</title>'
```

Then re-run the Semrush audit. Expected: **1 error → 0**, and 7 of the flagged
warnings cleared.

---

## Two things worth knowing

**The local database has diverged from production.** `/terms/`,
`/privacy-policy/`, `/perth-hills-wellness-destination/` and
`/listing/ellenbrook-remedial-massage/` all return 404 on `localhost:10052`
while serving fine on production. Content has been added on the live site since
`oriahaven-production.sql` was exported, and deploys never carry the database
back. Local is not a reliable mirror for content-dependent checks.

**A small pre-existing bug, not touched.** Listings with one Google review
render "1 Google reviews". The `_n()` call at `single-listing.php:185` handles
singulars correctly, so the Google-rating branch below it is producing that
string another way. Not in the audit, not mine, left alone.
