# Health food stores batch — 9 October 2026

New practice sub-category **Health Food Stores** (`health-food-stores`, #690, under Nutrition & Healthy Living) — **pending launch** (noindex, out of the sitemap and navigation) until `wp oria sources launch health-food-stores`. Source category key `health-stores`; filters (specialty tags): organic-produce, bulk-refill, supplements, in-store-naturopath, store-cafe.

Everything imported is a **draft**. Nothing is published and no store was contacted.

| Candidates | New drafts | Manual | Rejected |
|---:|---:|---:|---:|
| 12 | 12 (#901230–#901241) | 11 | 8 |

All 12 verified against their own websites. Each listing shows a "store at a glance" panel (range, bulk & refill, dietary ranges, practitioner in store, café, delivery/click-and-collect, opening hours) with its own wording (`glance` in `data/source-categories.json`).

The category's intro and FAQ come from `tools/health-stores-content.php` (names, not counts — **if a store is not published, remove it from the FAQ** on the term's edit screen).

## Already listed — not changed
- **Magic Apple Wholefoods** (#900307, Cottesloe): site is JS-only Wix; the only readable text calls it "a complete wholefoods restaurant" — check it is still a store before adding it to this category.
- **Manna Wholefoods & Café** (#900308, South Fremantle): site refuses automated requests (403). Add to the category by hand if you're happy it's still trading.

## Check before publishing (also in each record's flags)
- **Sam's Health Emporium** (Willetton) trades as "Go Vita Southlands" (Go Vita co-op); supplement-heavy, but the only store with naturopaths stated in store.
- **Scoop Wholefoods Cottesloe**: "family-run", but the Scoop name may be a wider network.
- **The Wellness Store** (Mandurah): teas, herbs, supplements and coffee; no bulk or produce — weakest fit.
- **Pantryman** (Mandurah): some mainstream lines; its Rockingham store shares the page.
- **Bassendean Wasteless Pantry**: name word order follows the page; you may prefer "Wasteless Pantry Bassendean". Neither Wasteless Pantry page gives a street address.
- **Earth Wholefoods**: practitioners are nutritionists (not naturopaths); café page says Sunday 10–2 but the contact page says Sunday closed (contact page used).
- **Loose Produce**: no published hours; BYO containers not claimed (FAQ page still has template text).
- **The Little Big Store**: no street address. **Replenish**: footer © 2017–2021, hours may be stale. **The Organic Collective**: mainly box delivery, with a retail shop.

## Commands (WordPress root)

```
wp oria sources setup
wp oria sources fetch  --batch=2026-10-09-health-stores
wp oria sources verify --batch=2026-10-09-health-stores
wp oria sources import --batch=2026-10-09-health-stores
wp eval-file wp-content/plugins/oria-core/tools/health-stores-content.php
# after reviewing and publishing the stores:
wp oria sources launch health-food-stores
```
