# The weekly events file

A hand-curated batch of Perth wellness events, imported with:

```
wp oria events import events/week-2026-09-22.json --dry-run
wp oria events import events/week-2026-09-22.json
```

Events arrive as **drafts**. Add `--publish` only when the whole file has
already been reviewed.

## Shape

```json
{
  "generated": "2026-09-22",
  "city": "Perth",
  "events": [
    {
      "title": "Sunset Sound Bath",
      "start": "2026-10-04 18:00:00",
      "end": "2026-10-04 19:30:00",
      "type": "sound-healing",
      "venue": "Sir James Mitchell Park",
      "suburb": "south-perth",
      "price": "$35",
      "free": false,
      "description": "Ninety minutes of gongs and bowls on the foreshore. Bring a mat.",
      "booking_url": "https://studio.example/book/sunset-sound-bath",
      "source_url": "https://studio.example/whats-on",
      "organiser": "Example Studio",
      "listing_slug": "example-studio-south-perth",
      "checked": "2026-09-22"
    }
  ]
}
```

A bare array of event objects works too; the wrapper is for a human reader.

| Field | Required | Notes |
| --- | --- | --- |
| `title` | yes | As the organiser writes it. |
| `start` | yes | Perth local time. Must be in the future and within 120 days. |
| `end` | no | Must be after `start`. |
| `type` | yes | An existing `event_type` slug (below). An unknown one rejects the row. |
| `source_url` | yes | The page the details were read from. |
| `booking_url` | no | Falls back to `source_url`. |
| `venue` | no | Venue name only; the suburb is appended for display. |
| `suburb` | no | An `area` term slug. Anything else still imports, it just joins no area page. |
| `price` | no | Free text as published: `$35`, `$25–$40`, `By donation`. |
| `free` | no | `true` writes "Free" and overrides `price`. |
| `description` | no | **Written by us**, never pasted from the source. |
| `organiser` | no | Display name when no listing exists. |
| `listing_slug` | no | Connects the event to an Oria listing ("Run by"). Ignored if no such listing. |
| `checked` | no | The date the details were verified. Defaults to import time. |

Event types: `breathwork`, `cold-plunge`, `community`, `fitness`,
`meditation`, `mens-group`, `mindfulness`, `nutrition`,
`personal-development`, `retreat`, `sauna`, `sound-healing`, `spiritual`,
`relaxation`, `wellness-workshop`, `womens-circle`, `yoga`.

## Rules the file has to honour

- **Write our own descriptions.** Never paste an organiser's copy.
- **No images.** Organisers supply their own through the submission form;
  everything else uses the category tile. The importer ignores image fields.
- **Only events that are really on.** Every row names the page it came from
  and the date it was checked.
- **No ClassPass or aggregator collection.** Organiser and venue pages only.

## What the importer does

- Rejects rows that are incomplete, in the past, too far out, or typed wrong,
  and prints why. A bad row never blocks the rest of the file.
- Skips anything already on the site, using the same fingerprint and fuzzy
  same-day title match the nightly crawler uses, so the two never collide.
- Re-importing a corrected file **updates** the drafts it created before,
  and never touches a member's event, a crawler find, or anything already
  published and edited by hand.
