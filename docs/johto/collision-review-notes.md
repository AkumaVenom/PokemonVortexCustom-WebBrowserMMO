# Atlas A collision review

Reviewed all atlas IDs 0–2199 on contact sheets 00–04 and the first 200 entries of sheet05. Every ID in that interval has exactly one classification in johto-atlas-a-labels.json. Classifications are based on the native 16px grid, not resized display pixels.

Six complete atlas sheets were visually inspected; additional original-map contact sheets inspect selected uncertain examples (johto-atlas-a-uncertain-1.png through -4.png). Full original maps were inspected for Battle Frontier chambers, S.S. Aqua Marine, Archaic Enclosure, Alola Islands, Whirl Islands, Rocket Secret Base, and the Battle Frontier rooftop. Mt. Silver and other maps were checked in original-map crops.

Notable resolutions:

- Tiles 348 and 350 are continuous rocky cliff tops, not paths; blocked.
- Tile 334 is a building doorway; portal. Tile 387 is sidewall trim beside a snowy cliff; blocked.
- Tiles 639–643 are cyan rock-top filler outside the yellow cave floor, and are blocked after the full-map QA pass. The more saturated blue wave surfaces and waterfalls are water.
- Tiles 668 and 675 are waterfall/foam surfaces, not bridges. Tile 686 is a shoreline diagonal; classified water conservatively for marine navigation.
- Tile 758 is a stony garden path; floor. Tiny fringe graphics on otherwise clear native-grid floor tiles can remain floor where the center and intended route are clear.
- Rocket Secret Base dark grates are walkable floor. The large gray rectangular tables and cream-bordered blue oval table are blocked after full-map confirmation with the parent reviewer; surrounding grates remain floor. The white horizontally lined rectangles with consoles/chairs are tables, not stairs, and stay blocked.
- Brown cave plateaus have solid rocky edges; outer plateau filler is blocked. The lower mottled cave paths and yellow sand are floor, ladders and openings are portals. Three isolated Dark Cave platforms with visible ladders/stairs have explicit, reviewed per-map floor overrides in johto-dark-cave-platform-overrides.json; these preserve genuine upper routes without opening all cliff filler globally.
- Undersea Lymph open turquoise seabed/water surfaces are water; rock walls, coral, boulders, and large weeds remain blocked. The undersea opening 1735 is a portal. Dark circular underwater patches are conservatively blocked unless part of a clear open-water surface.
- The solid blue region 2090 outside the Battle Frontier rooftop is sky/background, so blocked. The gray roof deck 2092–2094 and grates 2112–2120 are floor. Blue facade panels 2121–2130 are blocked.
- The Ice Path entrance lower tile 1999 is a portal; its upper rock/arch tile 1993 stays blocked. Open snow and ice remain floor; boulders and visible rock faces stay blocked.

Limits: This is a visual collision classification, not an extraction of original game behavior flags. Decorative railings, furniture, partial structures, void, ambiguous rock surfaces, and other ambiguous object tiles generally remain blocked. Portal labels identify visually plausible doors, stairs, cave openings, and ladders; they do not themselves imply warp destination semantics. Water should remain blocked for ordinary walking and be used only when the map's movement mode permits marine travel. A final arrival-position review should confirm that a destination uses an open, connected floor area rather than a furniture fringe or portal arch.

Arrival QA follow-up: Full room maps confirm tiles 1833 and 1839 are horizontal wooden floors, and tiles 1983 and 1986 are cyan four-square floors. These are now open; neighboring plants/chairs 1982 and 1985 and the half-black fringe tiles 1990 and 1991 are blocked. Gatehouse corridor and blue-rug corrections outside this atlas range are supplied separately in johto-arrival-floor-corrections.json. The tiny 001_107_Whirl_Islands cave has an authentic 11-cell connected walkable floor component and should use an explicit small-component arrival rather than changing its walls or boulders.

