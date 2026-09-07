# Responsive Mobile & iPad UI — v25.3.0

## Scope and diagnosis

The authoritative input is the supplied v25.2.8 archive. The shared coherent theme had unrestricted `:hover` mutations across actions/cards, a dark/absolute mobile menu inherited from the earlier theme, very small controls/text, and oversized panels hidden behind percentage-based reveal thresholds. Small screens also exposed a native Vortex map viewport without a local-trainer camera. These are source-confirmed defects and touch compatibility hazards. The exact reported device failure has not been reproduced on physical iOS hardware in this environment.

Safari's documented compatibility-event behavior can defer or suppress later mouse/click events when hover-related content changes. This informed the input guard; it is not proof that every reported tap failure has the same cause. Reference: [Apple Safari Web Content Guide — Handling Events](https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/HandlingEvents/HandlingEvents.html). Input capabilities are chosen using [W3C Media Queries](https://www.w3.org/TR/mediaqueries-4/#mf-interaction), not device-name/user-agent detection.

## Interaction contract

`pv-can-hover` is initialized from `(hover: hover) and (pointer: fine)`. Passive capturing touch/pointer handlers remove it before emulated mouse events, and genuine mouse input can restore it. Each hover selector is constrained through `:where(html.pv-can-hover *)`, which adds no specificity. Mixed active/focus/checked selectors retain their original behavior and declaration order. Card-depth handlers also require an actual mouse pointer and the hover guard.

Normal `click`, anchor navigation, native validation and submit events remain the only action pathways. The repair introduces no synthetic touch clicks, global `preventDefault`, client-side transaction logic, user-agent sniffing, new dependency, or remotely loaded asset.

`PVUI.initNavigation()` binds each header once and is reused after the legacy battle shell is constructed. Menus use their real `aria-controls` targets. Every sidebar keeps its original link nodes inside an expandable Game sections region. Without JavaScript, navigation is visible. The compact breakpoint is 1100px; the 1101–1400px map layout separately stacks the old wide columns to accommodate the left rail.

## Layout and maps

Phone layouts reduce framing and place battle commands before the log. The design remains the existing bright Pokémon theme. Touch control heights are at least 44px (52px for movement), inputs use 16px text on coarse-input devices, and pinch zoom remains enabled. Menus can scroll within short viewports without a fixed overlay swallowing the play surface.

Only the visible map viewport adapts. Vortex retains 480×400 artwork; regional source dimensions and render tiles retain their existing contracts. The added Vortex camera scrolls only its own viewport and uses server-confirmed local coordinates. It does not run on routine presence refreshes. Region maps retain the accepted `world-map.js` camera unchanged.

Reveal targets remain visible by default and observers use threshold zero. A long list or failed/missing observer cannot hide its controls indefinitely.

## Files and persistence

Changed runtime files: `assets/css/vortex-modern.css`, `assets/js/vortex-modern.js`, `assets/js/vortex-map.js`, `includes/bootstrap.php`, and `config/app.php` (cache key only). `VERSION`, README, changelog and release/test documents record the update. No database or artwork changes are needed.

The output filter adds viewport metadata only to full documents with no existing standard viewport tag. It retains the existing CSS/script injection, base-path rewriting, CSRF injection and AJAX-fragment behavior.

## Validation boundary

Portable tests execute the navigation/input logic in a minimal DOM/event model and the real camera function against geometry fixtures. They also parse JavaScript and check CSS lexical structure. These tests are not a rendering engine and cannot certify hit testing, iOS emulated-event sequencing, Safari compositing or virtual-keyboard behavior.

The included browser harness uses representative frontend markup and the actual release CSS/JavaScript. Its map endpoints are mocked and it performs no gameplay transactions. It was not run here because preview navigation was denied by browser permissions. PHP/MySQL and physical-device acceptance are also pending. Follow `TESTING_v25.3.0.md` before accepting this release.
