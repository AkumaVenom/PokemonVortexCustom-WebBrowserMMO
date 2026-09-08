# Map scale and arrivals — v27.0.1

The replacement upload contains 129 images with exactly half the width and height of the earlier pack. This release installs the 120 expansion images verbatim; eight alternatives for already installed maps and one duplicate remain accounted for in the source audit. It preserves all 110 pre-expansion regional images.

| Maps | Logical cell | Native display cell | Cells per command | Trainer display | Unobstructed cardinal step |
| --- | --- | --- | --- | --- | --- |
| Previous 110 | 16 | 32 px | 1 | 32 × 64 px | 32 px |
| Expansion 120 | 8 | 16 px | 2 | 32 × 64 px | 32 px |

The finer grid retains collision/portal coordinates from v27.0.0. The server checks each substep independently, stops at the first portal, rejects diagonal corner clipping and permits a safe first half-step when the second is blocked. Players, bots and blocked-direction hints use the same helper. Encounter rolling remains once per successful movement command. No coordinate migration is required.

All 230 arrival lists were visually reviewed against their native map art and collision masks. The first listed entrance is deterministic for new visitors. Saved progress is checked on page entry against bounds, current collision, reviewed unsuitable coordinates/regions and reachability to legitimate entrances or installed portal landings. Invalid positions recover to a clear reviewed entrance. Reachable valid positions and legitimate separate map sections are retained. Arrival recovery does not turn water metadata into movement collision. AI arrivals prioritize available reviewed entrance cells and use reachable nearby cells only if those entrances are occupied.

The Area Travel form accepts only a server-resolved entrance index and requires CSRF validation. It offers Return to entrance on single-entry maps and a section selector on maps with several entrances. No client-provided coordinates are accepted. Moving between entries clears any pending wild encounter.

One older collision file changes: Hoenn Route 122. Its 40 × 40 original Emerald layout aligns to the retained 1280 × 1280 artwork at zero offset. Canonical collision and mountain-top behavior replace a mask that incorrectly blocked the sea while opening the mountain. The dry spawn [24,32] connects to 587 cells, including 568 ocean cells. The perimeter, mountain and rocks remain blocked. See hoenn-route122-collision-proof-v27.0.1.json.

Map image, tile and thumbnail URLs include asset version 27.0.1. Rendering tiles are rebuilt from the current native pixels. Old build reports and source manifests remain historical evidence; their dimensions and original spawn suggestions describe v27.0.0. For current rebuilds use tools/rebuild_region_scale_v27_0_1.py and this release's source/spawn audits. Do not rerun the earlier map importers over the corrected assets.

Encounter profiles, six Vortex varieties, the wild cap of 24, AI catch/EXP/evolution code, schema 28 and the existing database configuration are retained. New profiles with original levels above 24 still roll 21–24.
