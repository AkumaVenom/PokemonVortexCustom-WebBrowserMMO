# Kanto World Implementation — v21.4

v21.4 supersedes the atlas-derived v21.1 playable crops with the user's supplied individual FireRed/LeafGreen area maps.

## Current world model

- `world_key = vortex` keeps the accepted 25-map Vortex network.
- `world_key = kanto` contains 45 unique native Kanto sections.
- Multiplayer presence, saved positions, encounters and movement remain namespaced by world + area.

## Artwork authority

The individual WebPs from `pokemon-firered-and-leafgreen-versions-gba-maps.zip` are authoritative for playable Kanto art. Production copies are byte-identical and are rendered at native dimensions.

The older joined `FullKantoMap.png` remains preserved only for world/geographic reference.

See `KANTO_NATIVE_MAPS_v21.4.md` for the exact rendering, grid, collision and networking contract.
