"""
Gather the outside evidence for the weak-page audit.

    python weak-pages-collect.py <gsc-export.json>

Runs on a desktop, never on the server: it needs a Search Console export,
and the server has no Search Console access. It reads the live site over
HTTP only -- sitemaps, redirects and public page markup -- so it writes
nothing anywhere except its own output file:

    oria-core/data/weak-pages-input.json

which tools/weak-pages.php reads on the server, where the database is.

The export is 90 days of Search Console data with the `page` dimension.
Either the gscServer tool's raw output file ({"result": "<json>"}) or a
plain {"rows": [...]} file is accepted.

WHAT IT WORKS OUT

Impressions per URL, credited to where the URL lives NOW. On 1 September
2026 most of the site moved to /explore/, and Search Console still holds
the old addresses' impressions under the old addresses. Without following
those 301s, every migrated page reads as a page nobody has ever seen,
which is exactly the wrong conclusion. Every Search Console URL that is
not in the sitemap is requested once, and its impressions and clicks are
added to wherever it lands.

Which archive pages say the same thing. Each category, facet, suburb and
combination page publishes its listings as an ItemList. Two pages whose
listing sets are near-identical are one page with two addresses, however
different their headings -- that is "very little distinct information",
measured rather than guessed.
"""

import json
import re
import subprocess
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from datetime import date, datetime, timezone
from itertools import combinations
from pathlib import Path
from urllib.parse import urlparse

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

BASE = "https://oriahaven.com.au"
OUT = Path(__file__).resolve().parent.parent / "data" / "weak-pages-input.json"

# Two archive pages overlapping this much are one page with two addresses.
OVERLAP = 0.8
# Below this, a page is too small for overlap to mean anything -- three
# listings in common between two three-listing pages is noise.
OVERLAP_MIN = 4

WORKERS = 4  # polite: the site is on shared hosting


def get(url: str, follow: bool = True) -> tuple:
    """(status, final_url, body). curl -4 with retries: some routes to the CDN drop connections."""
    args = ["curl", "-4", "-s", "--max-time", "45", "-A", "Mozilla/5.0 (Oria Haven weak-page audit)",
            "-w", "\n__META__%{http_code} %{url_effective}"]
    if follow:
        args.append("-L")
    for _ in range(5):
        p = subprocess.run(args + [url], capture_output=True)
        if p.returncode == 0 and b"__META__" in p.stdout:
            body, meta = p.stdout.rsplit(b"\n__META__", 1)
            code, final = meta.decode().split(" ", 1)
            return int(code), final.strip(), body.decode("utf-8", "replace")
        time.sleep(2)
    return 0, url, ""


def norm(u: str) -> str:
    u = u.split("#")[0].split("?")[0]
    return u.rstrip("/") + "/"


def locs(xml: str) -> list:
    return [u for u in re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", xml)
            if not re.search(r"\.(jpe?g|png|webp|gif|svg)$", u, re.I)]


def kind(u: str) -> str:
    p = urlparse(u).path
    for pat, k in [
        (r"^/listing/", "listing"),
        (r"^/area/[^/]+/[^/]+/[^/]+/$", "suburb"),
        (r"^/area/[^/]+/[^/]+/$", "region"),
        # A specialty facet and a suburb combination share one shape --
        # /explore/perth/spa/cold-plunge/ and /explore/perth/spa/ellenbrook/
        # -- and both are archives, which is all the audit needs to know.
        (r"^/explore/[^/]+/[^/]+/[^/]+/([^/]+/)?$", "explore"),
        (r"^/explore/[^/]+/[^/]+/$", "category"),
        (r"^/journal/.+", "journal"),
        (r"^/best/.+", "best-of"),
        (r"^/compare/.+", "compare"),
        (r"^/apps/category/", "app-category"),
        (r"^/apps/.+", "app"),
        (r"^/guides/.+", "app-guide"),
        (r"^/events/|^/event-type/", "event"),
    ]:
        if re.search(pat, p):
            return k
    return "page"


ARCHIVES = {"suburb", "region", "explore", "category"}


def load_gsc(path: str) -> tuple:
    raw = json.load(open(path, encoding="utf-8"))
    if isinstance(raw, dict) and "result" in raw and isinstance(raw["result"], str):
        raw = json.loads(raw["result"])
    rows = raw["rows"]
    window = raw.get("date_range", {})
    return rows, window


