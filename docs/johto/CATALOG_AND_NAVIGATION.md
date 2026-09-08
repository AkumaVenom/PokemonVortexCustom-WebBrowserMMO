# Johto source catalog and navigation

The submitted collection contains 286 PNG maps. The source inventory retains 285 of them. `001_002_New_Bark_Town.png` is entirely blank and is the only image omitted from the installed source assets. Repeated cabins, alternative rooms, tiny chambers, and patterned scenes remain distinct source records. No remaining image is silently discarded or merged.

Source preservation does not imply playability. Close collision review found that `014_004_Blackthorn_City.png` and `008_004_Goldenrod_City.png` each repeat a furnishing/wall fragment 99 times without any floor or entrance. These originals remain installed and cataloged, but they have no invented walkable cells or arrivals. Only an explicit, reviewed `source-placeholder-no-walkable-floor` disposition can keep such an image intentionally unavailable in a final build. Missing review data cannot become an intentional exclusion.

The final reviewed build enables **283 playable maps with 902 safe arrivals**, preserves the two unavailable source placeholders, and has no pending reviews. The active graph contains 304 reciprocal links and one connected component.

## Source and presentation contract

`source-catalog.json` is the explicit source filename → stable area key → player title → category → locality mapping. Its names were reviewed against all submitted artwork. Numeric source bank identifiers remain provenance only; player titles use recognizable towns, routes, buildings, and numbered chambers. Where a PNG contains several disconnected rooms, the title describes those rooms without pretending that the PNG is one canonical floor. Floor numbers are used for the visually sequential department store rooms; other uncertain layouts use sections or chambers.

`asset-manifest.json` records original and installed SHA-256 hashes, dimensions, preview dimensions, and every derived render tile. Native assets are byte-for-byte copies of the supplied PNGs. The overview `JohtoWorldMap.png` is also copied verbatim. No playable map is resized, padded, cropped, recolored, or recompressed. Logical tiles are 16px, displayed tiles are 32px, one movement step advances one tile, and the trainer uses the existing 32×64 presentation. Thus the collision grid is native image width/32 by height/32.

Small preview WebP images have maximum dimension 320px. For the 44 maps whose largest dimension exceeds 2048px, the renderer receives 309 exact, lossless PNG tiles of up to 1024×1024px. All tiles are pixel-compared against their source crops. Smaller maps use their single native image.

## Connected Areas

`navigation-manifest.json` records every active reciprocal navigation edge and its rationale, alongside the authored source-inventory topology. Only reviewed playable maps participate in active connections. The final compiler requires that these playable maps form one connected graph, with no links to unavailable sources. Standard town, route, forest, and cave entrances follow Johto locality geography. Buildings connect to their named town or route. Separate cave images connect as dungeon sections. Related tower, gym, store, and bridge rooms also have sequential navigation links.

These links operate through **Connected Areas** and the independently reviewed destination arrivals. They do not claim that a particular painted door, image edge, ladder, or stair points to a particular destination. The PNGs contain no ROM warp table. Pixel-triggered stair and door transitions are permitted only when the separate collision/arrival review supplies verified transitions; the catalog builder creates none.

Images containing disconnected chambers use the reviewed section arrivals. No artwork is split into separately invented maps, and no guessed spawn makes an area ready. The world manifest compiler requires an explicitly reviewed collision record and validates every spawn against the shipped mask and unsafe-arrival exclusions before setting `ready: true`. Reviewed no-floor placeholders must have a fully blocked mask, no spawns, no encounters, no transitions, and no active connections. `playability-manifest.json`, the source catalog, and `catalog-validation.json` report the final expected playable count, explicit unavailable sources, and whether review is complete. While any source is pending review, the expected playable count remains unresolved.

## Frontier & Expeditions

The collection includes maps outside standard Johto geography: Battle Frontier, Mikan Island, Navel Island, Trovita Island, Kumquat Island, Mellsweet Island, Alola Islands, Faraway Zone, Undersea Lymph, Archaic Enclosure, and Dim Deep Cave. These are identified as supplied extra locations with `is_extra: true` and presented in Frontier & Expeditions. Their names describe the supplied collection; their appearance is not asserted to be an official Johto map or an exact adaptation of another region.

The western coast connects to the extra Frontier facilities. Olivine's harbor connects to the S.S. Aqua Marine room collection, whose expedition links reach the extra island hubs. Mikan connects to Undersea Lymph, the Ruins of Alph to Archaic Enclosure, and Mt. Silver to Dim Deep Cave. These are explicitly documented menu journeys, not canonical border claims. New Bark Town provides a regional journey to Mt. Silver; the intervening eastern/Kanto approach route maps are absent from the submitted collection.

## Rebuild

Run from the repository root, providing the original source directory and overview path:

```sh
python tools/johto_build_catalog.py /path/to/johto-source
python tools/johto_build_assets.py /path/to/johto-source /path/to/JohtoWorldMap.png
python tools/johto_build_world.py
```

The final command consumes `qa/johto-collision-arrivals.json` and `docs/johto/encounter-assignments.json`. It fails if any of the 285 sources lacks either reviewed safe collision/arrivals or an explicit reviewed no-floor-placeholder disposition, or if any encounter assignment is missing. It also fails if the active playable graph is disconnected. `--allow-pending` exists only for development wiring and keeps unreviewed areas unavailable without treating them as intentional exclusions. Neither builder modifies Kanto, Hoenn, or Vortex assets or configuration.
