# Kanto / Sevii native map expansion — v27.0.0

This import adds **49 supplied areas** to the **45 accepted Kanto areas**, giving **94 playable Kanto / Sevii areas**. Every accepted area's artwork, dimensions, collision grid and saved coordinate scale remain unchanged. Parent connections gain links to the new areas.

## Native presentation and movement

The new WebP files in `images/worlds/kanto/native` are byte-for-byte copies of the uploaded source. Their native width and height are retained, including the long One Island composite and the source's small edge crops. The world view scrolls over that native image; there is no fit-to-window conversion of the playable art.

The supplied art is at four times GBA resolution. New maps use an 8-unit logical collision cell displayed at 32 pixels, giving two movement cells across one original 16-pixel GBA metatile. This subdivision supports the half-tile crop offsets in the uploaded images without changing their native presentation. A partial outer cell remains visible but is outside playable coordinates. Character art retains the four-times native sprite scale.

Each area has a lightweight preview (maximum 384 × 240). Images larger than 2048 pixels on either axis also ship complete, source-derived 1024-pixel PNG render tiles, including correctly sized edge tiles.

## Collision provenance and registration

Collision is derived from the original authored FireRed map-grid collision field (`0x0C00`), rather than an inferred color mask. The original binary layout snapshots are bundled under `tools/kanto_source_data` with SHA-256 hashes and source paths. See [the source field definitions](https://github.com/pret/pokefirered/blob/master/include/global.fieldmap.h) and [map-grid lookup implementation](https://github.com/pret/pokefirered/blob/master/src/fieldmap.c).

During preparation, the original map was reconstructed from its indexed tiles, metatiles and palettes solely to align collision. Matching visual features between that reconstruction and the uploaded artwork established each exact crop offset; the import records 129–2266 agreeing feature matches per ordinary area. The reconstructed artwork is not used as the game's map image.

- One Island combines its town, Kindle Road and Treasure Beach layouts at their actual source positions.
- Two Island combines its town and Cape Brink.
- The first Mt. Ember Ruby Path image repeats a 16-pixel source column. Its collision has the same documented repeated column.
- Every pixel of a 32-pixel movement cell must belong to authored traversable geometry. This keeps composite offsets conservative around walls.
- Arrival points are selected from existing open components, preferring land. No obstruction is cleared to create a spawn. Tiny isolated components, water-only fragments and edge fragments without an authored warp are excluded from arrival choices. Original cave-map backgrounds contain some open-flag decorative metatiles beyond their enclosing cliffs; these never become selectable arrivals.
- Authored traversable water follows the project's existing regional movement abstraction. This import does not add Surf, waterfall, boulder-pushing, switch or story-puzzle mechanics. Original static gate barriers remain represented by their authored collision.

The same published collision JSON is consumed by the existing authoritative regional movement path for players and AI. The manifest's connections link new floors and landmarks to their existing towns/routes and provide return paths. In addition, 128 authored stair/ladder/door warps are installed as 508 collision-aligned trigger cells across 42 new areas. Both the trigger and landing cell must already be open. Arrival is placed beside the destination warp, outside all trigger cells, preventing an immediate bounce. No collision cell is opened to install a warp.

The 217 authored source warp records are fully accounted for in `kanto-warp-audit.json`: 128 installed; 54 lead outside the newly supplied source set; 34 are blocked or outside a source crop; one has a destination outside its supplied crop. Accepted existing areas retain their original transitions. The final new-area manifest exposes 86 vetted arrival sections; 13 sections behind original static puzzle gates or omitted/cropped connections use the game's explicit section-entry choice. `kanto-section-access-audit.json` lists those sections.

## Source disposition and corrected labels

`kanto-source-manifest.json` accounts for all **55 supplied FireRed files**:

| Disposition | Files |
|---|---:|
| New native areas imported | 49 |
| Existing Route 23, Route 25 and Safari Zone Areas 1–3 preserved | 5 |
| Byte-identical duplicate imported once | 1 |

The supplied forest filenames transpose the names of two locations. The actual Six Island artwork is Pattern Bush (60 × 32 original metatiles); the Three Island artwork is Berry Forest (57 × 47). Public labels and encounter profiles follow the artwork. The Pattern Forest `-1` file has the same SHA-256 as its sibling.

The supplied Victory Road `1f` image is the canonical central 2F map, while `b1f` is a crop of the canonical 1F entry cavern. New public labels are **Central Cavern** and **Entry Cavern**; the accepted pre-existing Victory Road area is preserved.

Each new manifest entry uses the shared region encounter catalog. Indoor areas with no original random wild encounters keep an empty encounter profile. Encounter levels and Vortex variants are enforced by the shared encounter runtime; original map files do not contain independent level rolls.

## Rebuilding and review

From the project root, run:

```sh
python tools/kanto_rebuild_collision.py
python tools/kanto_rebuild_collision.py --render-assets --qa-dir /tmp/kanto-map-review
python tools/kanto_rebuild_transitions.py
```

Run the transition rebuild after the collision rebuild to restore authored triggers and final arrival filtering. The scripts check artwork and binary hashes before rebuilding. It requires Pillow, NumPy and SciPy, works offline from the installed native assets and bundled collision sources, and updates only the new areas' collision and arrival coordinates. `--source-dir` can instead point at the extracted uploaded map archive. `--render-assets` also rebuilds the previews and PNG render tiles.

The five `kanto-collision-atlas-*.jpg` review sheets show every new area: red indicates blocked cells; cyan dots indicate safe section arrival choices. Full collision dimensions, open-cell counts, component sizes, source hashes and registered layout layers are recorded in each collision JSON.