def main() -> None:
    if len(sys.argv) < 2:
        sys.exit("usage: python weak-pages-collect.py <gsc-export.json>")

    rows, window = load_gsc(sys.argv[1])
    if len(rows) < 200:
        sys.exit(f"Only {len(rows)} rows in the export. That is not 90 days of this site -- refusing to write an input that would read as 'nobody ever saw anything'.")

    print(f"Search Console: {len(rows)} URLs, {window.get('start', '?')} to {window.get('end', '?')}")

    # ---- what the site asks Google to index --------------------------------
    code, _, index = get(BASE + "/sitemap_index.xml")
    if code != 200:
        sys.exit(f"sitemap index returned {code}")
    urls = {}
    for m in locs(index):
        c, _, xml = get(m)
        if c != 200:
            sys.exit(f"{m} returned {c} -- a partial inventory would make every missing page look like a zero")
        for u in locs(xml):
            urls[norm(u)] = {"url": norm(u), "kind": kind(u), "impressions": 0, "clicks": 0, "position": None, "via": []}
    print(f"Sitemaps: {len(urls)} indexable URLs")

    # ---- credit impressions to where each URL lives now -------------------
    gsc = {}
    for r in rows:
        u = norm(r["page"])
        g = gsc.setdefault(u, {"impressions": 0, "clicks": 0, "pos_w": 0.0})
        g["impressions"] += int(r.get("impressions", 0))
        g["clicks"] += int(r.get("clicks", 0))
        g["pos_w"] += float(r.get("position") or 0) * int(r.get("impressions", 0))

    stray = [u for u in gsc if u not in urls and u.startswith(BASE)]
    print(f"Following {len(stray)} addresses Search Console knows but the sitemap does not ...")

    def land(u):
        c, final, _ = get(u)
        return u, c, norm(final)

    moved = {}
    with ThreadPoolExecutor(WORKERS) as pool:
        for u, c, final in pool.map(land, stray):
            if c == 200 and final in urls and final != u:
                moved[u] = final

    for u, g in gsc.items():
        target = u if u in urls else moved.get(u)
        if not target:
            continue
        t = urls[target]
        t["impressions"] += g["impressions"]
        t["clicks"] += g["clicks"]
        t.setdefault("_pos_w", 0.0)
        t["_pos_w"] += g["pos_w"]
        if target != u:
            t["via"].append(urlparse(u).path)

    for t in urls.values():
        pw = t.pop("_pos_w", 0.0)
        t["position"] = round(pw / t["impressions"], 1) if t["impressions"] else None

    print(f"  {len(moved)} redirected into a current page; the rest are gone, noindexed or duplicates")

    # ---- what each archive page actually lists -----------------------------
    arch = [u for u, t in urls.items() if t["kind"] in ARCHIVES]
    print(f"Reading the ItemList on {len(arch)} archive pages ...")

    def items(u):
        c, _, h = get(u)
        found = set()
        for block in re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', h, re.S):
            if '"ItemList"' in block:
                found |= set(re.findall(r"https://oriahaven\.com\.au/listing/[a-z0-9-]+/", block))
        # Yoast writes the robots meta with single quotes.
        m = re.search(r"""<meta name=["']robots["'] content=["']([^"']*)""", h)
        return u, c, sorted(found), (m.group(1) if m else "")

    with ThreadPoolExecutor(WORKERS) as pool:
        for u, c, found, robots in pool.map(items, arch):
            urls[u]["listings"] = found
            urls[u]["status"] = c
            urls[u]["robots"] = robots

    # A page that says noindex while the sitemap advertises it. Google gets
    # two answers, and the listing-count floors are meant to make these two
    # agree -- so every one of these is a gate that has drifted.
    disagree = [u for u in arch if "noindex" in urls[u].get("robots", "")]
    print(f"  {len(disagree)} archive pages are in the sitemap but tell Google noindex")

    # ---- archive pages that are the same page ------------------------------
    pairs = []
    sets = {u: set(urls[u].get("listings", [])) for u in arch}
    for a, b in combinations(arch, 2):
        A, B = sets[a], sets[b]
        if min(len(A), len(B)) < OVERLAP_MIN:
            continue
        inter = len(A & B)
        if not inter:
            continue
        jac = inter / len(A | B)
        # A small page that sits entirely inside a bigger one is the same
        # finding from the other side: it adds nothing the bigger one lacks.
        sub = inter / min(len(A), len(B))
        if jac >= OVERLAP or (sub == 1.0 and min(len(A), len(B)) / max(len(A), len(B)) >= 0.75):
            keep, drop = sorted((a, b), key=lambda u: (-urls[u]["impressions"], -len(sets[u]), len(u)))
            pairs.append({
                "keep": keep, "drop": drop,
                "overlap": round(jac, 2), "shared": inter,
                "keep_n": len(sets[keep]), "drop_n": len(sets[drop]),
                "keep_imp": urls[keep]["impressions"], "drop_imp": urls[drop]["impressions"],
            })
    pairs.sort(key=lambda p: (-p["overlap"], -p["shared"]))
    print(f"  {len(pairs)} pairs of archive pages overlap by {int(OVERLAP * 100)}% or more")

    out = {
        "generated": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "window": {"start": window.get("start"), "end": window.get("end"), "days": 90},
        "migrated": "2026-09-01",
        "overlap_threshold": OVERLAP,
        "urls": sorted(urls.values(), key=lambda t: t["url"]),
        "overlaps": pairs,
        "sitemap_noindex": sorted(disagree),
    }
    # The listing sets were only needed to find the overlaps above, and the
    # old addresses only to credit their impressions -- keep the counts, drop
    # the lists, so a file committed every month stays small. One URL per
    # line keeps the monthly diff readable.
    for t in out["urls"]:
        t["listed"] = len(t.pop("listings", []) or [])
        t["moved_in"] = len(t.pop("via", []))
    urls_out = out.pop("urls")
    head = json.dumps(out, ensure_ascii=False, indent=1)[:-2]
    body = ",\n".join("  " + json.dumps(t, ensure_ascii=False) for t in urls_out)
    OUT.write_text(head + ",\n \"urls\": [\n" + body + "\n ]\n}\n", encoding="utf-8")
    print(f"\nWrote {OUT}")


if __name__ == "__main__":
    main()
