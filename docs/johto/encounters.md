# Johto encounter evidence

The 285 nonblank uploaded map records have explicit habitat decisions in
`encounter-assignments.json`: 79 use a wild habitat and 206 remain quiet. Two
quiet records are malformed exports excluded from play, leaving 283 playable
maps: 79 habitats and 204 quiet scenes. The blank
`001_002_New_Bark_Town.png` is excluded from the source catalog. Runtime data is generated in
`public_html/config/worlds/johto_encounters.php`, with 58 profiles and 95 base
species supported in all six existing Vortex varieties.

## Original Gold/Silver data

The primary source is the [pret Gold/Silver disassembly](https://github.com/pret/pokegold)
at commit `656583c939d30f920a316177311a502dd222b57c`. Its README identifies the
verified original Gold and Silver ROM builds. The relevant evidence is bundled
under `tools/johto_encounters_source/`; each file records both its SHA-256 and
its Git blob hash, verified against the upstream commit tree.

- [Land tables](https://github.com/pret/pokegold/blob/656583c939d30f920a316177311a502dd222b57c/data/wild/johto_grass.asm)
  provide each original species and fixed level.
- [Water tables](https://github.com/pret/pokegold/blob/656583c939d30f920a316177311a502dd222b57c/data/wild/johto_water.asm)
  provide each original surfing species and base level.
- [Slot probabilities](https://github.com/pret/pokegold/blob/656583c939d30f920a316177311a502dd222b57c/data/wild/probabilities.asm)
  are 30/30/20/10/5/4/1 for land and 60/30/10 for water.
- The [encounter engine](https://github.com/pret/pokegold/blob/656583c939d30f920a316177311a502dd222b57c/engine/overworld/wildmons.asm)
  adds 0–4 levels to surfing encounters. Its byte comparisons and the
  [percent macro](https://github.com/pret/pokegold/blob/656583c939d30f920a316177311a502dd222b57c/macros/data.asm)
  produce weights 89/76/51/26/14 out of 256. Those outcomes are expanded into
  separate fixed-level entries, preserving their original distribution.

The existing scanner accepts one table per area, so Gold and Silver are mixed
equally, and land tables also mix morning, day and night equally. This keeps
version-exclusive species available without claiming that the scanner simulates
a version selector or an in-game clock. Every original slot retains its species,
weight and level before duplicate rows are combined.

Each resulting original level at or below 24 remains fixed. A resulting level
above 24 becomes a uniform 21–24 range, matching the accepted expansion policy.
Route 29 therefore still has levels 2–4, and the Ruins of Alph inner chamber has
level-5 Unown. This change does not cap owned Pokémon: existing human and AI
progression can still reach level 100.

## Map interpretation and deliberate adaptations

Exact source tables are used for identifiable routes and cave floors. A PNG
containing several floors receives the explicitly listed combined floor tables.
For example, the Slowpoke Well canvas includes both floors and the Bell/Tin
Tower canvas includes the upper rooms. Whirl Islands entrance artwork whose
exact original entrance is unverified receives a documented combination of
original entrance-floor tables. No guessed floor is presented as verified.

Mixed routes and caves use their land tables. Sea routes, Lake of Rage and the
named Dragon’s Den aquatic cavern use water tables. The profile applies to the
entire canvas: it does not switch with the current tile or distinguish furnished
side rooms in a combined canvas. Town streets therefore remain quiet instead of
inheriting their original surfing species. Per-image notes disclose material
combined-room limitations.

Original per-step rates are retained as evidence. Scanner pacing is adapted to
the common overworld grid: 0.32 for caves, ruins and towers; 0.30 for sea, lake and
undersea habitats; 0.28 for other outdoor habitats. Species-slot probabilities
remain independent of those pacing choices and of the variety roll.

Orange Islands, Alola-labelled custom areas, Faraway Zone, Archaic Enclosure,
Dim Deep Cave and Undersea Lymph have authored habitat pools limited to Gen 1/2
species. These are explicitly marked `authored_adaptation`, with the reason for
each choice. They are not Gold/Silver tables. Battle Frontier remains a quiet
facility. Facilities, houses, gates, ships, gyms, roof events, puzzle rooms and
isolated event caves receive no invented random wild encounters.

No random legendary, scripted gift, guaranteed Red Gyarados, fishing, headbutt,
swarm or contest override is added. The original ordinary Gyarados and Dratini
slots remain in their applicable water tables.

## Existing Vortex varieties and sprite repair

The existing shared human/AI roller selects the variety after species and level:
Normal is 311/336, and Shiny, Dark, Mystic, Metallic and Shadow are each 5/336.
All 570 exact species/variety names resolve to the supplied guide or an existing
installation repair seed, and have packaged sprites.

The supplied guide already has six unlettered Unown names, but its existing
artwork is named by letter. Six new unlettered sprite aliases are byte-identical
copies of the matching existing A-form sprites. The builder only creates absent
targets and refuses to replace different artwork. Exact source paths, target
paths and hashes are recorded in `encounter-provenance.json`. Unown letter
unlocking and letter randomization are not simulated.

## Rebuild and verify

```sh
python tools/johto_encounters_build.py
python tools/johto_encounters_build.py --check
php tests/johto_encounters_gate.php
```

The builder needs no network access. `--check` verifies source integrity,
baseline catalogue hashes, exact sprite availability and reproducibility of the
generated PHP and JSON. The PHP gate checks every source slot against the pinned
source line, all map assignments against the runtime manifest, independent
Route 29/Unown/Mantine examples, quiet event rooms, all six varieties, authoritative
battle-level rejection, and the separation between wild and owned level limits.
The five existing Kanto, Hoenn and Vortex encounter catalogue files remain
byte-identical to the accepted baseline.
