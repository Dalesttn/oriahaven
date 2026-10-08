# Creative therapies batch — 8 October 2026

More creative arts therapists for **Creative Therapies** (practice `creative`, #223 — reused, untouched; the only new term is the specialty tag `drama-therapy`). Source category key: `creative-therapy` in `data/source-categories.json`.
Everything imported is a **draft**. Nothing is published and no practitioner was contacted.

## Results

| Candidates | New drafts | Manual | Rejected |
|---:|---:|---:|---:|
| 9 | 9 (#901221–#901229) | 19 | 37 |

All 9 verified: every material field cites words found on the practice's own site (cached in `uploads/oria-sources/cache/`). The 19 "manual" are real but not verifiable automatically (site down, domain gone, directory/Facebook only); 37 were out of scope (not WA, no longer offered, generalist counsellors, large providers). `candidates.json` has every record with its reason.

Perth has few creative arts therapists with their own website beyond the 19 already listed; no dance-movement or drama therapist could be verified (both found were directory-only).

## Before publishing each draft

Use the **Public-source research** box (sidebar) and the checklist in `../2026-09-26-activities/README.md`. Batch-specific flags:

- **Trulybe** (Glen Forrest): creative arts therapy is one of several services (also energy and craniosacral work); Murdoch postgraduate diploma, no ANZACATA registration stated.
- **Honeywood Music** (Leeming): one page mentions a "Wandi studio space"; elsewhere Leeming. Prices are $117.12 / 45 min and $156.16 / 60 min (NDIS-style), so price_from is left blank.
- **Attuned Health**: in-home sessions across the metro area; region set to Fremantle only because the office is in Palmyra. No published price.
- **Heart Song**: site says "City of Walyalup/Fremantle" only — suburb left blank. Tagged art therapy only (not an RMT).
- **Paperbark Grove Collective** (michellematchamtherapy.com): art therapy diploma from a Canadian institute; registered as a mental health social worker, not ANZACATA. Fees and contact pages 404. Host rate-limits.
- **Heart and Play Therapy** (Hilton): site dated 2023 — confirm still operating.
- **Artsense** (Dunsborough): ANZACATA affiliate (not registered), NDIS registration in progress. **No area term** — Dunsborough is not in the area tree; set the area by hand.

## Commands (from the WordPress root)

```
wp oria sources setup                       # creates the drama-therapy tag; creative is reused
wp oria sources fetch  --batch=2026-10-08-creative-therapies
wp oria sources verify --batch=2026-10-08-creative-therapies
wp oria sources import --batch=2026-10-08-creative-therapies --dry-run
wp oria sources import --batch=2026-10-08-creative-therapies
wp oria sources rollback --batch=2026-10-08-creative-therapies   # trashes unedited, unreviewed drafts
```

On production the page cache is not in git: run `fetch` before `verify`/`import`. Local note: the CLI PHP needs `-d extension=curl -d extension=openssl`, or every robots.txt "could not be read" and the fetch log marks the host blocked (clear `fetch-log.json` requests before retrying).
