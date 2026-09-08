# Pokemon Vortex NXT — Bright League visual system, v26.1.0

## Direction

The user rejected v26.0.0’s bland white panels, tiny framed Pokémon/map collages and oversized battle UI. Their positive references retain the original bright Pokémon style: pearl blue/white, warm yellow, multicolour League trims, dark readable headings and foreground sprite compositions. This revision follows those references.

The shared desktop header is one compact row, followed by a narrow blue world-navigation strip. The sidebar has pearl blue/cream surfaces and readable normal-case links. Dashboard uses a compact welcome area with five foreground companions, including the player’s lead, over soft orbit artwork. Page headers use unframed companions. Regional illustrations belong to the wide public/account entry surfaces and meaningful world navigation previews.

Core colours: ink `#17324d`, blue `#2a75bb`, deep blue `#174f91`, yellow `#ffcb05`, red `#e84545`, green `#37b86a`, sky `#eaf8ff`, cream `#fff9df`. Repeating white lines provide subtle texture on blue actions and ambient surfaces. Cards use soft borders, restrained blue shadows and consistent rounded corners.

## Battle layout

At widths above 900px the shared surface places arena and commands beside one another, with a short log underneath. At smaller widths the order is arena, commands, then log. Both combatants remain side by side at every width. Desktop arena height is 318px, stepping down to 256px and 228px. Sprite canvases are 192px, 160px and 136px, constrained by the available row.

Each fighter uses adjacent HUD, HP and flexible sprite rows. The player’s order is reversed through grid areas. Variable-height names grow the HUD and reduce available sprite space rather than overlap the HP rail. Left/right actor orientation, actor transforms, absolute effect layers, result state, original forms and action bindings are preserved. The Wild Battle PHP change consists solely of an enclosing layout div; Trainer and Live already provide the corresponding wrapper.

Inherited Trainer container caps and empty minimum heights are explicitly cleared. Fight/Items/Team/Run controls stay at the top of the command panel; move choices retain two columns. Optional motion controls sit in the footer.

The layout is implemented, but first-button viewport bounds have not been measured in a rendered browser here. See the acceptance guide.

## Coverage and implementation

All 46 full HTML routes receive the final presentation layer through the existing output filter. This covers public/help, auth/setup, trainer/account, maps/exploration, all battle modes, events/sidequests, rankings/rivals/AI, collection/team/Pokédex/labs, economy/trades and community/messages/clans. Redirect aliases retain their behavior. JSON and partial action responses are excluded.

- `includes/nxt_theme.php`: presentation metadata, common identity and end-of-head asset integration.
- `includes/ui.php`: compact shared navigation, footer and game sections.
- `assets/css/vortex-nxt.css`: canonical final appearance and compact layout overrides.
- `assets/js/vortex-nxt.js`: decorative companions, empty-state artwork and local motion preferences. No network/action interception.
- `dashboard.php` and `index.php`: the revised welcome/entry composition; pre-render game logic unchanged.
- `wildbattle.php`: one layout wrapper; game code unchanged.

The existing modern runtime continues to own navigation behavior, game adapters and animation choreography. Its NXT branding/navigation changes from v26.0.0 remain. All original artwork bytes, authoritative gameplay includes, gameplay config, database files and selected map/network endpoints remain identical to the v25.3.0 archive.

## Artwork, motion and access

All artwork is already packaged with the project. No generated art, new fonts, CDN or new runtime dependency is added. Kanto/Hoenn regional illustrations are reused as background/preview artwork. Playable map images, tile layers, stage coordinates and cameras are untouched.

The packaged GIF sprites are single-frame images. CSS provides gentle decorative motion; the existing runtime provides combat feedback. The footer preference pauses ambient sprites and decorative transitions. Hidden documents pause ambient motion; system reduced motion takes precedence. Text and actions never depend on an animation becoming visible.

Inputs use 16px text. Primary touch controls retain 44px targets; native map controls retain 52px targets. The 1100px responsive navigation boundary is unchanged. Original links/forms, keyboard focus and native scrolling/zoom remain in place. Decorative sprites are excluded from the accessibility tree.

## Validation boundary

Portable gates pass: 69 mobile/UI, 24 NXT script behavior, 31 ranked SQL/source and 51 AI policy checks, plus original-byte integrity, route and asset checks. CSS received lexical checking and source review. PHP/MySQL execution, rendered browser layout, physical touch devices and live multiplayer were not tested here. Current status is a XAMPP/browser/device test candidate.
