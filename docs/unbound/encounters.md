# Unbound encounter habitats

The new region uses the same server-side encounter, battle-level and independent Vortex-variety pipeline as Kanto, Hoenn and Johto. Human trainers and AI trainers read the same `unbound_encounters.php` profiles. No database reset, guide replacement or change to previously accepted regional pools is required.

The pools are **authored MMO habitats**, selected from the supplied map artwork and the Pokémon guide and type data already shipped in this project. They are not presented as original Pokémon Unbound ROM encounter tables. Source filenames are locality labels, not reliable encounter metadata: a named town can include underwater rooms, furnished facilities and multiple other sections.

## Installed coverage

All **666 ordinary supported species from Generations 1–6** occur in suitable habitats, with **3,996 exact species/variety identities**. This is coverage of this project's installed guide and repaired identities; it is not a claim that its historical guide contains every canonical national-dex species.

| Generation | Ordinary species in Unbound |
| --- | ---: |
| 1 | 145 |
| 2 | 94 |
| 3 | 125 |
| 4 | 93 |
| 5 | 143 |
| 6 | 66 |

The generation boundaries follow the existing ordered guide: Bulbasaur, Chikorita, Treecko, Turtwig, **Victini**, Chespin and Rowlet. Existing repair seeds supply Ditto, Farfetchd, Nidoran (F), Nidoran (M), Mime Jr. and Flabebe. No new national-dex species or guide rows were invented.

The historical catalogue has a specific Generation 1 gap: **Mr. Mime has local artwork but no species row in either shipped guide SQL file and no established repair seed**. It is therefore excluded from these pools. Generation 1 has 150 supported guide species, of which five remain reserved, yielding 145 ordinary species. Existing `Mime Jr.` support does not create a `Mr. Mime` guide row. `Ho-oh`, the spelling in the installed guide, is explicitly reserved alongside canonical `Ho-Oh`.

`encounter-provenance.json` contains the exact current profile, source-map and generation counts. `encounter-assignments.json` records a habitat or quiet decision for every retained source. `encounter-species-availability.json` lists each existing guide species, types, generation, local sprite readiness and assigned profiles. Final release checks require every counted species to occur in an actually ready map. Collision-rejected source canvases cannot be counted as coverage.

## Habitats and levels

Snow routes and ice caves, forests, flower meadows, grassland, coastal routes, open sea, underwater passages, freshwater, marsh, volcanic caverns, mountains, electric caverns, ancient ruins, sewers and spectral areas use separate pools. Type-based assignments have explicit physiological overrides: freshwater Pokémon are separated from marine species; desert, forest, pollution-tolerant and ghost species have appropriate homes. Distinct reviewed canvases have distinct pools; identical artwork in the same locality shares a profile.

Each available ordinary species receives up to two suitable home profiles. Existing evolution rules keep later evolutions out of very early habitats. Stable selection makes builds reproducible. Common base species have weight 20 and evolved species weight 8, with exact entries recorded in generated configuration. This is an authored distribution, not a claim about native Unbound rarity.

Early habitats begin at **levels 2–5** and progress through intermediate ranges. High-level habitats use **21–24**. The shared wild-level guard remains active, and the resulting encounter level is retained by the battle session. Existing evolution requirements and player/AI-owned Pokémon progression are unchanged; the wild cap does not limit owned Pokémon to level 24.

The existing six-way roll follows species selection:

| Variety | Probability |
| --- | ---: |
| Normal | 311 / 336 |
| Shiny | 5 / 336 |
| Dark | 5 / 336 |
| Mystic | 5 / 336 |
| Metallic | 5 / 336 |
| Shadow | 5 / 336 |

Towns, occupied facilities, furnished interiors, transit corridors, raid pens, scripted chambers and malformed/unverified exports stay quiet. Legendary, mythical and special Ultra Beast species remain reserved. There is no invented roaming legendary, raid battle or guaranteed event capture. The custom Darkrown is not added to these regional wild pools.

Like the accepted regional scanner, encounters apply to the **whole area**, rather than to individual tile biomes. Collision determines where trainers can stand. Traversable grass, stairs and glass remain valid walking surfaces; walking across them does not require a separate terrain encounter implementation. Water Pokémon use designated coastal/water/undersea areas, rather than being assigned across ordinary city streets.

## Existing sprite-name repairs

