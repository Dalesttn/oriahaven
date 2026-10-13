# Pottery classes batch — 13 October 2026

Thirteen more pottery and ceramics class providers for **Pottery & Creative Workshops** (`creative-workshops`, source key `creative`). The category is still **pending launch**: it's noindexed and kept out of the sitemap and navigation until `wp oria sources launch creative-workshops`.

Everything imported is a **draft**. Nothing is published and no studio was contacted.

| Candidates | New drafts | Manual | Rejected |
|---:|---:|---:|---:|
| 13 | 13 (#901306–#901318 locally) | 0 | 14 |

All 13 were verified against each studio's own website: every claim has a verbatim excerpt from the cited page.

**Excluded:** the 8 pottery drafts from the 26 September batch (ClayMake, Hound, Perth Pottery Co, Studio P, Porch, Winterwares, Fremantle Arts Centre, Canning Arts Group), and the 4 studios already marked for manual review (Rediscover, Prickly Pear, Kracking, Perth Artists' Collective).

## The studios

| Studio | Area | From | Beginners |
|---|---|---|---|
| MUK Studio | O'Connor (Fremantle & South) | $80 | yes |
| House of Hobby | West Perth | course only, see note | yes |
| Lien Nguyen Ceramics | Noranda (Perth Central) | $300 (1:1, 3 hr) | yes |
| South of the River Potters' Club | Alfred Cove (Fremantle & South) | $95 | yes |
| From Dust Studio | Willetton | not published | not stated |
| Terraform Studio | Mandurah | $75 | yes |
| Earth & KLĀ | Wembley | $120 | yes |
| Happy Hub Collective | Fremantle | $89 (painting; clay classes $124–132) | yes |
| Perth Studio Potters | Cottesloe | course only ($360 for 8 sessions) | yes |
| Not Yet Perfect | Applecross | $179 | yes |
| Potteroo | Mount Claremont | not shown (site gives $65 and $75) | not stated |
| Rockingham Ceramics Studio | Rockingham | course only ($395 for 8 weeks) | yes |
| Midland Junction Arts Centre | Midland | course only ($455 for 8 sessions) | yes |

"From" is the listing's per-session price. Course totals are kept in each listing's price note rather than shown as a per-session price.

## Check before publishing (also in each record's flags)

- **No published street address:** Lien Nguyen (home studio), Earth & KLĀ, Happy Hub and Rockingham.
- **Suburbs not in the area list:** O'Connor, Noranda and Alfred Cove are filed under their region.
- **Bookings on other platforms:** From Dust (Eventbrite) and Happy Hub (ClassBento). From Dust's site is thin (© 2023).
- **Sold out or full now:** all of Earth & KLĀ's workshops, Lien Nguyen's dates, and Rockingham's Term 4 classes.
- **18+ only:** House of Hobby, Perth Studio Potters, Midland Junction and South of the River.
- **Potteroo:** classes are for all ages, and its price conflicts ($65 vs $75).
- **Terraform:** also trades as Get Muddy Workshops.
- **Rejected,** with reasons in manifest.json:
  - Guildford Village Potters (classes paused for renovations)
  - Andrew Tremain (no classes)
  - Stackwood (a venue, not a studio)
  - Graham Hay (online course only)
  - Mundaring Arts Centre (no current ceramics classes)
  - North Metro TAFE (accredited course)

## Commands (WordPress root)

```
wp oria sources fetch  --batch=2026-10-13-pottery
wp oria sources verify --batch=2026-10-13-pottery
wp oria sources import --batch=2026-10-13-pottery
# after reviewing and publishing the studios:
wp oria sources launch creative-workshops
```
