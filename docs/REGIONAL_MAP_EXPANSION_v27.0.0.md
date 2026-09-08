# Regional map expansion — v27.0.0

## Scope and preservation

The supplied v26.1.1 full project is the implementation baseline. The 129-file GBA map pack contributes 120 new areas: 49 Kanto/Sevii and 71 Hoenn. Eight source images represent existing areas and one Kanto image is a byte-identical duplicate. They are recorded in the source manifests rather than replacing the user's accepted maps.

The finished catalogs contain 94 Kanto/Sevii areas and 136 Hoenn areas. The original Vortex network retains its 25 maps. Existing map image bytes, collision JSON, coordinate scales, saved keys and encounter profiles are preserved. Existing parent maps gain connections where new areas complete their routes.

## Native view and navigation

New map WebPs retain their exact uploaded bytes, including cropped margins and composite islands. Their 4× GBA art is displayed at 1:1 image pixels inside the existing scrollable viewbox. The collision grid subdivides original metatiles into 32px movement cells (logical unit 8), retaining 64px trainer sprite units. Foot positions and labels derive from these separate sizes, so precise movement cells do not shift the character into the tile below. Existing logical 16 / display 32 maps render as before.

Small previews are separate files. Maps exceeding 2048px on either axis render from complete lossless 1024px PNG crops. Their recomposed pixels equal the source image; no runtime zoom or fit-to-window scaling is added.

Area search filters the regional catalog by name, floor and key. Connected Areas provides the existing travel model. Verified source stairs and doors between installed maps use collision-validated transitions. Explore Sections lets players choose a server-defined safe arrival in maps with distinct accessible sections; raw client coordinates are never accepted. A section request requires CSRF validation and clears a pending encounter from the previous position. Source puzzle mechanisms such as strength boulders, switches, rock smashing, currents and story progression are not recreated. Static authored barriers remain barriers; connected-area and section travel make the supplied MMO locations accessible.

## Collision and arrival authority

Original FireRed/Emerald blockdata supplies authored collision. Map-specific crop offsets align that collision with each supplied image. Composites combine the appropriate original layouts, and explicit reviewed corrections record artwork differences. Source snapshots, hashes, transforms, rebuild tools and collision review sheets are included with the project.

Both player and AI regional footsteps validate one-cell movement, bounds, source/destination collision and both orthogonal cells for diagonal travel. This happens before any door/stair transition; the destination is checked again. Database collision edits remain additive. New arrivals are chosen from validated open sections; no wall is cleared to make a spawn. Decorative cliff-edge fragments are excluded from the section selector, and arrivals avoid stair/door triggers to prevent bouncing between maps. Traversable water follows the game's accepted area-based movement abstraction, while obstructions remain blocked.

Original map mechanics can contain decorative open pockets separated by impassable terrain. AI spawning is anchored to approved arrival points and searches connected floor nearby, avoiding arbitrary remote decorative cells.

## Wild encounters and AI

The 75 added profiles contain 444 weighted entries, based on original game tables. New source slots at/below 24 retain their levels; slots above 24 roll21–24. Existing profiles retain their established capped balance. Players and AI consume the same profiles and shared Normal/Shiny/Dark/Mystic/Metallic/Shadow roller. Species odds and variety odds are separate. Static legendary events, gifts and buildings without ordinary roaming habitat are not converted into random encounters. Equivalent underwater sections reuse documented original underwater habitat.

Existing AI accounts can travel through installed regional connections without reseeding or resetting collections. Travel is considered once per scheduled action, separately from collision-checked footsteps. AI encounters still use the established battle simulation, transaction-backed capture, team EXP and evolution routines. The wild cap does not cap owned Pokémon progression.

## Compatibility and performance

Schema remains 28. Area keys fit the existing 45-character map presence field. Human online activity text is bounded to that legacy field's 45-character limit. The map/area catalogs cache validated definitions per request to avoid repeated image and collision reads during AI and encounter work. Each movement request identifies its world and area; a stale browser tab cannot move the trainer on another map.

Credentials are inherited unchanged from the uploaded project. No database reset, schema change or AI reseed is required for this expansion.

## Validation limits

See `RELEASE_VALIDATION_v27.0.0.json` for executed checks. Tests cover real PHP world functions, all map metadata, collision/spawns/links, source-derived encounters and varieties, image fidelity, and AI functions using explicitly labeled in-memory database adapters. The optional isolated MySQL fixture is supplied for the target server.

Automatic approval review denied the local browser preview. No browser-rendered acceptance or live MySQL/session/multiplayer test is claimed. The XAMPP checklist in `TESTING_v27.0.0.md` covers those final environment-specific checks.