The existing guide uses some neutral species names while its local art pack uses named forms. The build adds **96 byte-identical aliases**, copying the correct existing sprite separately for each of the six Vortex varieties. Existing files are never overwritten with different bytes.

| Existing guide name | Existing artwork used |
| --- | --- |
| Burmy | Burmy (Plant) |
| Wormadam | Wormadam (Plant) |
| Cherrim | Cherrim (Non Sunny) |
| Shellos | Shellos (West) |
| Gastrodon | Gastrodon (West) |
| Basculin | Basculin (Red Stripe) |
| Deerling | Deerling (Spring) |
| Sawsbuck | Sawsbuck (Spring) |
| Vivillon | Vivillon (Meadow) |
| Flabebe | Flabebe (Red) |
| Floette | Floette (Red) |
| Florges | Florges (Red) |
| Meowstic | Meowstic (M) |
| Aegislash | Aegislash (Shield) |
| Pumpkaboo | Pumpkaboo (Average) |
| Gourgeist | Gourgeist (Average) |

These are fixed presentation defaults. They do not implement automatic gender, seasonal, flower, stance or cloak changes. The earlier Johto Unown aliases remain unchanged. Reserved Meloetta and Xerneas neutral names are not repaired solely for ordinary wild encounters.

## Generation 7: exact local asset requirement

The accepted guide already contains **74 Generation 7 base species**, but the supplied project contains none of their **444 required exact variety GIFs**. Those incomplete species are not included in current playable wild pools. The official [Rowlet Pokédex](https://www.pokemon-vortex.com/pokedex/Rowlet) and [Vortex wiki](https://wiki.pokemon-vortex.com/wiki/Rowlet) confirm real Vortex art/varieties exist; they are not local project assets. An attempted direct public asset acquisition was not completed: the tool reported `network approval was cancelled before a decision was returned`. No cancelled download route was retried or bypassed.

`encounter-gen7-import.json` lists all 74 guide identities and every exact missing filename, including reserved species. The build supports offline completion:

1. Put authentic GIFs in one local folder, using exact filenames from the missing-assets list. For each ordinary species you want to enable, provide **all six** varieties: `Rowlet.gif`, `Shiny Rowlet.gif`, `Dark Rowlet.gif`, `Mystic Rowlet.gif`, `Metallic Rowlet.gif` and `Shadow Rowlet.gif`, for example. A complete set can include matching files already installed locally.
2. From the project root, run:

   ```bash
   python tools/unbound_encounters_build.py --import-gen7 /path/to/authentic/gifs
   python tools/unbound_build_world.py
   python tools/unbound_encounters_build.py --check --self-test
   ```

3. Run `tests/unbound_encounters_gate.php` and the regional player/AI gates before deploying the updated package.

The importer validates all supplied filenames, GIF decoding, all six required files and no-overwrite constraints before copying the batch. It accepts real GIF files only, not PNG bytes renamed `.gif`, HTML errors or URLs. It cannot establish visual authenticity from a filename; use the actual corresponding Pokémon and variety artwork. It does not recolor or synthesize sprites. Unknown files, incomplete offered species sets, invalid GIFs and attempts to overwrite different existing assets are rejected.

After a valid import, the same builder automatically includes locally complete ordinary Generation 7 species in compatible authored habitats. Reserved special species stay reserved even if their artwork is installed. There are no runtime downloads, fallback Normal sprites for special varieties, or new database entries. If any required sprite is removed, rebuilding excludes that species again.

## Rebuilding and validation

```bash
python tools/unbound_encounters_build.py
python tools/unbound_encounters_build.py --check --self-test
php tests/unbound_encounters_gate.php
```

Pillow is required for GIF validation. The normal builder and `--check` do not use network access. `--check` is read-only; approved aliases must already exist. The self-test uses a temporary isolated fixture directory and removes it afterwards. Fixture images test file/variety completeness and actual profile activation, and are never installed as Pokémon artwork.

The Python audit verifies deterministic output, unchanged accepted database/encounter files, exact sprite availability, generation membership, explicit quiet decisions and complete ordinary species coverage. The PHP gate checks actual runtime profile loading, all ready maps, exact guide identities, every variety window, bounded levels, concrete reviewed habitat examples and the battle level authority. The shared player/AI gates cover actual encounter, capture and owned-level progression behavior separately.
