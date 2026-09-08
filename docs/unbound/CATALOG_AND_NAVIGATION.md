# Unbound source catalog and travel

This release installs the supplied `PokemonUnboundMapsV1.zip` artwork at its exact native dimensions. The PNGs use a 32-pixel display cell, matching the accepted Johto maps: 16-pixel logical tile, 32-pixel display tile, one-cell movement, and the existing 32 × 64 character sprite. No playable image is resized or recompressed.

## Complete source accounting

The collection contains 722 original PNGs. `source-catalog.json` records every filename, dimensions, original SHA-256, RGBA pixel identity, and the `preserved_asset` that retains its exact bytes.

- 694 distinct candidate scenes have native assets; final review makes 618 playable and explicitly leaves 76 unavailable. Named bank/room entries retain their own identities, even when they share artwork.
- 17 uniform blank exports have no playable catalog entry.
- 11 unnamed exports are exact pixel aliases of another catalog scene. These have explicit `alias_of_source` and `alias_of_key` entries. A matching alias never creates another anonymous duplicate in Explore.
- Additional malformed or floorless exports remain present as reviewed unavailable candidates. The 76 individually reviewed unavailable candidates and their evidence are in `playability-manifest.json`; pending review never counts as an intentional exclusion.

Canonical scenes preserve original bytes in `images/worlds/unbound/native`. The 17 blank and 11 alias originals preserve their own distinct bytes in `source-originals`. This avoids a second complete copy of the same assets while allowing recovery of every uploaded PNG. To reconstruct a flat source ZIP:

```sh
python tools/unbound_reconstruct_sources.py /path/to/PokemonUnboundMaps_Originals.zip
```

The recovered entries have the original filenames and exact PNG bytes; ZIP container timestamps and compression metadata may differ from the uploaded archive.

## Presentation

`asset-manifest.json` records 694 WebP previews, each at most 320 pixels on its longest side. Previews are used only in menus. The 83 native scenes larger than 2,048 pixels have 526 lossless 1,024-pixel render crops, verified against every source RGBA pixel. Render assets include unavailable candidates for complete source handling; only reviewed playable scenes enter the runtime list. The active region uses 459 render crops across 71 large playable scenes.

The upload contains no regional overview artwork. The region's hero uses the exact **Frozen Heights** town image and is explicitly labeled as a location preview. It is not presented as a geographic world map. No image generation or invented overview is involved.

## Names and Connected Areas

Named sources determine localities; repeated rooms get stable distinct keys and descriptive section numbers. Service rooms with visually verified identical art are labeled as Pokémon Centers, Link Rooms, Poké Marts, gatehouses, or lifts. Visible numbered department-store signs support its floor labels. Other room and section numbers are menu identities, not inferred native ROM floor numbers.

Unique unnamed exports use visual descriptions. Recognizable earlier versions of supplied localities are grouped with that locality as alternate layouts; unlocated coastal and interior scenes are clearly marked as side expeditions. Malformed tile exports are unavailable rather than repaired by inventing floor or walls.

`navigation-manifest.json` records a reciprocal Connected Areas itinerary, locality rooms, and expeditions. These are Vortex travel adaptations of the supplied scene collection. They do not claim to reconstruct native ROM doors, stairs, image-edge warps, story progression, ferry schedules, HM gates, puzzles, or trainer scripts. All destinations use the independently reviewed arrival data. Active travel contains 618 playable scenes and 617 reciprocal links in one connected graph.

The compiler can choose a reviewed scene in the same source locality as its travel anchor if the original anchor is unavailable. Every such choice is recorded under `locality_anchor_promotions`; this does not invent a source warp.

## Rebuild and validation

From the project root, with Python and Pillow installed:

```sh
python tools/unbound_build_catalog.py /path/to/extracted/UnboundMaps
python tools/unbound_build_assets.py /path/to/extracted/UnboundMaps
# Run the collision and encounter builders described in their companion docs.
python tools/unbound_build_world.py
```

The final compiler requires reviewed collision masks and arrivals, exact encounter assignment coverage, a reciprocal connected active graph, and no pending review. Reviewed invalid scenes must have a fully blocked grid and no arrivals, encounters, transitions, or active links. `--allow-pending` exists solely for intermediate development; it does not meet the release gate.
