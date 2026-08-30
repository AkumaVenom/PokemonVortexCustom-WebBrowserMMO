# PokemonVortexCustom-WebBrowserMMO-WebServer WIP

This package is a carefully modernized style reconstruction of the recovered Pokémon Vortex browser MMORPG source, prepared for a current XAMPP/PHP environment.

## Install on XAMPP

1. Copy the `public_html` folder to `C:\xampp\htdocs\PokemonVortex`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Confirm `public_html/config/app.php` matches your local MySQL credentials. The default XAMPP configuration is already supplied (`root`, blank password, database `pokemon_vortex`).
4. Browse locally to `http://localhost/PokemonVortex/_setup.php`.
5. Run the local installer/import process.
6. Open `http://localhost/PokemonVortex/`, create a trainer account and sign in.

`_setup.php` is intentionally localhost-only and does not appear in normal player navigation.

## Compatibility work

The recovered codebase depended heavily on PHP's removed `mysql_*` extension. The compatibility bootstrap maps those legacy calls onto `mysqli` so old gameplay pages can continue operating under modern PHP while newer account/frontend code uses prepared statements where implemented.

Historic static-site and game links are rewritten to local assets/routes, and old branding is normalized to Pokémon Vortex in rendered legacy output.

## Data note

The included reconstruction database combines the recovered Vortex SQL material and supplementary Pokémon/ability datasets supplied with this project. Some original production-era metadata was unavailable, so compatibility data has been reconstructed where necessary. The goal is a coherent, playable browser RPG rather than an archival bit-for-bit copy of any historical live server.

## Recommended runtime

- Apache 2.4+
- PHP 8.2+ with `mysqli`
- MariaDB 10.4+ or MySQL 8+
- Modern Chromium/Firefox/Edge browser

## Current in-game Screenshots
<img width="3798" height="2086" alt="team" src="https://github.com/user-attachments/assets/8492f085-b8fd-4c93-83a1-9ca2bd7d4b40" />
<img width="3840" height="2085" alt="battles" src="https://github.com/user-attachments/assets/faab5162-65ba-4e45-b6c0-e643774a2981" />
<img width="3807" height="2074" alt="Shop" src="https://github.com/user-attachments/assets/72233f4f-1d46-4296-919f-307972e73d2e" />
<img width="3799" height="2073" alt="restored maps to explore" src="https://github.com/user-attachments/assets/c9f4735d-b587-46af-8d29-05f2a847ff35" />
<img width="3840" height="2080" alt="evolutionlab" src="https://github.com/user-attachments/assets/35978ec2-ea94-40ce-af11-f5fada26c0e3" />
<img width="3800" height="2071" alt="capturingwildpokemon" src="https://github.com/user-attachments/assets/b48629bf-5868-440b-9a91-34f35b1c90e9" />
<img width="3803" height="2076" alt="allpokemonvarietiesrestored" src="https://github.com/user-attachments/assets/cbebbe81-6489-42db-87cf-c672bcb23c65" />

## ChangeLog

### v19 — Production Runtime Integrity Hardening

### Standard and Live Battle

- Added strict server-side allowlists for submitted move slots and battle items.
- Added active-team validation for switch commands; fainted or forged Pokémon IDs are rejected.
- Removed the standard-battle double medicine decrement path.
- Made standard battle item decrements atomic and single-use.
- Normalized the recovered `Parlyz Heal` / `Paralyze Heal` mismatch.
- Fixed poison item-turn damage using an undefined percentage/damage variable.
- Fixed Live Battle's historical `UDPATE items` typo and undefined inventory-column guard.
- Live Battle medicine now persists to the database and keeps session inventory synchronized.
- No-effect status medicine is not consumed.

### Accounts and recovery

- Made trainer signup transactional across the member row, options, starter Pokémon, stats, team assignment, default account rows and Pokédex population update.
- Signup failures now roll back instead of leaving partial accounts.
- Expanded InnoDB migration coverage to account/event support tables used by transactional workflows.
- Removed new writes to the retired FlashChat MD5 password mirror.
- Made password reset completion atomic with single-use token consumption.
- Made password-reset token replacement transactional.

### Event Center

- Enforced CSRF on all Event Center POST actions.
- Rejected multi-action forged Event Center requests.
- Made event-ticket unlocks atomic and idempotent for an already-unlocked event.
- Made DNA Splicer purchase atomic with row locking and guarded balance/item updates.
- Hardened Kyurem fusion request IDs, ownership, distinct-Pokémon validation and storage-only protection.
- Removed event-page production error display.

### Security and validation

- Tightened CSP frame permissions to same-origin only after retirement of obsolete external embeds.
- Bumped package and asset revision to `19.0.0`.
- 165/165 PHP files pass PHP 8.4 syntax validation.
- 30/30 bundled JavaScript files pass syntax validation.

