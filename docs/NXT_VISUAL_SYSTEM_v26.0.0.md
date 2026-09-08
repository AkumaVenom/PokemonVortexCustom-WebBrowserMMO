# Pokemon Vortex NXT — visual system v26.0.0

## Release boundary

This redesign is based on the exact supplied **v25.3.0 Responsive Mobile & iPad UI XAMPP test candidate**. It is a complete presentation release, named **Pokemon Vortex NXT**. It is still a XAMPP/browser/device test candidate until the updated application is exercised on the target installation.

Schema remains **28**. No migration, import, account reset, ranking reset, map conversion or new service is required. The existing `/PokemonVortex` installation path is independent of the visible NXT name.

## Design direction

The shared interface uses crisp white Pokédex-like panels, blue primary actions, red NXT branding, restrained yellow highlights, readable ink text, softly recessed sprite wells, consistent outlines and small layered shadows. Authentic Pokémon sprites and regional maps provide the artwork. White scan lines are restricted to ambient scenery and decorative image areas.

| Section | Accent | Companion/art direction |
| --- | --- | --- |
| Public home | League blue and NXT red | Pallet Town, Charizard and the starter lineup; three real world links |
| Adventure | Grass green | Route 101, Eevee, Treecko and regional companions |
| Battle | Pokémon red | Charizard, Blastoise and Lucario; blue/player and red/opponent HUD accents |
| Ranked competition | Gold | Dragonite, Garchomp and Ever Grande scenery |
| Collection / Pokédex | Pokédex blue | Bulbasaur, Rotom and collection-specific sprites |
| Shop / trade | Warm amber | Meowth, trade evolution partners and Slateport |
| Community | Violet | Chatot, Togekiss and Lilycove |
| Account | Blue | Littleroot, Pikachu and Eevee |

The existing Pokémon GIFs contain single frames. CSS supplies gentle sprite motion; this release does not claim new frame-by-frame character animations. Existing attack, hit, capture, switch and result choreography is retained.

## Implementation ownership

- `public_html/includes/nxt_theme.php` supplies section metadata, the common wordmark, reusable sprite lineups and full-document theme injection. It has no database or gameplay access.
- `public_html/includes/bootstrap.php` keeps its existing compatibility/output pipeline and calls the NXT decorator at the end. Full HTML documents receive section attributes and the NXT resources **after** previous head styles. JSON and HTML fragments receive no NXT document assets.
- `public_html/assets/css/vortex-nxt.css` is the canonical final presentation layer. Existing skin CSS is retained because it also owns detailed layout and battle runtime contracts. New selectors explicitly override presentation without rewriting stage dimensions or runtime data.
- `public_html/assets/js/vortex-nxt.js` adds decorative section companions, empty-state sprites, public-page artwork and a device-local motion preference. It does not intercept, submit, disable, clone or rewrite gameplay forms or issue game requests.
- `public_html/includes/ui.php` owns the shared header, footer and game navigation. The separately assembled Trainer Battle shell in `vortex-modern.js` has matching branding and the same major destinations.
- Login, signup and local setup use the same identity, surfaces and supplied Pokémon artwork. Signup fields have explicit label associations and its starter radios use a labelled fieldset.

## Coverage

Every active full HTML route receives the new final layer. Redirect aliases keep redirecting to their corresponding redesigned page; retired/denied helper trees remain retired.

| Family | Full pages |
| --- | --- |
| Public and help | Home, About, News, Contact, Terms, Privacy, Legal, Credits, 404, password recovery |
| Authentication and maintenance | Login, signup, local setup |
| Trainer | Dashboard, account, options |
| Exploration | World selection, Vortex maps, Kanto/Hoenn maps, AI trainer profile |
| Battles and progression | Battle Arena, standard Trainer Battle, Wild Battle, Live PvP lobby/runtime/results, Event Center, Sidequests |
| Competitive | Rival Hub, Trainer Rankings, AI Activity |
| Collection | Collection, team builder, Pokédex/index/detail, move lab, evolution, fossil lab |
| Economy | Items/shop, trade listings, listing creation, making offers, viewing offers |
| Community | Community hub, trainer directory/profiles, messages, clans |

`NXT_RELEASE_AUDIT_v26.0.0.json` enumerates the actual document entry points and the baseline integrity results.

## Interaction and accessibility

- The accepted **1100px** compact navigation boundary is retained. Both normal and dynamically built battle menus use the existing binding logic.
- Navigation remains available without JavaScript. Existing links are retained and current shared navigation destinations have `aria-current="page"`.
- Main reading text is 16px, regular labels are 14px, and small supporting metadata is at least 12px in the new layer. Forms use 16px input text. The inherited specialist pages retain some compact metadata.
- Primary action targets remain at least 44px; the map direction pad remains 52px. Native click, submit, scroll and pinch zoom behavior stays intact.
- `Pause motion` stores only `pv_nxt_motion_paused` on the device. It pauses decorative CSS animation without freezing battle hit/move feedback. Storage denial is tolerated. OS reduced motion takes precedence, including changes while the page is open.
- Ambient motion pauses while the document is hidden. Content is never hidden waiting for a reveal observer.
- Companion art uses empty alternative text and hidden decorative groups. Controls, labels and result text remain separate from decoration.
- Portrait phones collapse illustrated headers to prioritize the task. The home cast, starter selection, team and content sprites remain available. No control is placed behind an artwork layer.

## Assets and performance

All imagery comes from the supplied project. No generated artwork, external font requests, external asset CDN, new package dependency or image hotlink is introduced. Existing sprites and map files remain byte-identical.

Selected home imagery is approximately **232 KiB** before HTTP overhead and cache reuse. New map-based headers use one scene per section and small 96px sprite canvases. Hoenn decorative previews use the existing small thumbnails where suitable; larger route/town images are used only where panel resolution warrants them. Decorative assets are shared URLs, with explicit dimensions for the new image elements. Below-fold cards remain lazy-loaded.

No rendering rule in the new layer changes native Vortex/Kanto/Hoenn map stage sizes, trainer coordinates, world tiles or camera origins. Map/card preview crops are distinct from playable map art.

## Verification and limits

Completed here:

- 69 portable mobile/navigation/camera/source checks.
- 24 NXT script behavior checks, including native form preservation, blocked storage, system/device motion preferences, cross-tab preference updates and missing decorative assets.
- 31 ranked SQL/source checks.
- 51 AI competition policy checks.
- JavaScript syntax and CSS lexical structure checks.
- Asset existence/image decode checks and a byte comparison against the supplied ZIP for authoritative gameplay, database files and all original assets.

These are source checks and model-based execution, **not rendered browser acceptance**. PHP, MySQL/MariaDB, real browser rendering, WebKit/touch behavior and live multiplayer gameplay were not executed in this environment. See `TESTING_v26.0.0.md` for the target-runtime checklist. Do not promote the candidate to an accepted production baseline solely on these results.
