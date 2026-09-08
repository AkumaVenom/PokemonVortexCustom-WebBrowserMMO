# Unbound terrain collision and arrivals

The supplied map PNGs use the same **32-pixel displayed terrain cell** as the accepted Johto maps. Collision uses that cell grid and the existing shared player/AI movement checks. No map is stretched to fit the viewport, and no runtime zoom control is added.

## What the reconstruction represents

The upload contains rendered artwork, without ROM movement permissions, elevations, door scripts or collision metadata. These masks are a **visually reviewed reconstruction**, not a claim to reproduce the Unbound ROM's original collision data.

`tools/unbound_collision_data/atlas.json` records the explicit terrain class and source example for each reviewed tile signature. The signature is the exact RGB content of a 30×30 core inside a 32×32 displayed terrain cell. The one-pixel outer interpolation halo varies with neighboring artwork and is the only part omitted. This is exact signature lookup, not a color threshold or a probabilistic image classifier. An unknown core stops the build.

The atlas and complete map contexts were reviewed separately. The contextual pass checks roads, grass patches, interior floors, glass bridges, frozen surfaces, stairs, roofs, cliff bands, walls and furniture. This distinction matters when a similar-looking texture belongs to a roof in one scene and a floor in another. Explicit local overrides cover such cases without changing the source images.

## Walkable surfaces

- Grass, flowers and autumn grass patches remain walkable.
- Dirt, sand, paved city streets, room floors, rugs and court markings remain walkable.
- Actual glass floors, glass/metal bridge treads and ice floors remain walkable. Glass roofs, windows and building facades remain solid.
- Stairs, ladders and the reviewed approaches onto cargo platforms remain walkable. Vertical cargo faces, raised obstacles and ordinary building roofs remain solid.
- Walls, trees, large rocks, furniture, cliff faces, void and lava remain solid. Incidental water and sewage channels cannot be used as dry arrival points.

Directional ledge jumps, automatic ice sliding, HM requirements, native door/warp triggers and scripted ROM puzzles are not simulated by these visual collision masks. Connected Areas supplies the regional section travel used by this MMO.

## Arrival review

Arrivals are selected from existing walkable cells. The builder never clears collision to manufacture a spawn. Every retained arrival is explicitly recorded after reviewing its source context and approach. Exterior cliff shelves, decorative terrain strips, roof fragments, water channels and small disconnected scenery are not accepted merely because their tiles resemble floor.

Named areas that reuse identical artwork reuse the same reviewed terrain and arrival decisions. Small genuine caves and islands have explicit dry arrival exceptions with enough adjacent floor to move. Malformed exports and source patterns are recorded with a reviewed unavailable disposition, a fully blocked mask and no arrival or active transition.

`qa/unbound-collision-arrivals.json` is the compiler handoff. `docs/unbound/collision-summary.json` reports area readiness and component measurements. The source catalog and playability manifest account for unavailable exports and aliases without deleting the uploaded artwork.

## Rebuild and verify

From the project root:

```sh
python tools/unbound_collision_build.py
python tools/unbound_collision_validate.py --report docs/unbound/collision-validation.json
```

Both commands resolve original PNGs through the shipped source inventory. `--source-dir /path/to/extracted/upload` optionally compares against the separately extracted upload. `--review-dir /path/to/overlays` on the builder creates red collision overlays and arrival crops for inspection; these QA images do not replace game artwork.

The validation checks every mask, the exact source bytes, reviewed readiness, dry arrivals and connected local movement. Independent source-coordinate fixtures cover glass bridges across hazards, court exits, city streets, autumn grass, ice, cargo ladders, building roofs and the approaches between those surfaces. The accepted regions retain their own existing masks and movement behavior.
