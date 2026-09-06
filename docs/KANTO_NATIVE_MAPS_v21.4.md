# Kanto Native Map Contract — v21.4

## Authoritative gameplay source

The uploaded `pokemon-firered-and-leafgreen-versions-gba-maps.zip` is the authoritative source for Kanto playable map art in v21.4.

- 46 WebP files were supplied.
- Route 22 is duplicated byte-for-byte in the archive.
- 45 unique map sections are registered for gameplay.
- `html/static/images/worlds/kanto/native/` preserves the supplied filenames.
- `html/static/images/worlds/kanto/production/` contains clean-slug byte-identical copies used by the runtime.

`FullKantoMap.png` is retained only as a geographic/reference atlas. It no longer provides playable area pixels.

## Rendering contract

A Kanto map is displayed at its native image dimensions. The runtime must not apply:

- user-selectable zoom/scale controls;
- CSS `transform: scale(...)`;
- forced width/height enlargement;
- pre-upscaled 2x/3x/4x presentation assets;
- smoothing filters;
- decorative grid/vignette overlays over the map pixels.

Large maps scroll inside the exploration viewport. They are never shrunk merely to fit the panel.

## Logical grid

Movement remains server-authoritative on a 16px logical cell grid. Most supplied maps are exact multiples of 16 pixels. A few source exports contain a final partial strip smaller than one logical cell; those source pixels remain preserved while only complete 16px cells participate in movement.

## Collision

v21.4 regenerates collision against the native map dimensions. Static data guarantees map bounds/void protection; `world_map_blocks` remains an additive per-cell correction layer for real-XAMPP tuning. Source images must never be edited to solve collision.

## Networking

Presence remains namespaced by `world_key + area_key`. Display resolution is presentation-only and never changes server coordinates.

## Wild encounters

Existing Route 1–25 / Viridian Forest / Victory Road profiles are retained. v21.4 also adds native-area profiles for Mt. Moon, Diglett's Cave, Seafoam Islands and Safari Zone using species present in the recovered Pokédex catalogue.
