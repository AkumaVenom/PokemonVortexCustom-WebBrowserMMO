# Hoenn World Implementation — v22

## Asset contract

The user-supplied `HoennResized.zip` is authoritative for Hoenn gameplay artwork. v22 copies each unique PNG byte-for-byte into `html/static/images/worlds/hoenn/resized/`. No gameplay map is resized, sharpened, re-encoded or CSS-scaled.

The archive contains 66 files. Two Slateport files are byte-identical, so production deliberately registers one Slateport area and 65 unique maps total.

## Thumbnail contract

Hoenn's large gameplay pack is roughly 36 MB. Selector previews are separate local WebPs under `html/static/images/worlds/hoenn/thumbnails/`. They are generated with pixel-preserving nearest-neighbour reduction, capped at a 360px longest edge, and total roughly 1.7 MB. Full gameplay PNGs are loaded only after entering an area.

## Logical/display grid

- Server logical tile: 16px GBA coordinate unit.
- Supplied resized display tile: 32px.
- Database/world position: logical X/Y only.
- Actor presentation: scaled to the 32px display grid.
- Large map behavior: native image remains inside the normal scrollable viewport and is never shrunk to fit.

## World namespace

Hoenn uses `world_key = hoenn`. Kanto uses `world_key = kanto`. Vortex retains its original numeric map runtime. `world_map_positions`, `world_map_blocks` and namespaced `mapusers` presence already support this without a new migration.

## Collision

Each Hoenn map ships with a grid-matched conservative boundary collision file. Database `world_map_blocks` remains the additive runtime correction layer. This avoids pretending that a flattened rendered PNG exposes the original Emerald ROM collision/event layer. Real-XAMPP traversal reports should be used to harden individual obstacle cells without modifying the supplied artwork.

## Connections

Area relationships are only declared when the required supplied maps exist. The upload omits Routes 107–108 and 125+, therefore v22 does not create artificial shortcuts over those missing routes. This leaves a main 52-area connected component plus several supplied island/dungeon components which remain directly selectable.

## Encounters

36 data-driven profiles contain 95 recovered Pokédex species. All configured names are validated against `database/original_pguide.sql`; all weights total 100%. Profiles are Emerald-inspired MMO habitats, not claims of exact ROM encounter tables.

## v22.8.1 Edge/Firefox rendering compatibility

Hoenn's authoritative resized Emerald PNGs remain byte-identical and continue to define the exact fixed map dimensions. v22.8.1 changes only how browsers paint large presentation surfaces: any Hoenn source bitmap larger than 2048px on either axis is reconstructed from exact 1024px lossless crops at 1:1 CSS coordinates. This avoids the Microsoft Edge/Chromium oversized single-bitmap compositor path that could leave Route 123 and other large maps black while trainer overlays still rendered.

The derivative tiles do not affect source hashes, 16px logical movement coordinates, 32px presentation alignment, collision JSON, safe spawns, exits, encounters or multiplayer presence. Pixel recomposition validation covers every tiled Hoenn map, and the v22.3.1 Chromium camera-origin/safe-centering rules remain unchanged. Smaller Hoenn maps continue using the original single-image rendering path.
