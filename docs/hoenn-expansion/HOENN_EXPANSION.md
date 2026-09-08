# Hoenn native map expansion

This release integrates all 74 Emerald map images from the supplied map archive: **71 new areas**, plus **three source aliases** whose existing accepted areas are preserved. Hoenn now contains **136 ready areas**.

## Native presentation

The new map files are copied byte-for-byte into `public_html/html/static/images/worlds/hoenn/native-expansion/`. Their full uploaded width and height are preserved, including intentional source margins and partial outer cells. The exploration viewport scrolls across the native image.

Each original 64-pixel map metatile is subdivided into four 32-pixel collision cells. The manifest uses `display_tile_size: 32` and `tile_size: 8`; this preserves the existing actor scale while supporting the half-tile source crops. Complete grid cells use `floor(width / 32)` and `floor(height / 32)`. Any final partial image edge remains decorative, outside the playable grid.

The expansion includes 71 small previews, each within 320 × 240 pixels, and 426 lossless 1024-pixel render tiles for images larger than 2048 pixels. Large-map rendering never requires a giant scaled background.

## Collision provenance and visual review

Collision comes from the original Emerald layout matrices, rather than a color threshold. Each unsigned little-endian map word contains collision in bits 10–11 (`0x0C00`). The upstream snapshot is [pret/pokeemerald, revision 5eff78649e7170a877b961ef0b3da13b81a16038](https://github.com/pret/pokeemerald/tree/5eff78649e7170a877b961ef0b3da13b81a16038); compact matrix snapshots and SHA-256 values are included under `tools/hoenn-source-data/`.

Original metatile artwork was rendered temporarily to verify each uploaded image’s layout identity and crop translation. All 71 new collision overlays were visually reviewed. The supplied images remain the authority when their artwork differs from the upstream revision:

- Mt. Pyre 3F contains a different tombstone arrangement in two central rows. Eight explicit metatile corrections block the four visible additional graves and open the four visibly empty floor cells. The correction record is `art-collision-corrections.json`.
- Ship images contain black margins; Aqua and Magma rooms include cropped borders; several caves use half-tile offsets. Their exact translations are recorded in `interior-source-alignment.json`.
- A cell intersecting a cropped solid pixel remains blocked. Spawns never clear or replace obstacles.

Open grass, cave floors, sea routes, currents and canonical underwater channels remain traversable. Water inside a canonically solid event chamber stays solid. Underwater darkness alone is not treated as collision: the original matrices correctly preserve the deep traversable channels.

Interior spawn points are selected from clear components containing real entrance or warp locations, with maximum obstacle clearance. The reviewed west-middle Seafloor Cavern chamber has an additional explicit entry because its original one-way ledges cannot be crossed by generic grid walking; the Explore Sections selector exposes it without opening the cliff. This excludes unused roof, plateau and map-border pockets. Sea spawn selection additionally uses original water behavior metadata to exclude inaccessible rock-top pockets. Existing accepted collision files and spawn points are unchanged.

## Travel and interior navigation

Every new area has bidirectional regional connections. Previously isolated late-game seas, Sootopolis, Pacifidlog, Shoal Cave, Seafloor Cavern and Sky Pillar are connected to the expanded route network. Special optional locations receive explicit access links from a relevant existing hub: Artisan Cave from Slateport, Faraway Island from Lilycove, Terra Cave from Route 115, and Marine Cave through Route 127’s underwater area. These are browser-MMO access links; the original game’s temporary weather and event gates are not simulated.

The manifest also contains 130 verified open-cell staircase, ladder, hole and teleport transitions. This includes same-map Aqua Hideout pads, allowing travel among separate rooms. Arrival cells are legal and off the trigger to avoid immediate teleport-back. A solid original door event never causes its collision to be cleared; the area remains accessible through its regional connection.

## Encounters

Each added area uses the separately verified regional encounter catalogue. Dry offices and original event-only chambers correctly have no random wild pool. Applicable water, cave and route maps use their respective species, with the normal Vortex Normal, Shiny, Dark, Mystic, Metallic and Shadow variety handling. The shared runtime enforces the wild level ceiling of 24 for player and AI encounters. Owned Pokémon progression uses the game’s existing team-level rules.

## Source aliases preserved

| Supplied source | Existing area retained | Reason |
| --- | --- | --- |
| `mt-pyre-1f` | `mt-pyre-1f` | Same accepted crop and map artwork at the established presentation size. |
| `mt-pyre-2f` | `mt-pyre-2f` | Same accepted floor and artwork. |
| `shoal-cave-1f-2-low-tide` | `shoal-cave-low-tide` | Same 46 × 38 original-metatile inner-room layout. |

`source-disposition.json` lists every supplied file, exact source dimensions and SHA-256, installed destination or preserved alias, logical/display cell sizes, and render-tile counts. `collision-audit.json` records every new grid and spawn. `portal-audit.json` records all installed and intentionally omitted original warp events.

## Rebuild

Requires Python 3, Pillow, NumPy and SciPy. Extract the user-supplied `pokemon-gba-maps.zip` into a local source folder, then run from the project root:

```bash
python tools/build_hoenn_sea_collision.py --source-dir /path/to/map-source --output-dir /tmp/hoenn-sea
python tools/build_hoenn_expansion.py --sources /path/to/map-source --sea-collision /tmp/hoenn-sea --qa-output /tmp/hoenn-overlays
```

All collision source inputs needed for rebuilding are included; no network access is required. The full upstream game source and temporary rendered comparison artwork are not part of the release.

## Release verification

The Hoenn structural gate passed with 136 ready/reachable areas, 71 new source hashes matching exactly, 426 correctly dimensioned render tiles, and 130 legal source/destination transition pairs. Every connection is bidirectional. All spawn cells are legal. The compact visual review sheet is `hoenn-collision-review.jpg`; the rebuild command can regenerate the complete set of 71 overlays. The shared project PHP runtime and AI gates provide the remaining integration coverage.
