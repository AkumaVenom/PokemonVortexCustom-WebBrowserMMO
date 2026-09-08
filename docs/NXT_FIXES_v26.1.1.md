# v26.1.1 presentation corrections

The user approved v26.1.0’s appearance and reported four remaining defects. This release preserves that visual baseline.

| Defect | Correction |
| --- | --- |
| Opponents face away from the player | Apply independent `scale: -1 1` only to the direct image in an enemy fighter zone. Runtime `transform` animation stays separate. |
| Home Pokémon cover feature copy | Dedicated feature-card markup with copy and art in separate grid columns; no absolute or floating sprite overlay. |
| Results are oversized and inconsistent | Reset the inherited 420px height, align content normally, use pearl-blue/League styling, readable rewards and 112px sprite canvases; correct the legacy ID-level link colour override. |
| Trainer shell lacks common icons/links | Match the common eight navigation entries, five group icons, profile ball, three world links and footer. Completed results update the banner/status. |

## Orientation boundary

The modern Wild, Trainer, Live and mission/rival renderers use `.pv-wild-sprite-zone[data-pv-fighter="enemy"] > img`. The rule applies automatically to later replacements and target previews. It excludes player sprites, UI icons, item images and projectile layers.

The legacy no-JavaScript table has opposite column ordering: the opponent is on the right. Its existing orientation is retained. DOM cell order remains unchanged because the modern adapter parses those cells.

## Gameplay preservation

Only the shell and outcome presentation portions of the modern adapter change. Active battle parsing, commands, form submission, vault behavior, cinematic/replay logic, authoritative state, rewards and settlement remain unchanged. Outcome sprite elements reuse the exact source image URL and alternative text without copying obsolete inline image styling. Existing result option URLs and reward text are retained.

`live_battle_result.php` adds one class to the settled receipt panel. Its pre-render PHP and database reconciliation are unchanged. `index.php` only changes the feature-card markup. All original image files are unchanged.

## Validation

Portable UI/source and NXT behavior gates pass, plus icon parity and baseline integrity checks. This work did not execute a rendered browser, PHP/MySQL, physical devices or live multiplayer. See `TESTING_v26.1.1.md` for focused target-runtime checks.
