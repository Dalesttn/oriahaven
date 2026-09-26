# Activities batch — 26 September 2026

Five categories from `DESIGN/oria-haven-top-five-categories-scraping-claude-code.md`.
Everything imported is a **draft**. Nothing is published, nothing is indexed, and no organiser was contacted.

## Category mapping

| Requested | Term | ID | Parent | URL | State |
|---|---|---:|---|---|---|
| Running Groups | practice `running-groups` | 569 | fitness | `/explore/perth/running-groups/` | new, pending launch |
| Ocean Swimming & Social Dips | practice `ocean-swimming` | 573 | nature | `/explore/perth/ocean-swimming/` | new, pending launch |
| Pickleball | practice `pickleball` | 577 | fitness | `/explore/perth/pickleball/` | new, pending launch |
| Pottery & Creative Workshops | practice `creative-workshops` | 581 | experiences | `/explore/perth/creative-workshops/` | new, pending launch |
| Walking Groups | service `walking-groups` | 382 | (service; home category `community`) | facet under Community | **reused**, untouched |

Why these parents: sub-category URLs are flat, so a parent is a grouping that can change later without moving any URL. Pottery is under Experiences and **not** under `creative`, because that category is *Creative Therapies* (art and music therapists) — the brief forbids presenting a pottery class as therapy. Beaches stay in `beaches-swims` (places); `ocean-swimming` is for organisations.

Tags (specialties): social-running, run-walk, coached-running · social-dip, distance-swim, coached-swim · come-and-try, social-play, pickleball-lessons · pottery, wheel-throwing, hand-building, painting-workshops. Mapped into the Move / Connect / Recharge / Relax / Reset chips in `data/goodfor.json`.

**Pending** = `noindex, follow`, excluded from the Yoast sitemap, hidden from the navigation. Existing terms are never marked.

## Results

| Category | Candidates | New drafts | Existing matches | Review required | Rejected |
|---|---:|---:|---:|---:|---:|
| Running | 11 | 4 | 1 (Rise and Run Club #900576) | 5 | 1 |
| Walking | 9 | 4 | 1 (The Perth Walking Group #373) | 2 | 2 |
| Ocean | 11 | 2 | 0 | 6 | 3 |
| Pickleball | 7 | 4 | 0 | 2 | 1 |
| Creative | 12 | 8 | 0 | 4 | 0 |
| **Total** | **50** | **22** | **2** | **19** | **7** |

50 organisations; one (Manning Walk & Run) is in two categories as a single listing. `report.md` has the full exceptions list.

**Shortfalls, plainly:** Ocean has only 2 drafts against a 5–10 target — most Perth swim groups exist only on Instagram or in directories, and Swimclan's site could not be read. Running has 4 own drafts plus the cross-listed Manning walk. The single strongest pickleball venue (Perth Pickleball Centre) refuses automated requests and needs checking by hand.

## Files

- `manifest.json` — every domain considered, its type, and the access decision
- `candidates.json` — one record per organisation, with field-level evidence
- `fetch-log.json` — every request: when, result, and why it stopped
- `report.md` — per-category table and exceptions

Page cache (not in git): `wp-content/uploads/oria-sources/cache/`. Journal (for reruns/rollback): option `oria_src_journal_2026-09-26-activities`.

## Commands

From the WordPress root:

```
wp oria sources setup --dry-run                  # categories + tags; safe to rerun
wp oria sources setup
wp oria sources fetch  --batch=2026-09-26-activities [--category=ocean] [--limit=5] [--dry-run] [--refresh]
wp oria sources verify --batch=2026-09-26-activities
wp oria sources import --batch=2026-09-26-activities --dry-run
wp oria sources import --batch=2026-09-26-activities [--category=creative] [--limit=3]
wp oria sources report --batch=2026-09-26-activities
wp oria sources rollback --batch=2026-09-26-activities --dry-run
wp oria sources rollback --batch=2026-09-26-activities
wp oria sources launch pickleball --dry-run
wp oria sources selftest
```

Import writes drafts only. Rerunning refreshes this batch's own *unedited* drafts and creates nothing new. Rollback moves this batch's unedited, unreviewed drafts to the Trash and removes its proposals; anything reviewed, published or edited since import is kept, and no pre-existing listing is touched.

**On production:** the candidate file is in git, but the page cache is not — run `fetch` there before `verify`/`import`, and expect a few pages to have changed.

## Editorial checklist — before publishing a draft

1. Open the listing. The **Public-source research** box (sidebar) shows the batch, when it was checked, any flags, and every excerpt with a link to its page.
2. Open the organiser's page. Confirm the group still runs, the schedule, the meeting point and the price are still as stated. A fetched page is not proof a group is active.
3. Resolve the flags — Rise and Run (affiliate-heavy site), Pickleball Perth vs Perth'ect (same Craigie slot), ClayMake (Sunday class vs Sunday closed), Manning Park Trail Runners (verified via WAMC).
4. Click the join link. It must work.
5. Unknown stays unknown. Don't turn a blank price into Free or a blank into "beginners welcome".
6. Check what Google Places pulls in once the page renders (photos, rating). It matches by name; for a group whose Google match is a venue rather than the organisation, tick *Do not show Google reviews or photos here*.
7. Set **Review state → reviewed** and publish. The publish guard holds a batch listing at Pending until it is reviewed and has a way to join.
8. For **Rise and Run Club** and **The Perth Walking Group**, the *Proposed from public sources* box lists what research found. Copy what's right into the fields, then tick *Dismiss*.

## Launching a category

When a category has enough reviewed, published listings (5–10 is the brief's target, not a rule):

```
wp oria sources launch creative-workshops --dry-run
wp oria sources launch creative-workshops
```

That lifts noindex and the sitemap exclusion. It appears in the navigation once it clears the site's category minimum (3 published). At launch, also add the category's tags to `data/specialty-homes.json` if tag facet pages are wanted — a specialty with no home has no page, which is deliberate while drafts are unreviewed.

Suggested order by current strength: creative-workshops (8), pickleball (4, plus Perth Pickleball Centre by hand), running-groups (4 + Manning), ocean-swimming last.

## Known, out of scope

107 existing listings have their ACF `_kind` reference pointing at the Classes repeater's sub-field (`field_oria_cls_kind`) — the original importer writes `kind` by name. Values are correct; only the internal reference is wrong. The new importer writes by key. Not changed here because it touches unrelated listings.