## v18 — Battle, Sidequest & Production Safekeeping

- Established the verified safekeeping baseline used for v19.
- Preserved exact map encounter identity into wild battle through server-issued encounter state.
- Reconstructed wild Fight / Items / Poké Balls / Switch / Run / victory / defeat / capture flows with CSRF, one-use actions and a durable result ledger.
- Hardened standard/gym and Live Battle compatibility paths.
- Added transactional sidequest milestone rewards and duplicate-collection protection.
- Continued production cleanup of dead external dependencies, legacy routes and public restoration/debug wording.
- Retained the reconstructed trading, labs, PokéMart, collection, community, messages and clan systems.

## v16 — Wild Encounter & Battle Production Test

### Encounter integrity

- Removed reliance on historic numeric Pokémon IDs from recovered map encounter pools.
- Added server-side species-name resolution against the installed `pguide` catalogue.
- Preserved recovered visual forms such as Unown letters, Burmy cloaks, Shellos forms and similar map artwork while using canonical battle data where the database stores only one species row.
- Added one-use 48-character cryptographic encounter tokens.
- Encounter tokens record exact species, display form, sprite, level, map and coordinates.
- Encounter tokens expire after 15 minutes and cannot be reused to start a second wild battle.
- Rebuilt the legacy `your_pokemon` encounter cache from authoritative collection rows when needed, preventing PHP 8 `count()` failures in recovered encounter files.
- Added compatibility seeds for the small set of recovered map species missing from the supplied Pokédex revision, including their Normal/Shiny/Dark/Metallic/Mystic/Shadow variants.

### Wild battle runtime

- Replaced the recovered AJAX-driven `wildbattle.php` with a modern server-authoritative battle runtime.
- Removed the legacy `get('wildbattle.php', ...)` submission path responsible for the observed XAMPP **403 Forbidden** battle failure.
- Converted battle turns to same-origin POST requests with CSRF validation.
- Added POST → Redirect → GET handling so refresh never replays a completed turn.
- Added per-turn command sequence validation to reject double-clicked or stale battle forms.
- Added immutable battle IDs so stale forms cannot submit against a different encounter.
- Added server-side ownership validation for the complete active team.
- Added HP calculation, accuracy, STAB, type effectiveness, critical hits and bounded damage variance.
- Added local move metadata lookup with safe fallback behaviour for reconstructed moves.
- Added opponent AI that prefers available damaging moves.
- Added switching, automatic replacement after fainting, victory, defeat and run flows.
- Added battle telemetry/log output and locked completed encounters against duplicate reward processing.
- Added a durable `wild_battle_results` ledger keyed by cryptographic battle ID so a committed win/capture cannot be paid or created twice after a lost HTTP response.

### Capture and item integrity

- Added atomic inventory consumption for Potions and capture balls.
- Added HP recovery using Potion, Super Potion and Hyper Potion.
- Added Poké Ball, Great Ball, Ultra Ball and Master Ball capture logic based on remaining HP, ball tier and opponent level.
- Captures now create Pokémon transactionally with catalogue moves/types, IVs, nature, gender, ability, original trainer and ball metadata.
- Pokédex population and trainer progression are refreshed after successful capture.
- Failed capture attempts consume exactly one ball and continue the same battle against the same opponent.

### Rewards and progression

- Added one-time wild victory reward processing.
- Participating team members gain EXP and happiness.
- Trainer battle wins and money rewards are updated server-side.
- Progression totals are recalculated from authoritative Pokémon rows after rewards/captures.
- Losses are recorded when the entire team faints.

### Presentation and compatibility

- Added a fully reconstructed wild-battle UI matching the dark navy/cyan sci-fi map presentation.
- Added enemy/player HUDs, HP bars, scan geometry, battle telemetry, command tabs, move cards, item/capture controls and team-switch cards.
- Added global sprite fallbacks for recovered species whose catalogue row has no standalone base-form image.
- Added a trainer-29 map sprite fallback so the recovered missing owner-highlight pair cannot produce broken image requests.
- Added a polished map encounter card with local sprite, level, registration indicator and secure battle handoff.
- Bumped the modern asset cache revision to `16.0.0`.

### Validation

- 162 PHP files pass PHP 8.4 syntax validation with zero parse failures.
- Pure runtime checks pass for HP calculation, Electric→Ground immunity, Water→Fire effectiveness, Fire→Water resistance and battle damage generation.
- All 25 recovered map images are present.
- All 471 recovered base encounter species/forms have local sprite artwork.
- All 2,355 tested Shiny/Dark/Mystic/Metallic/Shadow encounter-art combinations are present.