Additional primary/secondary QA corrections are in johto-priority-corrections.json. This includes the confirmed Goldenrod crosshatched pavement and curb tiles, Violet gym brick/glass floor tiles, and Radio Tower wall tile 5845. Within Atlas A it blocks cliff/fence tiles 357, 358, and 360; snowtree crowns 380, 381, 392–395, 408, and 409; cliff corner 390; cyan rock-top filler 1292, 1300, and 1326; and related tan outer-plateau variants. Plain snow 374 and 391 stay floor; blue tufts 384 are icy encounter grass. Underwater seaweed 1706 and dark seabed patch 1707 are water, while rounded rock crowns 1727/1728 and the cave's upper arch 1732 are blocked. Lower cave opening 1735 remains a portal. Cyan diagonal puzzle ice 1028 is dry floor in all four Ice Path sections; wave-water and waterfall IDs remain water.

The final used-arrival tile sweep confirmed 121 is battle-room floor; 1511/1513/1529 are temple floor slabs; 1952 is mint gym floor; and 645/662/665 are white cave terraces linked by visible stairs. Tile 299 has clear grass beside a fence post, and 561 is clear grass below a cliff; these are intentionally walkable based on native-grid center and source context.


# Atlas B review notes

Reviewed every contact-sheet tile in IDs 2200–4399 (sheet05 suffix and sheets06–10), and supplied one label per ID. Obstacles are the conservative default, with explicit dry-floor, water and portal exceptions. Visual classification is approximate at 16-pixel tile resolution; it is not an extraction of original game collision flags.

Source-map checks covered Battle Frontier interior002_010, Route30 house002_012, Ruins exterior002_013, Slowpoke Well002_017, Goldenrod interiors002_019 and002_020, ship002_025, enclosure002_028, Ruins maze002_039, Lake of Rage metal base002_053, Ruins house002_064, Sprout Tower002_072, Burned Tower002_075, Ecruteak002_076, Lighthouse002_081, National Park002_088, Goldenrod003_015, Ecruteak003_016, Cianwood003_017, Olivine003_018, Navel003_054, Dim Deep Cave003_060, and Trovita003_063. Door close-up comparisons corrected superficially similar blue/green roofs to obstacles.

Key decisions:
- Metal ventilation-looking patterns and white metal lattice in the Lake of Rage base are floor. Broad blue carpet bands in the Lighthouse are floor.
- Aqua ship ornate horizontal bands are walls; large blank-white interior blocks are ceilings/solid structures; dark torn floor holes are obstacles. White small exit mats remain floor.
- Raised green Ruins maze tops and Unown fronts are obstacles. Pale speckled corridors and their shadows are floor.
- Plain tan National Park tiles are a building roof. Green circular Trovita tiles4373–4397 are the roof of a tall building, all blocked.
- Snowy house roofs are blocked. Snow paths and visibly frozen blue ice surfaces are floor. Snow-covered trees are blocked.
- Rocks, cliff faces, city building walls, decorative railings and fences remain blocked. Obvious cave ladders and door centers are portals. White or blue building siding is not a portal.
- Beach/rock transitions labeled water only when the tile center is substantially water; mixed ledge tiles generally remain obstacles. Partial building/vegetation corners favor blocking.

Limits: no original collision masks were available. Several narrow roof/ground corners and plateau lip tiles are conservatively blocked, so a few real passages may need route-level adjustment. Global identical-art reuse may encode different intended behavior in different maps. Portals identify visible traversable features only, not actual warp destinations. This atlas segment does not contain the Undersea Lymph seabed tiles (those were checked in source context and are elsewhere in the atlas).


Final cross-map QA corrected common interior floors, Goldenrod pavement, National Park grass, cliff faces, cyan cave filler, snowy tree crowns, window/counter/roof families and underwater seaweed. Three Dark Cave upper platforms and Dim Deep Cave repeated-art contexts use explicit coordinate overrides. All283primary arrivals were reviewed; all255distinct arrival-used tile types were additionally swept in native context, with regenerated unsafe wall/roof points removed. Two99cell repeated-fragment placeholders remain nonplayable. No native collision metadata was available.
