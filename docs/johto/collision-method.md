# Johto collision reconstruction

The source bundle contains rendered map artwork, without a ROM, map-layout
collision bits, metatile behavior table, or engine warp metadata. These masks
are a **visual reconstruction**, not an extraction of native collision data.

The retained 285 images each correspond exactly in dimensions to a native BMP
in the original supplied archive. The displayed PNGs are 2× native dimensions:
one native 16×16 metatile occupies one displayed 32×32 movement cell. Native
artwork is used for signatures because interpolation in the displayed PNGs
creates neighbor-dependent edge variations. No screenshot downsampling is used
to infer engine collision.

The reusable atlas records all 6,660 distinct native RGB metatile signatures.
Every atlas contact sheet was visually reviewed. Classifications distinguish
ordinary floor/grass, visible entrances or stairs, water, and solid obstacles.
Ambiguous tiles were checked in their source-map context. Trees, furnishings,
raised terrain, building surfaces, cave walls and background void remain solid.
Decorative fringes are evaluated at the native movement-cell footprint.

Normal land and indoor maps block incidental lakes and pools. Route 40 and
Route 41 explicitly permit sea traversal while selecting arrivals on dry beach
where possible. The three Undersea Lymph maps permit underwater seabed movement;
raised rock and boulders remain solid. Faraway floating-island star fields and
cave background fill are blocked, rather than being mistaken for water.

The builder maps reviewed signatures onto the complete grid, then applies
explicit documented per-map overrides. It never opens a guessed path to connect
rooms and never clears cells to make a spawn valid. Unknown signatures fail the
build. Four-neighbor connected components preserve the supplied map's separate
rooms, floors and islands. Safe arrivals require actual open floor in a useful
component; tiny fragments do not become automatic travel destinations. Arrival
previews are checked against visible floor, with targeted entrance overrides
recorded in `tools/johto_collision_data/overrides.json`.

Coordinates are 1-based logical movement cells: 16 native pixels, 32 displayed
pixels, movement step 1. Collision JSON fields are `columns`, `rows`, `blocked`,
`spawn_points`, and `audit`. `qa/johto-collision-arrivals.json` records each source,
manifest key, mask, arrivals, component sizes/bounds, provenance and review state.

To reproduce after placing the original supplied BMP archive and new PNGs:

```sh
python tools/johto_collision_extract_native.py /path/to/original.zip /tmp/johto-native
python tools/johto_collision_build.py --native-dir /tmp/johto-native --source-dir /path/to/new-pngs --review-dir /tmp/johto-review
```

This reconstruction does not implement native one-way ledge jumps, ice sliding,
warp trigger semantics, surfing state, puzzle state, or dynamic objects. Visible
stairs and door thresholds are ordinary walkable cells; runtime travel sections
provide access to reviewed disconnected rooms without inventing native warps.
Tile artwork can encode different collision behavior in the original engine,
which cannot be verified from these images alone. These limits are explicit;
all shipped collision is derived from reviewed artwork rather than guessed ROM
provenance.

The final catalog contains 283 playable maps and two malformed repeated-fragment source placeholders (`014_004_Blackthorn_City.png` and `008_004_Goldenrod_City.png`). Their artwork is retained; they have no arrivals or playable links.

All 283 primary arrivals were individually checked in their image context. Secondary arrivals were checked through their terrain signatures and targeted full-map reviews. The semantic gate, `python tools/johto_collision_validate.py --native-dir /tmp/johto-native`, writes `docs/johto/collision-validation.json`. It checks every reconstructed cell, all 902 arrivals and their connected floor areas, 31 terrain fixtures, and five city entrance-to-shop paths.
