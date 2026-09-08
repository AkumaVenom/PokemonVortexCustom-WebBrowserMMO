# Unbound runtime integration

Unbound uses the accepted regional exploration runtime under the stable world key `unbound`. Its ready map catalogue supplies the existing player and AI movement, arrival, encounter, travel and persistence services. The release adds region data and assets; the AI service does not require a reseed or collection reset.

## Player exploration and battles

- `pv_world_area()` validates each playable image, grid, collision header and arrival list before allowing entry. Runtime gates compare the complete reviewed ready-key set with the resolved catalogue, so missing or malformed files cannot silently reduce the number of tested maps.
- The regional page renders each installed image at its declared dimensions in the accepted scrollable viewport. Its existing large-image tile renderer and character movement stride remain in use.
- Player and AI movement share `pv_world_walk_direction()` and `pv_world_step_blocked()`. Bounds, every movement subdivision, blocked destination cells and diagonal corner clipping are checked on the server. Grass, glass floors and stairs are classified in the Unbound collision data as passable terrain; these are data decisions, not a bypass of collision validation.
- Entry uses a reviewed arrival point. Returning players retain valid saved progress; invalid or disconnected saved coordinates recover through the existing entrance policy. Section selection and Connected Areas expose the reviewed travel network.
- The scanner loads the area's assigned Unbound profile, rolls the species and then rolls the independent Vortex variety. The exact guide identity, sprite name, level, world, area, coordinates and single-use encounter token enter the pending session record.
- `pv_wild_start()` consumes that pending record. Stale legacy level fields cannot overwrite it. Invalid levels, wrong tokens, expired tokens and token replay are rejected. Battle results return to the original Unbound map.
- Unbound does not change the wild-only level cap of 24. High profiles use 21–24. Player-owned Pokémon retain their normal progression above level 24.

## Persistent AI trainers

`pv_bot_population_maps()` and `pv_bot_region_admission_areas()` already discover every registered ready region. The original 1,000 trainer identities retain their accepted initial placement contract of 400 Vortex, 300 Kanto and 300 Hoenn. The normal scheduled regional admission action can bring existing trainers into an underpopulated Unbound region without rewriting their identity or collection.

Admission is rolled once per scheduled action. Ordinary footsteps do not issue a regional population query. Admission moves trainers only from above their region's fair share to a less populated registered region. Vortex home identities remain in Vortex. Population query failure skips admission, and inaccessible destinations are bounded to eight safe-entry attempts.

Within Unbound, bots use the same collision masks and Connected Areas as players. Every destination is selected from the ready catalogue, and unoccupied arrivals use reviewed entrance points. Invalid saved AI coordinates recover within their existing map. New trainer identities above 1,000 can select Unbound through the existing least-populated-map assignment.

The accepted wild encounter and settlement services use the same Unbound profiles as players. Quiet interiors produce no encounters. Successful catches persist the exact variety's guide ID, display name, level, starting EXP, owner, original trainer, stats, team slot and collection counters. Training and capture keep their established transaction boundaries; a capture failure rolls back partial capture records. Owned AI Pokémon can train past level 24 and retain the normal level-100 ceiling. Victories continue to dispatch the trained active team to the existing level-evolution service; Unbound adds no separate evolution rules.

## Persistence and multiplayer scope

The inherited schemas already support this region: `world_key` is `VARCHAR(24)` and Unbound uses seven characters. Persistent regional positions and collision overrides use world/area keys; human and AI presence queries filter by both world and area. `mapusers.map` remains `VARCHAR(45)`, so all Unbound keys must fit that existing limit. Existing accounts, collections, old-world positions and schema definitions do not need to be reset.

## Executable validation

Run from the project root on the same PHP 8/mysqli environment used by the game:

```sh
php tests/unbound_player_gate.php
php tests/unbound_ai_gate.php
php tests/johto_ai_gate.php
php tests/region_ai_runtime_gate.php
php tests/region_ai_capture_gate.php
```

`unbound_player_gate.php` runs the real scanner body, retaining its declaring namespace and production `__DIR__`, then calls the actual battle-start function. Controlled rolls exercise both low/high profile endpoints and all six exact variety sprites. It checks session handoff, token misuse/replay/expiry, corrupted pending levels, legacy-field tampering, player-owned level 26, quiet rooms and the return URL.

`unbound_ai_gate.php` exercises actual region admission, every reviewed map's placement and recovery eligibility, controlled low/high wild rolls, six-variety capture transactions, stats/count write failures and owned progression from 24 to 26 and 99 to 100. Its evolution assertion covers dispatch to the accepted evolution service; it does not claim to emulate a full evolution transaction. Capture reflection retains each function's actual source directory. The database adapters exercise application persistence calls and rollback behavior, not a live MySQL server.

The Johto admission fixture populates every other registered region so its Johto-specific checks remain meaningful when Unbound is added. Its settlement check now verifies preserved totals and an equal regional distribution across the current registry, while retaining the original 400/300/300 initial identity assertions.

The release validation report records executed checks. These fixtures do not establish live HTTP behavior, browser presentation, multi-client synchronization, or a production MySQL transaction result; deployment validation should retain the existing two-account exploration and capture checks on the target server.
