## v25.2.8 — Active AI Ranked Competition

- Replaced the four-match/five-minute ranked pulse with a shared one-minute scheduler targeting up to 16 committed automatic matches per minute, subject to available trainers and bounded request work.
- Fixed returning-rival starvation: half the match opportunities prioritize eligible high-RP contenders, one quarter Master/Elite identities and one quarter the rotating wider field. The rotation continues across small requests using the committed match count.
- Added a 120-second recurring contender window and shortened normal identity cooldowns to 90–150 seconds for Masters, 180–300 seconds for Elites and 600–1,200 seconds for Active Rivals. The operation floor now supports these intervals.
- Routed automatic world-tick ranked launches through the same scheduler, eliminating a separate path that could bypass the shared quota and race another automatic launch.
- Separated the per-request batch limit from the minute target: four-match map batches can now accumulate to the full 16-match cycle instead of treating four as the entire cycle's ceiling.
- Made quota-read failures reject scheduling, checked request budgets even when every candidate fails, and stopped starting new work after the sampled cycle boundary. Existing participant team ownership and defender-protection checks remain active.
- Reduced routine map service work to four matches with a 350 ms scheduling budget. A full minute skips repeated rank-state seeding. Rankings can top up remaining work with the existing 1,200 ms budget.
- Updated Rankings to a shared one-minute countdown/refresh and explained recurring AI competition in the current ladder copy.
- Preserved accepted v25.2.7 RP-first sorting, human/AI ranked statistics, durable replay receipts, player shields/retaliation, outcome math and unrelated gameplay/assets. No RP, wins or history are fabricated or reset.
- Added portable 2,000-trainer policy simulation and an isolated PHP/MySQL service integration test. Portable gates pass; native integration/browser validation remains pending on XAMPP.
- Asset cache advanced to 25.2.8; database remains schema 28 with unchanged credentials. The existing request-driven execution model is retained.

## v25.2.7 — Rating Points Ladder & Ranked Battle Stats Fix

- Replaced wins-first ordering with rating-first ordering in mixed/Players/AI standings, every displayed global rank, Dashboard/Rival Hub rank calculations and Top AI Rivals. Ranked W/L are deterministic equal-RP tiebreakers.
- Connected Rankings to explicit ranked profile challenges for both player and AI trainers. Profiles show current RP, global rank, ranked W/L and peak RP, and launch through the existing CSRF/action-token, team and shield checks.
- Labelled Practice Snapshot and Live AI as unranked and removed their competing launch controls from the Rankings-origin AI profile view.
- Moved ranked terminal settlement outside the legacy standard reward cooldown so legitimate fast wins and defeats still affect both trainers' ranked stats.
- Added durable session-nonce receipts in retained match history, checked under deterministic participant locks before any rating mutation, to make settlement retries duplicate-safe without a schema migration.
- Retained failed server-resolved outcomes in the active session and added retries on ranked pages and before a new ranked launch. Pending results cannot be overwritten by another ranked launch.
- Bound ranked launch state to the initialized trainer battle; refreshing its start URL resumes the active battle, and switching opponent modes removes stale ranked intent.
- Isolated post-commit AI activity-feed failures from committed match settlement. Result feedback shows actual RP change, current rating and committed W/L, including the rating floor.
- Preserved earned RP/W/L, the neutral entry point, Elo parameters, all-player visibility, autonomous service, shields/retaliation and unrelated gameplay/assets.
- Asset cache advanced to 25.2.7. Database schema remains 28 and XAMPP credentials are unchanged. Source/SQL checks are recorded separately from pending PHP/MySQL and gameplay validation.

## v25.2.6 — Wins-First Global Ranking & Autonomous AI Ranked Service Fix

- Corrected the global competitive-order contract: `ranked_wins` is now the primary sort key everywhere. Rival Rating breaks equal-win ties, fewer ranked losses breaks equal-win/equal-rating ties, and `user_id` is the deterministic final tiebreaker.
- Updated the signed-in trainer rank calculation, per-row `global_rank`, mixed Top 100, complete Players view, AI view and AI Activity **Top AI Rivals** ordering to use the same wins-first contract. A trainer with the most ranked wins can no longer be buried by AI accounts that merely have higher RP.
- Removed free competitive seeding. New human and autonomous trainer rank states begin at exactly `1,000 RP`; never-played legacy rank rows are idempotently normalized to `1,000 RP` without touching any trainer that already owns ranked W/L history.
- Hardened the five-minute autonomous Ranked service so bucket accounting reads committed `rival_battles` rows with `source='autonomous'` instead of presentation-only `ai_activity` telemetry.
- Made the Ranked pulse self-contained by ensuring missing trainer rank states before scheduling, and restricted attacker selection to bots whose active lead Pokémon is actually owned by that account.
- Hardened autonomous opponent selection with owned-team validation and a full-field fallback when the normal ±260 RP target window has no attackable trainer. Defender shields and authoritative settlement checks still apply.
- Connected the bounded Ranked pulse to ordinary active-world bot ticks, so normal game/map/Rival/AI traffic services the shared five-minute Ranked bucket instead of requiring Trainer Rankings itself to be the only reliable driver.
- Preserved Elo rating changes, tiers, 15-minute defender protection, 24-hour retaliation, the four-settlement five-minute bucket ceiling, all-player visibility, ranked result W/L feedback, wild level cap, map/collision authority and existing gameplay progression.
- Asset cache key advanced to `25.2.6`. Database schema remains revision 28; MySQL remains `root` with a blank password.

## v25.2.5 — All-Player Ladder Visibility & Ranked Result Clarity

- Added a dedicated **Player Trainer Standings** block to the mixed Trainer Rankings page. Every eligible human trainer is now surfaced immediately on every Rankings response, even when thousands of autonomous trainers push that player outside the global Top 100.
- Preserved authoritative ordering: player spotlight rows display their real mixed player/AI `global_rank` and do not promote, reorder or alter Elo for any trainer.
- Changed the **Players** filter from a Top-100 slice into the complete eligible human ladder. The **AI** and mixed global fields remain bounded to their existing Top-100 autonomous/global views for predictable request cost.
- Preserved the v25.2.4 signed-in `YOU` fallback beneath the mixed Top 100 as a secondary safeguard while adding the new immediate all-player visibility surface above the global field.
- Extended ranked settlement results to return the newly committed attacker/defender W/L and streak counters directly from the same transaction that updates rating and match history. Completed Rival battles now show the player's new ranked record immediately alongside the rating delta.
- Removed the misleading direct **Rebattle Opponent** path from an active Rival Network result. Ranked follow-up now returns through Rival Hub so protection, retaliation and one-time ranked session authorization are revalidated before another result can count. Ordinary non-Rival Trainer Snapshot rebattles remain unchanged and unranked.
- Preserved the shared server five-minute refresh bucket, cache-busted automatic reload, four-result bounded AI pulse, Elo math, 15-minute defender shield, 24-hour retaliation, wild level cap, map/collision authority and all accepted progression contracts.
- Asset cache key advanced to `25.2.5`. Database schema remains revision 28 and MySQL remains `root` with a blank password; no migration is required from v25.2.4.

## v25.2.4 — Authoritative Ranked Cycle Sync

- Replaced the page-local Rankings countdown with the **same fixed server five-minute bucket used by the ranked pulse**. Trainer Rankings now embeds the authoritative server time, current cycle start and next refresh boundary; reloading the page inside a cycle shows only the remaining time instead of fabricating a new `05:00`.
- The initial countdown is rendered server-side, so there is no fake `05:00` flash before JavaScript starts. Browser timing uses a monotonic clock only after it is anchored to the server deadline, with the existing watchdog, focus, visibility and BFCache catch-up paths preserved.
- The refresh watchdog now waits only for the **remaining portion of the current server cycle**, not another full five minutes from page load. Automatic refresh remains cache-busted, while its temporary query token is removed from the visible URL after the new response loads.
- Hardened ladder synchronization so the trainer summary counts, displayed global rank and personal global rank all use the same active-team eligibility. Hidden/no-team rank-state rows can no longer create invisible rank gaps.
- Added a pinned authoritative **YOU** row when the signed-in player is outside the mixed Top 100. This fixes the confusing case where the summary correctly reported (for example) global rank `#766` but the player appeared to be missing from the board entirely. The pinned row does not alter Elo, ordering or anyone else's rank.
- Preserved the fixed-bucket autonomous top-up, four-settlement ceiling, five-minute attacker floor, 15-minute defender shield, 24-hour retaliation, normal Master / Elite / Active Rival identity cadences, wild level cap, map contracts and all server-authoritative battle settlement.
- Asset cache key advanced to `25.2.4`. Database schema remains revision 28 and MySQL remains `root` with a blank password; no migration is required from v25.2.3.

## v25.2.3 — Ranked Live Loop + Countdown Reliability Fix

- Reworked the five-minute Ranked ladder pulse from a rolling `MAX(created_at)` skip rule to independent fixed five-minute buckets. Normal autonomous ranked results count toward the bucket and the pulse only tops up the missing settlements, so one late AI result can no longer suppress the next visible ladder cycle.
- Expanded the fairness candidate scan and gave the bounded Ranked page pulse a 1.2-second XAMPP-safe service budget while retaining the four-settlement ceiling, five-minute attacker floor, Elo settlement, defender shields and existing Master / Elite / Active Rival identity cooldowns.
- Added a polished visible **NEXT REFRESH `05:00` countdown** to Trainer Rankings. The clock is calculated from an absolute deadline, updates continuously, changes to `REFRESHING…` at zero and restarts after the new page response.
- Replaced the previous visibility-gated one-shot reload with a cache-busted forced navigation, a continuously checking countdown loop, an independent watchdog, and visibility/focus/BFCache catch-up paths. Hidden-tab throttling can delay execution but can no longer permanently skip the refresh cycle.
- Preserved MySQL `root` with a **blank password**, database schema revision 28, the v25.2.1 world-wide wild level-24 cap, and all accepted native-map / responsive-image containment contracts.
- Asset cache key advanced to `25.2.3`. No database migration is required from v25.2.2.

## v25.2.2 — Five-Minute Ranked Ladder Refresh

- Fixed Trainer Rankings not visibly advancing on the expected service cadence. The page now uses an explicit **300-second / 5-minute** no-cache refresh contract and preserves the active `scope=all|human|ai` URL while reloading.
- Added a server-authoritative **five-minute ranked ladder pulse**. If ordinary autonomous simulation has already produced a ranked result during the current five-minute window, the pulse does nothing; otherwise it may settle a small bounded batch of AI ranked operations so the public ladder cannot remain motionless simply because the slower per-class AI cadence has not fired yet.
- Deliberately did **not** make all 2,000 AI trainers attack every five minutes. Pulse work is capped at four successful settlements, uses a non-blocking MySQL advisory lock, enforces a five-minute per-attacker floor and retains existing Elo, target protection, 15-minute defender shields, human targeting and bounded-history contracts.
- Preserved the v25.2.0 Master/Elite/Active Rival roaming and normal ranked-cadence profiles; the five-minute pulse is a separate global service-level guarantee, not a replacement for those identity behaviors.
- Added `no-store` / `no-cache` response headers to Trainer Rankings so browser or proxy caching cannot present a stale ladder after a scheduled refresh.
- Database credentials remain at the accepted XAMPP default: **root with a blank password**. No credential rotation or setup-password bridge is included.
- Preserved the accepted site-wide responsive containment rules and the explicit native-map sizing exceptions, preventing the oversized UI/image regression without scaling Vortex/Kanto/Hoenn map artwork.
- Asset cache key advanced to `25.2.2`. Database schema remains **revision 28**; no migration is required from v25.2.1.

## v25.2.1 — Wild Encounter Level 24 Progression Cap

- Rebalanced every active wild-encounter source so no runtime wild Pokémon can exceed **level 24**, eliminating the level-25+ encounter bands that were producing excessive Wild Battle EXP and accelerating players and autonomous trainers toward level 100 too quickly.
- Updated **15 Kanto encounter profiles / 96 entries** and **19 Hoenn encounter profiles / 129 entries** whose previous maximum exceeded level 24. Existing low/mid-game entries at or below the cap are unchanged.
- Preserved encounter-level randomness instead of flattening late areas to level 24. Cross-cap ranges keep their existing minimum where possible; ranges that previously started at level 24+ are compressed into variable late-game bands ending at 24, with historically harder bands biased closer to the cap.
- Rebalanced all **16 Vortex day/night encounter pools**: ordinary tiers remain **5–20**, high tiers are now **22–24**, and legendary tiers are now **23–24**. Species, rarity windows, variants and legendary unlock behavior remain unchanged.
- Added a shared server-side Wild Encounter balance contract (`PV_WILD_LEVEL_CAP = 24`) used by Vortex, Kanto, Hoenn and autonomous AI encounter generation so future encounter-table edits cannot silently reintroduce level-25+ wild Pokémon.
- Added final generated-level guards on human and AI Vortex encounters and made Wild Battle startup fail closed on stale pre-release pending encounters above level 24 rather than silently changing the scanner-visible level.
- Preserved the accepted Wild Battle EXP formula (`max(75, wild level × 55)`) for both human progression and the AI base curve. The fix is applied at the encounter-level source so low-level progression and human/AI reward parity remain intact.
- Added dedicated wild-level-cap regression coverage auditing all **424 Kanto/Hoenn encounter entries** plus all **16 Vortex day/night pools**.
- Asset cache key advanced to `25.2.1`. Database schema remains **revision 28**; no database migration is required from v25.2.0.

## v25.2.0 — Active Rival Trainer AI

- Rebalanced all 2,000 persistent autonomous trainers for substantially higher activity while preserving the existing server-authoritative map, battle, inventory and Rival Network contracts.
- Added deterministic activity classes: **24 Master Rivals**, **72 Elite Rivals** and **1,904 Active Rivals**. Master/Elite rivals use progressively higher roaming, training, capture and ranked-pressure settings and are labelled on AI trainer profiles, the AI Activity feed and Trainer Rankings.
- Replaced the old single-cell, 35–110 second movement rhythm with multi-cell collision-authoritative roaming bursts: Active Rivals move 2–3 steps every 18–40 seconds, Elite Rivals 3–4 steps every 10–22 seconds and Master Rivals 4–6 steps every 5–12 seconds when due.
- Increased wild-training opportunity from the previous flat 16% roll to class-based 34% / 46% / 58% encounter attempts. Existing Vortex and regional encounter tables, collision, transitions and species authority remain unchanged.
- Added whole-active-team autonomous training. Every AI team member receives the same base Wild Battle EXP curve used by human Wild Battles (`max(75, wild level × 55)`), with modest Elite/Master training multipliers, level 100 clamping and normal trainer-progress recalculation.
- Added automatic AI level evolution through the existing authoritative Evolution Lab runtime. Eligible level-based evolutions can chain through multiple already-satisfied stages; item, happiness and special-move evolution methods remain untouched.
- Expanded sustainable AI catching/collection growth to class caps of 36 / 42 / 48 Pokémon with stronger early collection rates and existing active-team promotion logic preserved.
- Replaced the former universal four-hour autonomous ranked cooldown with deterministic class cadences: Active Rivals 90–150 minutes, Elite Rivals 30–60 minutes and Master Rivals 10–20 minutes. Master/Elite profiles also receive modest ranked decision bonuses while all results continue through the same Elo/shield/history settlement runtime.
- Preserved human-facing anti-dogpile behavior. Defender shields remain 15 minutes and retaliation remains 24 hours. Autonomous bot-v-bot matches no longer create retaliation rows; autonomous attacks against human trainers still create the normal retaliation opportunity.
- Added current-map AI prioritization to Vortex/Kanto/Hoenn map and presence requests so nearby trainers visibly move more often instead of global simulation work crowding them out.
- Added hard per-request AI execution budgets, a maximum of two autonomous ranked settlements per tick and the existing non-blocking global advisory lock to prevent higher activity from causing concurrent page-load spikes.
- Optimized Rival state seeding to insert only missing trainers, sampled high-frequency housekeeping, retained at most 6,000 recent AI activity events and reduced autonomous-only ranked battle retention to seven days. Human-involved Rival history remains preserved.
- Added Evolution telemetry to AI Activity and refreshed trainer/ranking labels so Master Rival, Elite Rival and Active Rival identities are visible to players.
- Asset cache key advanced to `25.2.0`. Database schema remains **revision 28**; no database migration is required from v25.1.1.

## v25.1.1 — Full Pokémon Style Completion

- Completed a second full-site visual audit after real XAMPP screenshots exposed remaining recovered navy/sci-fi surfaces in Sidequests, Special Events, battle result/runtime framing and Community cards.
- Converted the remaining Sidequest queue, upcoming-step cards, map framing, rewards and handoff surfaces to the bright Pokémon adventure-book treatment while preserving Sidequest progression and reward authority.
- Converted Special Event organization/group headers and badge state strips to bright Pokémon/League cards with organization-color accents while preserving all Event opponents, teams, badges and battle routing.
- Removed the remaining dark outer battle-runtime shell and normalized Trainer, Wild and Live battle stage chrome to bright sky/field panels without changing combat forms, action tokens, HP state, animation hooks, settlement, rewards or persistence.
- Fixed Community card artwork overlapping Trainer Directory / Messages / Trade Center / Clans text by reserving a dedicated clipped icon column, constraining sprite dimensions and isolating text in a non-overlapping content column.
- Normalized the remaining public/auth/setup, world/map framing, shop, collection, trade, messages, clans, directory, account/options, Labs and secondary helper surfaces so user-facing literal-dark backgrounds no longer survive the final cascade.
- Updated legacy inline fallback styling in Setup, Signup and the hidden battle verification fallback so those states also match the bright Pokémon UI if they become visible.
- Preserved native Vortex/Kanto/Hoenn map dimensions, map actor labels, day/night overlay behavior, all existing local image assets and the full cinematic battle FX/reduced-motion contracts. No generated artwork was added.
- Asset cache key advanced to `25.1.1`; database schema remains revision 28 and no database migration is required.

## v25.1.0 — Site-wide Pokémon Visual Coherence Pass

- Completed the bright Pokémon-world visual conversion across the remaining pages that still carried the older dark navy / sci-fi presentation.
- Reworked Home, world selection, world exploration chrome, Trainer/Wild/Live battle presentation, Sidequests, collection, team management, Pokédex, PokéMart, Trade Center, Community, Messages, trainer directory, accounts/options, AI trainer profiles, Labs, Events, Battle Arena and Live PvP lobby surfaces into one coherent Pokémon-styled UI language.
- Preserved deliberate dark framing only where it improves gameplay readability around native map pixels or cinematic effects; ordinary cards, control panels, information blocks and navigation surfaces now use bright League/Pokédex-inspired white, sky-blue, cream, yellow, red and green treatments.
- Rebuilt battle fighter stages into a brighter sky/field presentation while preserving the existing authoritative battle engine, move forms, HP state, Wild/Trainer/Live cinematic attack effects and capture choreography.
- Replaced remaining player-facing battle terminal terminology such as “Authoritative NPC Combat”, “Combat Link”, “Battle Telemetry” and “Server-Authoritative Command” with gamer-facing Trainer Challenge / Battle Ready / Battle Log / Choose Move language. Backend authority and validation remain unchanged.
- Replaced the recovered legacy Trainer Battle `PV` title emblem with the packaged Poké Ball artwork without changing battle routing or recovered layout behavior.
- Added stronger page-wide responsive containment for ordinary artwork, tables, cards, flex/grid children and specialist panels while explicitly excluding native Vortex/Kanto/Hoenn map artwork from destructive scaling.
- Added additional hover lift, card shine, League-color accents, rounded Pokédex-style controls, Pokémon sprite decoration and reduced-motion fallbacks using only the project’s existing local assets. No generated or replacement artwork was added.
- Asset cache key advanced to `25.1.0`; database schema remains revision 28 and no database migration is required for this visual-only release.

## v25.0.0 — Pokémon Rival Network & Autonomous Ranked Trainer Field

- Added an isolated server-authoritative **Rival Network** progression layer with Rival Rating, peak rating, ranked W/L, streaks and last-activity state for every human and autonomous trainer. Historic `members.points` collection ranking remains untouched.
- Added a polished Pokémon-style **Rival Hub** with recommended close-rating rivals, Elite Rivals, recently active competitors, personal battle history and one-click launch into the existing animated Trainer Snapshot battle engine.
- Added **15-minute battle protection** after being attacked plus **24-hour one-use retaliation orders**. Normal challenges respect target protection; valid retaliation may answer a protected attacker; starting any outgoing ranked battle immediately drops the attacker's own shield.
- Added a shared **Top-100 Trainer Rankings** page where players and the 2,000 persistent autonomous trainers climb the same ladder, with Poké/Great/Ultra/Master/Champion tiers, W/L, streaks, peak rating and activity.
- Added a dedicated **AI Activity** page exposing meaningful ranked, capture, wild-battle and sampled roaming events plus world-population telemetry and top AI ladder pressure.
- Extended `bot_runtime.php` so due autonomous trainers can perform unattended ranked operations against appropriate unprotected targets using persisted team strength and current ladder rating. Results use the same Elo/history/shield/retaliation settlement path as player ranked battles.
- Tuned autonomous ranked pressure for a long-running 2,000-trainer world: each AI has a bounded four-hour ranked cadence, human trainers are deliberately preferred when they are valid/open targets so incoming AI challenges are visible to players, defender shields prevent dog-piling, and old autonomous-only history is pruned on rolling retention windows.
- Preserved the legacy `bot_trainers.player_battles/player_wins/player_losses` contract exclusively for **Live Player Battles**. Rival Network W/L lives only in `trainer_rank_state`, preventing cross-mode telemetry mixing or double-counting.
- Preserved ordinary non-Rival Trainer Snapshot battles as non-ranked. `battle.php` settles Rival Rating only when the battle was explicitly armed from Rival Hub and the matching one-time session context is still valid.
- Advanced the database to **schema revision 28** with additive `trainer_rank_state`, `rival_battles`, `rival_retaliations` and `ai_activity` tables plus deterministic `INSERT IGNORE` state seeding for existing players/bots.
- Rebuilt the site-wide visual language from the old sci-fi navy skin into a brighter **Pokémon-world style** using the packaged Pokémon sprites, trainer sprites and Poké Ball assets. Added Pokémon banner teams, layered hover/highlight depth, a looping Pokédex-style scan beam, decorative sprite field and coherent League-color accents.
- Replaced shorthand visual emblems such as `PV`, `MAP`, `VS` and `DEX` with actual image assets on the updated surfaces. Login/signup/empty-state branding now uses Poké Ball artwork.
- Preserved v24.1.1 cinematic Wild, Trainer Snapshot, Live PvP and Live AI battle FX/authority. The new visual JavaScript remains presentation-only and includes reduced-motion fallbacks.
- Asset cache key advanced to `25.0.0`. Existing v24.1.1 installations must run **Upgrade / Repair once** after copying this release.
- Final release gate: 233 PHP files lint clean; 33 JavaScript files syntax clean; 34 CSS files structurally balanced; all 14 current-release gates pass; 23 required v25 visual assets verified present.

## v24.1.1 — Unified Cinematic Battle Stage Hotfix

- Fixed Trainer/Snapshot Battles falling back to the oversized recovered table layout when a Pokémon fainted and the Attack radio controls disappeared. The modernizer now identifies the authoritative two-fighter combat table itself, so attack-result, KO and Continue states remain inside the shared cinematic arena.
- Unified Trainer/Snapshot normal turns, item turns, KO/Continue states and replacement-Pokémon selection under the same Wild-Battle-style fighter HUD, HP bars, battle log, command surface and animation stage.
- Added authoritative current/max HP metadata and move metadata to the recovered Trainer/Snapshot presentation so modern fighter cards and move buttons no longer infer critical combat display values from stretched legacy HP-bar images.
- Added explicit KO/faint choreography: resolved impact/recoil is allowed to finish before the affected sprite collapses, desaturates and yields to the next server-defined battle state.
- Rebuilt Live PvP and Live AI command presentation around one coherent Fight / Items / Team / Forfeit tab contract while retaining the existing Live Battle forms, CSRF/action-token protection and durable match state.
- Corrected Live PvP/AI replay to consume the current authoritative resolved turn instead of looking one turn behind, including deterministic same-species mirror ordering.
- Kept the just-fainted Live combatant visible during switch/complete phases long enough for the final hit and faint animation to play, then exposes an explicit View Battle Result handoff after idempotent settlement.
- Moved Live move-type presentation lookup behind a read-only runtime projection helper so the browser-facing page does not perform combat resolution or inspect legacy battle-session slots.
- Preserved v24.1.0 Wild attack/capture choreography, the blank-password public DB contract, responsive containment, schema revision 27, all 2,000 autonomous trainers, map authority, capture probability, battle damage/rewards and persistence behavior.
- No generated artwork or replacement image assets were added.

## v24.1.0 — Cinematic Battle & Capture FX Overhaul

- Added a presentation-only Wild Battle last-turn FX descriptor that persists the already-resolved command facts through the existing POST → Redirect → GET safety flow without moving any combat/capture authority into JavaScript.
- Wild attacks now replay the exact resolved move type, hit/miss, damage, critical/effectiveness state and enemy counterattack after the refreshed page loads.
- Added aggressive forward Pokémon lunges, recoil/dodge reactions, arena camera-kick frames, typed procedural impact bursts, radial energy shards, move banners, floating damage and critical/effectiveness callouts.
- Added a full staged capture sequence using only existing packaged Ball artwork: throw arc, target flash/absorption, Ball drop, physical shake beats, success beacon/confirmation or breakout burst/re-entry, followed by the authoritative enemy counterattack on failed captures.
- Added resolved medicine recovery and partner-switch presentation beats.
- Upgraded recovered Standard Trainer Battles with marked combat sprites plus shared attack/item/counterattack movement and impact FX without modifying legacy battle authority or reward logic.
- Upgraded Live PvP / Live AI Battle refreshes with compact authoritative-turn combat motion while preserving the existing runtime/polling/state machine.
- Added type-driven procedural effect palettes for all major Pokémon move types without introducing generated artwork or new image dependencies.
- Extended `prefers-reduced-motion` coverage so the new FX overlays, capture choreography and large sprite translations are removed when reduced motion is requested.
- Preserved the v24.0.1 blank-password public install contract and responsive image/UI containment. Vortex/Kanto/Hoenn native map sizing remains exempt from generic media scaling.
- Database schema remains **revision 27**; the 2,000 autonomous trainers, map movement/collision/encounters, Wild capture probability, battle damage/rewards, Live PvP settlement and persistence authority remain server-side and unchanged.

## v24.0.1 — Public Blank-DB & Responsive Containment Hotfix

- Removed the private/local XAMPP database password from the distributable package and scrubbed the legacy credential string from retained documentation/audit/test text.
- Changed `public_html/config/app.php` to ship with a blank database password (`'pass' => ''`) for standard fresh XAMPP installations.
- Updated normal gameplay database bootstrap so an empty configured database password is a valid credential rather than a startup error.
- Simplified localhost-only `_setup.php` so **Upgrade / Repair** and **Fresh Rebuild** both connect using the credential configured in `config/app.php`, including a blank password.
- Removed all MySQL/MariaDB `CREATE USER`, `ALTER USER`, `DROP USER` and root-password management from the packaged fresh SQL. The game installer now manages only the `pokemon_vortex` schema/data and never rewrites database-service accounts.
- Preserved support for operators who do use a local DB password: set `db.pass` in `config/app.php` and the same runtime/setup paths use it unchanged.
- Added responsive media/UI containment hardening so oversized legacy/fallback images, panels, forms and animated card media cannot force the application shell wider than the viewport.
- Preserved Vortex 480×400 map art and Kanto/Hoenn native regional map dimensions as intentional exceptions inside their existing scroll viewports, preventing the containment fix from blurring or resizing accepted map presentation.
- Added a new cache key (`24.0.1`) so browsers cannot accidentally combine the old v24 stylesheet with the corrected containment rules.
- Schema remains **revision 27**; gameplay, battles, movement authority, multiplayer synchronization and the 2,000 autonomous trainer contract are unchanged.

## v24.0.0 — 2026 Cinematic Motion & Battle Polish

- Added a coherent shared animation language across the modern website shell, navigation, buttons, form controls, cards, panels, tables, map/world selectors, Battle Arena surfaces and status/reward UI.
- Added progressive surface reveals, ambient geometry drift, pulsing online/status signals, stronger focus/press response, card lift/highlight behavior and polished image/content hover motion.
- Reworked Vortex World map actor rendering from full `innerHTML` replacement to stable keyed DOM reconciliation. Player and AI trainer markers now interpolate smoothly between server-confirmed tiles, animate footfalls, spawn/leave cleanly and retain interactive AI profile links.
- Applied the same stable actor reconciliation to Kanto and Hoenn regional maps while preserving native-map scaling, collision, coordinates and the accepted v22.3.1 Edge/Firefox exact camera-centering contract.
- Added animated encounter refresh/lock feedback and map-art readiness/transitional presentation without changing map movement, collision, encounter or presence endpoints.
- Expanded Wild Battle presentation with fighter entrances, idle sprite motion, rotating/pulsing scan rings, arena sweeps, animated HP/team/progress meters, battle-log line reveals, command-tab transitions and polished move/item/team hover feedback.
- Applied the same combat presentation language to modernized Standard Trainer Battles and Live PvP through the existing shared combat surface.
- Added result/reward animation, roster pops and lightweight victory/capture celebration particles. Particles are decorative only; server-authoritative result, damage, reward and persistence logic is unchanged.
- Added an explicit `prefers-reduced-motion` accessibility contract that collapses decorative animation and transitions to effectively immediate state changes and suppresses celebration particles.
- Preserved schema revision **27**, the 2,000-bot population contract, collision, encounters, captures, Standard Battle, Live PvP, Sidequests, Events, trading, economy, Labs and persistence unchanged.

## v23.7.1 — Balanced 2,000 Autonomous Trainer Bots

- Raised the persistent autonomous trainer ceiling from **1,000 to 2,000** (`VortexAI0001`–`VortexAI2000`).
- Preserved all original v23.7.0 bot identities, teams, collections, coordinates, counters and deterministic 400 Vortex / 300 Kanto / 300 Hoenn initial assignment contract for indexes 1–1000.
- Added lowest-population seeding for new indexes 1001–2000: each new bot reads enabled bot counts across all **135 playable maps/areas** and is assigned to a currently sparsest map, with deterministic tie-breaking.
- Existing installations add only missing bot indexes during **Upgrade / Repair**; no existing bot is recreated, reset or forcibly relocated.
- Preserved bot movement cadence, authored collision/transitions, wild-battle/capture behavior, 30-Pokémon collection ceiling, active-team growth, Trainer Snapshot Battles, Live AI Battles, login isolation and advisory-lock simulation unchanged.
- Database schema remains **revision 27**; this is a population-capacity/distribution update, not a schema redesign.

## v23.7.0 — Persistent Autonomous Trainer Bots

- Added exactly **1,000 persistent autonomous trainer identities** (`VortexAI0001`–`VortexAI1000`) as ordinary non-login member/Pokémon owners backed by the new `bot_trainers` control-plane registry.
- Distributed autonomous trainers across Vortex World, Kanto and Hoenn with persistent world/area coordinates, authored collision, diagonal safety and existing map-transition rules. Fresh population seeding now selects distinct open map tiles so same-area bots do not begin stacked on one spawn cell.
- Added bounded server-authoritative simulation ticks with a global MySQL advisory lock, request-local collision caching and no requirement for 1,000 browser sessions, cron jobs or background PHP processes.
- Bots now battle wild Pokémon, retain persistent wild-battle/capture statistics, create genuine owned Pokémon records, fill empty active-team slots and promote meaningfully stronger catches into full teams without deleting reserve Pokémon.
- Added sustainable collection growth controls: progressively lower capture rates after team completion and a 30-Pokémon collection ceiling per autonomous trainer. Individual simulation failures are isolated and backed off so one malformed bot cannot disrupt player map presence.
- Added bot-aware multiplayer presence to Vortex/Kanto/Hoenn maps and area counts, including a distinct clickable **AI TRAINER** map treatment. Presence/map readers remain safe before Upgrade / Repair and simply omit bots until the schema-27 registry exists.
- Added a polished autonomous-trainer profile showing current location, coordinates, activity, collection statistics and the exact persisted active team.
- Added player interaction through both the established **Trainer Snapshot Battle** route and a new **Live AI Battle** route using the existing server-authoritative Live PvP state machine.
- Live AI opponents choose active Pokémon, switch strategically at low HP, score moves using authoritative move metadata, STAB, accuracy and type effectiveness, and settle results idempotently into bot/player battle records.
- Autonomous trainers are explicitly prohibited from logging in and have no autonomous code path for gyms, Battle Arena/League/facility trainers, Special Event trainers or Sidequest opponents. Their autonomous opponents are wild Pokémon only; player battles occur when a player challenges them.
- Advanced database schema revision **26 → 27** with additive/idempotent `bot_trainers` creation and population seeding. Existing player progression is preserved by **Upgrade / Repair**.

## v23.6.8 — Production Database Blank Password Elimination

- Fixed v23.6.7 database credential hardening so Fresh Rebuild no longer secures only `CURRENT_USER()` while leaving another local XAMPP `root` identity able to authenticate with a blank password.
- Changed the actual `database/pokemon_vortex_full.sql` installer to enforce `[REDACTED_LEGACY_LOCAL_DB_PASSWORD]` for `root@localhost`, `root@127.0.0.1`, and `root@::1`, while also securing `CURRENT_USER()`.
- The SQL now removes standard anonymous local MySQL/MariaDB accounts so a local client cannot fall through to an anonymous blank-password identity.
- `_setup.php` now verifies the configured password on every reachable local database alias after Fresh Rebuild and explicitly tests that a blank password is rejected; setup fails closed if any blank local login still succeeds.
- Upgrade / Repair applies the same local-root hardening and post-upgrade credential verification without deleting trainer progress.
- Trainer/player account passwords remain completely independent from the database service password.
- Schema remains revision 26; no gameplay schema migration was added.

## v23.6.7 — Production Database SQL Credential Fix

- Changed the actual `database/pokemon_vortex_full.sql` installer so every Fresh Rebuild explicitly secures the authenticated MySQL/MariaDB account to `[REDACTED_LEGACY_LOCAL_DB_PASSWORD]`.
- Kept `public_html/config/app.php` on the exact same `[REDACTED_LEGACY_LOCAL_DB_PASSWORD]` database service credential.
- Changed Fresh Rebuild so a default blank-password XAMPP account can remain on its already-authenticated localhost-only installer connection while the SQL import itself performs the password transition; this removes the fragile immediate reconnect step that could trigger the restart-MySQL/setup lockout.
- Added a fail-closed `_setup.php` package check: Fresh Rebuild refuses to start if the SQL credential statement does not exactly match the configured application database password.
- Trainer account passwords remain independent per player and are not changed by this database-service credential fix.
- Schema remains revision 26; no gameplay schema migration was added.

# v23.6.6 — Production Database Installer Credential Bridge Fix

- Fixed the v23.6.5 regression that prevented both **Fresh Rebuild** and **Upgrade / Repair** on a standard/legacy XAMPP database account that still had a blank password.
- Preserved the hardened normal game runtime: gameplay still refuses blank database credentials and authenticates only with the configured non-empty password.
- Added a localhost-only installer compatibility bridge: `_setup.php` first tries the configured password; if that fails and the legacy blank credential works, it immediately changes the authenticated MySQL/MariaDB account to the configured password, closes the legacy connection, reconnects securely, and only then continues the install/upgrade action.
- Preserved the existing PHP localhost guard and Apache `Require local` protection around `_setup.php`; no blank-password fallback exists in public gameplay runtime code.
- Restored normal Fresh Rebuild and Upgrade / Repair workflow without requiring a manual phpMyAdmin password change first.
- Preserved schema revision 26 and all v23.6.5/v23.6.4 gameplay, collision, account, battle, map, PvP and server-console behavior.

---

# v23.6.5 — Production Database Password Hardening

- Replaced the blank MySQL/MariaDB application password with the private production credential selected for this deployment.
- Added fail-closed runtime validation so normal gameplay refuses to connect if the database password is ever blanked again.
- Added the same non-empty-password guard to the localhost-only database setup/repair path.
- Preserved the public website, trainer registration/login and all player gameplay routes unchanged; this release restricts database authentication, not access to the game website.
- Preserved schema revision 26, v23.6.4 server-console isolation, corrected Vortex traversal and all unrelated gameplay systems.

---

# v23.6.4 — Production Server Console Gameplay Isolation Fix

- Fixed a console-instrumentation regression that could make successful trainer registration report a failure after the database transaction had already committed.
- Made `pv_server_log()` and `pv_server_event()` fail-silent so operator logging can never interrupt gameplay or account state.
- Hardened request-shutdown logging so console/log formatting failures cannot alter HTTP responses.
- Moved the post-registration AUTH event outside the account transaction failure path.
- Wrapped rollback defensively so a rollback problem cannot replace the player-facing registration error.
- Preserved v23.6.3 console-noise suppression, corrected Vortex traversal, schema revision 26 and all unrelated gameplay systems.

---

# v23.6.3 — Production Server Console Diagnostic Noise Suppression

- Removed PHP warning/error/deprecation output from the visible local World Server console.
- Removed Apache error-log output from the visible local World Server console.
- Suppressed application-error and HTTP 4xx/5xx diagnostic lines from the normal worldserver activity stream.
- Preserved background PHP/Apache diagnostic files for maintenance without exposing them in the operator console.
- Preserved v23.6.2 movement and presence-noise suppression unchanged.
- Preserved schema revision 26 and all gameplay systems.

---

# Changelog

## v23.6.3 — Production Server Console Movement Noise Suppression
- Removed all per-step Vortex, Kanto and Hoenn movement events from the normal local worldserver activity stream.
- `map_move.php` and `world_map_move.php` are no longer mirrored into `worldserver.log` at any HTTP status code, including successful, blocked, rate-limited and rejected movement.
- Removed the explicit `MOVE` event hooks from both movement endpoints so movement does not consume application-log space.
- The console filters historical `[MOVE]`, `action=vortex_move`, `action=region_move`, `map_move.php` and `world_map_move.php` lines left in older v23.6.x logs.
- Raw movement remains available only when the operator deliberately enables Apache's raw access stream with `--apache-access`.
- Preserved authentication, battles, captures, purchases, trades, Labs, Live PvP, Sidequests, Events, social actions, important errors and all accepted gameplay systems.
- Schema remains revision **26**; no database migration is required.

## v23.6.1 — Production Server Console Presence Noise Suppression
- Removed successful Vortex and regional map-presence heartbeat requests from normal worldserver request logging.
- Prevents `map_presence.php`, `world_map_presence.php` and `action=presence_poll` from flooding the operator console while trainers stand on maps.
- Presence requests returning HTTP 4xx/5xx remain visible so real faults are not hidden.
- The console filters old v23.6.0 presence-poll lines already present in `worldserver.log`.
- No gameplay, map, collision, battle, encounter or schema changes.

## v23.6.0 — Production Local Server Console
- Added `PokemonVortex_ServerConsole.cmd`, a dedicated local XAMPP operator console that live-streams Pokémon Vortex application traffic and gameplay activity without changing the player website.
- Added a private application activity logger at `includes/server_console.php` and CLI-only viewer at `server_console.php`, with HTTP access denied by Apache and an independent CLI-only fail-closed guard.
- Added request-level visibility for PHP routes with trainer identity, user ID, remote IP, HTTP status, duration, safe action classification and safe request-field names; request bodies and sensitive values are never recorded.
- Added high-level operator events for authentication, Wild Battles and captures/results, Standard Battle results, Live PvP, PokéMart purchases, Evolution/Move/Fossil Labs, collection/team changes, trades, Sidequests, Events, friends/messages metadata, clans and account options.
- Added defense-in-depth redaction for passwords, session IDs/PHPSESSID, cookies, Authorization credentials, CSRF/action/reset/OAuth tokens, API keys and secret-like fields.
- Added `storage/logs/worldserver.log` with process-safe appends, automatic rotation at 20 MB by default, five retained rotated logs and a Windows-friendly tailer.
- Added `--gameplay-only` and optional `--apache-access` console modes plus live PHP and Apache error-log viewing when available.
- Preserved the corrected v23.5.1 Vortex collision/traversal candidate. Schema remains revision **26**.

## v23.5.1 — Production Vortex Collision Traversal Correction
- Corrected the v23.5.0 XAMPP regression where rare Grass-region staircase/height-transition artwork was classified as solid collision.
- Reopened exactly **13 confirmed traversal cells**: Grass Region I `x26/y10–13`; Grass Region II `x23/y8–10` and `x11/y13–15`; Grass Region III `x23/y9–11`.
- Reduced the audited Vortex blocker total from 7,447 to **7,434** without weakening confirmed collision on trees, water, cliffs, cave walls, rocks, structures, machinery, lava, ice formations or Ghost-region scenery.
- Added direct step/crossing regression checks for each affected Grass staircase plus explicit staircase fixtures for the established Cave, Fire and Ice height-transition tiles.
- Added walkable-component anchoring checks: every local traversal component must contain a safe map spawn or a valid incoming transition destination.
- Preserved all 25 Vortex map artworks byte-for-byte, all 56 directed connection pairs, all eight special portals, one-way ledges, wild encounters, multiplayer presence and saved-position recovery.
- Preserved Kanto, Hoenn and all accepted v23.4.1 gameplay systems. Schema remains revision **26**; no database migration is required.
- Updated the premade project README to the current v23.5.1 baseline, preserving all existing screenshot placements and adding a straightforward XAMPP / `_setup.php` installation and upgrade guide.

## v23.5.0 — Rejected Collision Test Candidate
- This candidate was **not accepted** after real-XAMPP testing showed that the initial collision classification over-blocked legitimate Grass-region stair/height traversal.
- Superseded by v23.5.1. Do not promote v23.5.0 as a stable baseline.
## v23.4.1 — Production World Map Copy Polish
- Removed the final asset-production wording exposed to players in the Kanto and Hoenn world selector and active-region description.
- Kanto now presents **Kanto Region** and **Explore Kanto cities, routes, caves and landmarks while searching for wild Pokémon.**
- Hoenn now presents **Hoenn Region** and **Explore Hoenn towns, routes, caves and seas while searching for wild Pokémon.**
- Removed player-facing references to supplied/resized map files, PNGs, natural/fixed dimensions and the scrollable exploration viewport while preserving the underlying map assets and presentation behavior unchanged.
- Strengthened `tests/player_facing_copy_static_gate.php` to 104/104 checks with explicit dynamic Kanto/Hoenn metadata assertions and forbidden asset/presentation terminology checks.
- Preserved schema revision 26 and all v23.4.0/v23.3.1 gameplay, map, collision, encounter, battle, evolution, Pokédex and Live PvP behavior. No database migration is required.


## v23.4.0 — Production Player-Facing Copy Reconstruction
- Reconstructed normal trainer-facing copy across the modern site so gameplay pages explain player actions, requirements, costs, rewards and outcomes without exposing implementation/recovery terminology.
- Removed or replaced player-visible language including server-authoritative/server-validated, runtime, recovered/reconstructed, deterministic, transactionally/atomically, telemetry, schema/revision, serialization, escrow, source-generation/source-era, terminal-state and settlement wording where it had no gameplay value.
- Polished shared strings that feed World Map selection, Battle Arena/Facilities, Special Events, PokéMart items and Wild Battle completion states, including catalogue-fed copy that is not visible as literal page HTML.
- Reworked common shell/footer/profile copy and gamer-facing failure/empty states; detailed technical failure causes remain logged internally rather than being surfaced to players.
- Added `tests/player_facing_copy_static_gate.php` with 94 release-blocking checks spanning root gameplay pages and curated shared UI/catalog/service messages.
- Preserved maintenance/legal precision where appropriate and left HTTP-denied provenance/source trees outside the modern player-facing UI contract.
- Preserved all accepted v23.3.1 gameplay behavior, Evolution Lab moveset integrity, Standard Battle, Wild Battle, Live PvP, Pokédex, maps/collision/encounters, Battle Arena, Events, Sidequests, trading, clans, economy and accounts.
- No database migration. Schema revision remains 26.

## v23.3.1 — Production Evolution Moveset Adoption Integrity Fix
- Fixed the Evolution Lab `Adopt evolved moveset` defect where evolution blindly copied all four recovered target `pguide` move slots, allowing duplicated defaults to replace a valid existing move with the same attack twice.
- Reproduced the reported Cyndaquil → Quilava case from shipped data: Cyndaquil stores `Leer / Tackle / Smokescreen / Ember`, while Quilava stores `Leer / Smokescreen / Tackle / Smokescreen`.
- Added `pv_evolution_adopted_moveset()`: evolved unique defaults take priority in source order; duplicate/blank holes are filled from the specimen's existing unique moves. Cyndaquil → Quilava now resolves to `Leer / Smokescreen / Tackle / Ember`.
- Preserved exact replacement behavior for evolved species that already have four distinct defaults; no combat, Move Lab, evolution requirement, item-consumption, ability, ownership or transaction semantics were changed.
- Completed a column-aware audit of all 4,728 recovered `pguide` rows and found 258 entries with duplicate default move slots, so the runtime defense covers the catalogue generically instead of patching one species.
- Added `tests/evolution_moveset_adoption_static_gate.php`: PASS 11,295/11,295 across all 376 reconstructed evolution rules and all 2,256 Normal/Shiny/Dark/Metallic/Mystic/Shadow evolution transitions.
- Preserved schema revision 26 and all accepted v23.3.0 helper isolation, v23.2.0 Standard Battle, v23.1.0 Pokédex, Vortex/Wild Battle, Kanto/Hoenn and Live PvP authority contracts. No database migration or Fresh Rebuild is required.

## v23.3.0 — Production Legacy Helper Surface Isolation
- Closed the remaining root-level recovered/helper PHP files as direct browser endpoints without deleting their source or breaking server-side include compatibility.
- Added an Apache 2.4 `FilesMatch` deny boundary for `kick.php`, `pv_connect_to_db.php`, `pv_disconnect_from_db.php`, `pagination.php` and `typo.php`.
- Preserved `kick.php` as the accepted bootstrap include used by standard battle, Live PvP and Event Center routes; direct HTTP execution is now forbidden while PHP `include`/`require` remains functional.
- Preserved the old DB connect/disconnect shims only for quarantined historical source fixtures. Modern root application PHP remains free of `mysql_*` consumers outside the explicitly retained compatibility files.
- Confirmed `pagination.php` and `typo.php` have no modern PHP runtime consumers and retained them only for provenance rather than deleting recovered source.
- Added `tests/legacy_helper_surface_static_gate.php`: PASS 17/17, covering the deny policy, helper preservation, internal include references, obsolete-helper non-use, modern SQL/JS boundaries and version/schema contract.
- Added an Apache 2.4.68 executable validation harness result: all five direct helper requests return HTTP 403 and an internal include probe returns HTTP 200 / INCLUDE_OK.
- Preserved schema revision 26 and all accepted v23.2.0 Standard Battle, v23.1.0 Pokédex, Vortex/Wild Battle, Kanto/Hoenn and Live PvP authority contracts. No database migration or Fresh Rebuild is required.

## v23.2.0 — Production Standard Battle Runtime Reconstruction
- Added private `includes/battle_runtime.php` as the standard trainer/NPC battle database boundary while deliberately preserving the accepted recovered combat arithmetic, state shape and presentation.
- Removed every `mysql_*` compatibility call from `battle.php`; the page no longer executes `pv_connect_to_db.php` or the recovered `includes/usernav.php`.
- Preserved ban enforcement and online-heartbeat behavior explicitly through prepared `mysqli` statements before standard battle processing begins.
- Reconstructed player/opponent party hydration through a private allowlist of the only supported battle Pokémon tables. Team ids remain server-owned, owner-bound, six-slot bounded and restored to the authoritative slot order after prepared lookup.
- Hardened empty-team handling so malformed/fabricated opponent teams still fail closed and empty player teams cannot enter combat.
- Reconstructed battle inventory hydration through prepared SQL and moved healing/status-item consumption to an atomic allowlisted decrement that can only succeed when stock remains above zero.
- Reconstructed participant EXP/level/happiness persistence as an owner-bound transaction using `SELECT ... FOR UPDATE` before mutation. A stale battle session cannot apply rewards to a Pokémon that has changed ownership.
- Reconstructed victory/money + optional clan-win and defeat + optional clan-loss persistence as transaction-backed prepared mutations.
- Preserved the accepted CSRF + one-use action-token command boundary, move-slot/item/switch validation, unified move/type authority, Dark/Metallic/Mystic/Shiny behavior, victory 10-second anti-cheat threshold and recovered 9-second defeat threshold.
- Preserved Battle Arena, Special Event and Sidequest route authorization and their existing transaction-backed progression settlement services.
- Added `tests/standard_battle_runtime_static_gate.php`: PASS 85/85, covering legacy-SQL retirement, session guard, navbar telemetry, all five opponent route modes, owner-bound party hydration, inventory authority, reward transactions, combat-contract preservation and schema/version boundaries.
- Preserved schema revision 26 and the accepted v23.1.0 Pokédex, Vortex encounters, Kanto/Hoenn rendering and server-authoritative Live PvP systems. No database migration or Fresh Rebuild is required.

## v23.1.0 — Production Pokédex Detail Reconstruction
- Fixed the selected-entry Pokédex failure that left only the application shell visible after opening `pokedex.php?dex=...`. The accepted v23.0.0 page called `pv_evolution_rules_for()` even though that lookup helper had never been implemented; PHP therefore terminated after the shared page shell began rendering.
- Added variant-aware forward and reverse evolution lookup helpers to the existing private evolution runtime. Normal, Shiny, Dark, Metallic, Mystic and Shadow catalogue entries now resolve the same recovered base-species evolution graph without duplicating rules.
- Added `includes/pokedex.php`, a read-only server data service backed exclusively by the existing authoritative `pguide`, `abilities`, `attacks`, `pokemon`, `pokemon_stats` and reconstructed evolution-rule sources.
- Rebuilt the selected-entry screen as a complete production catalogue view: variant/base identity, elemental typing, world registration count, trainer-owned copy count, recovered ability profile, four-slot default move metadata, previous/next catalogue navigation, prior/forward evolution routes, complete six-variant family navigation and exact trainer collection summaries.
- Added specimen-aware inspection through `?pid=` for Pokémon owned by the authenticated trainer, including level, EXP, nature, ability, happiness, gender and all six IVs, with direct links to the established Move Lab, Evolution Lab and collection surfaces. Foreign or missing specimen ids fail closed and never expose another trainer's Pokémon.
- Added graceful invalid/missing catalogue handling so malformed or unknown `dex`/`pid` requests render a coherent error state instead of a partial/blank page.
- Hardened the existing catalogue search/list path for failed prepares and strict SQL grouping while retaining 48-entry pagination, search, ownership badges and catalogue ordering.
- Corrected and expanded source-art visual-subform fallbacks for 19 recovered base families whose archive artwork exists only under a concrete visual form. This includes replacing the broken Basculin `(Red)` fallback with the shipped `(Blue Stripe)` artwork. No external or fabricated Pokémon art was added; genuinely absent source art still uses the established Poké Ball fallback.
- Added `tests/pokedex_detail_static_gate.php`: PASS 33289/33289, auditing all 4728 catalogue rows, 788 six-variant families, 4728 aligned ability records, 423 move metadata records, every default move reference, evolution helper wiring, ownership/privacy boundaries, UI contracts and source-art fallback mapping.
- Preserved schema revision 26 and all accepted v23.0.0 gameplay authority. Vortex encounter authority remains 388/388, Kanto/Hoenn region rendering remains 1087/1087 and server-authoritative Live PvP remains 35/35.
- No database migration or Fresh Rebuild is required.

## v23.0.0 — Vortex Encounter Authority & Legacy Surface Hardening
- Reconstructed all 16 recovered `mappoke` / `nightmappoke` encounter sources into the private declarative `config/vortex_encounters.php` catalog and new `includes/vortex_encounters.php` server authority.
- Original Vortex map movement no longer executes public `xml/*.php` source scripts or trusts their obsolete numeric Pokémon ids. Encounter selection is generated privately, then resolved through the existing local pguide authority before a one-use Wild Battle token is issued.
- Preserved all 471 unique recovered species/forms, eight map habitat modes, day/night splits, standard/legendary/high tiers, source level ranges, ordinary variant windows, legendary signal windows, progression unlock requirement and special mappoke5 rare/window behavior.
- Reconstructed the recovered Day/Night map preference on the modern CSRF-protected Options page. It remains session-scoped, defaults to Day at login, drives the original Vortex visual cycle and selects the matching private encounter catalog; Kanto/Hoenn remain independent.
- Repaired 14 hand-maintained random-index defects found in the recovered source while preserving its declared catalogs: 1 standard truncation, 7 legendary out-of-range bounds and 6 high-level truncations. Night Ice mode 6 now selects across all 32 declared standard species instead of only source indices 0–3.
- Added fail-closed catalog validation and cryptographically secure runtime selection using `random_int()` over actual array lengths.
- Preserved the accepted secure Vortex map → Wild Battle bridge: canonical pguide id/name resolution, display-form art fallback, CSRF, 48-character random encounter token source (24 random bytes rendered as 48 hex characters), one-use consumption state, authoritative level and durable Wild Battle settlement/capture behavior.
- Hardened the retained recovered `xml/`, `tabs/` and `stats/` trees as source-only material. Root and directory-local Apache rules now deny direct HTTP execution; no modern production PHP page depends on those paths.
- Added `tests/vortex_encounter_authority_gate.php`: PASS 388/388, covering exact source/catalog parity, runtime reachability, all 471 recovered species/forms, 2466 reachable display/tier combinations, pguide/sprite compatibility, authority isolation, deny rules and version/schema boundaries.
- Updated the preserved region-map and Live PvP static gates to the v23.0.0 package version; functional checks remain unchanged and pass 1087/1087 and 35/35 respectively.
- Added a strict v22.8.1 SHA-256 release-delta gate: PASS 15/15, confirming exactly 10 approved existing-file changes, 17 additions and zero removals.
- No database migration. Schema revision remains 26. Kanto/Hoenn maps, collision, encounters, cross-browser rendering, multiplayer presence, Wild Battle authority, League/Facility, Events, Sidequests, Live PvP, trading, clans, economy and accounts remain outside this release delta.

## v22.8.1 — Cross-Browser Region Map Rendering Fix
- Fixed the Microsoft Edge/Chromium black-map failure reported on large Kanto and Hoenn exploration maps while Firefox continued rendering the same authoritative artwork correctly.
- Preserved every authoritative Kanto/Hoenn source PNG byte-for-byte. No map artwork, logical coordinates, 32px presentation grid, collision mask, safe spawn, encounter table, multiplayer presence rule or camera coordinate was rescaled or rewritten.
- Added a browser-safe lossless compositor path for region maps whose source bitmap exceeds 2048px on either axis. Those 38 large maps are reconstructed on screen from exact 1024px PNG crops instead of asking Chromium to paint one oversized bitmap surface.
- Added 199 source-derived render tiles. Pixel-for-pixel recomposition checks confirm all 38 tiled presentations are identical to their authoritative source PNGs.
- Removed whole-stage `filter:brightness()` compositing from Kanto/Hoenn movement feedback so Chromium is never forced to allocate an off-screen surface at the full dimensions of a large world map.
- Hardened map-image readiness/error handling so both the single-image and tiled paths schedule camera settlement consistently and surface genuine artwork failures instead of silently leaving a black stage.
- Preserved the accepted v22.3.1 Chromium camera-origin/safe-centering contract, including actual stage-origin geometry, positive scroll coordinates, two-frame settlement, `pageshow`, resize, font and visibility recovery.
- Added `tests/region_map_render_static_gate.js`, exact tile/source audit coverage, a dedicated Edge + Firefox XAMPP checklist and cache-busted assets at version 22.8.1.
- No database migration; schema revision remains 26. Live PvP v22.8.0 server authority and all accepted League/Event/Sidequest/Wild Battle/economy systems are unchanged.

## v22.8.0 — Server-Authoritative Live PvP Reconstruction
- Replaced the recovered dual-browser PHP-session combat loop. The screenshots proved that one browser could resolve Bayleef versus Quilava while the opponent remained indefinitely on “Waiting for the other trainer”; independent session randomness and HP could not be made reliable with additional polling patches.
- Added `includes/live_battle_runtime.php`, a schema-2 state machine stored once on the immutable `live_battle` row. Active teams, HP, status, commands, turn, phase, result, log and monotonic revision now have one database authority.
- Every selection, attack, switch, item and forfeit locks that exact match row with `FOR UPDATE`, validates participant identity/state invariants, and commits at most one revision. A turn resolves only after both commands exist inside the same locked transaction.
- Random accuracy and Mystic checks now run once on the server during the canonical turn commit. Browsers never calculate damage and cannot disagree about the result.
- Rebuilt `live_battle.php` as a native modern server-rendered battle UI. POST commands require CSRF plus one-use action tokens and use Post/Redirect/Get; JavaScript performs read-only polling only.
- Rebuilt `live_battle_state.php` as a non-mutating match/revision endpoint. Choosing clients are not interrupted merely because the opponent submitted first; waiting clients advance when the committed phase, turn or revision changes.
- Added forced-switch, atomic item consumption, deterministic initiative ties, forfeit and terminal-result handling to the canonical runtime.
- Terminal canonical state projects into the existing transaction-backed, idempotent settlement service. `live_battle_result.php` can now recover and settle either side directly from a completed schema-2 match, even after navigation or reconnect.
- Preserved historical match rows when starting rematches. The arena no longer deletes the previous head-to-head row, rejects overlapping accepted rematches, and binds every accepted challenge to a fresh immutable match id.
- Removed obsolete `includes/live_battle_sync.php`; browsers are no longer competing resolvers with session hydration used as a synchronization substitute.
- Added `tests/live_pvp_static_gate.js` covering authority boundaries, immutable identities, read-only polling, anti-replay controls, terminal recovery, JavaScript parsing and CSS/PHP structural balance.
- No schema migration; the existing InnoDB `live_battle` row and unused `_2` TEXT field safely host schema 2. Schema revision remains 26.

## v22.7.4 — Live PvP Entry Synchronization Regression Fix
- Fixed the v22.7.3 Live PvP entry regression where the trainer who selected an active Pokémon first remained on “Waiting for the other trainer” after the second trainer entered the command screen.
- Fixed stale cross-mode session leakage visible in the reported screenshot where a newly-entered Live PvP page could render an earlier NPC opponent such as Brock. A new match now clears old combat rendering keys before the recovered engine can process them, then rebuilds both teams from the immutable match participants.
- The read-only `live_battle_state.php` poll now recognizes the server-owned initial/forced-selection handshake only when both `pokemon_choice_1/2` ids are present, both primary participant positions are ready, and neither secondary attack-command position has been submitted.
- The secondary-position guard prevents the new entry transition from becoming true during later attack turns, preserving v22.7.3 command-clock gating and preventing repeated refresh loops.
- The waiting presentation now accurately covers active-Pokémon selection, command submission and terminal-result transitions.
- Preserved immutable match/participant authorization, canonical HP/status snapshots, idempotent turn commits, terminal settlement, modern battle presentation, unified combat, all League/Event/Sidequest progression, maps, collisions, multiplayer presence and economy systems.
- No schema migration; schema remains revision 26.

## v22.7.3 — Live PvP Mid-Battle State Synchronization
- Fixed the release-blocking Live PvP mid-battle divergence where one browser could display updated HP/turn results while the other remained on an older PHP-session combat snapshot.
- Added `includes/live_battle_sync.php`, a durable shared combat-state authority using the recovered, previously-unused `live_battle._2` TEXT field; no database migration is required.
- Canonical Live PvP snapshots now persist both participants’ six battle slots, HP, max HP, HP percentage, status and participation flags with a monotonic synchronization revision.
- Each Live PvP request hydrates its local `s*`/`ops*` rendering cache from the same canonical match revision before turn processing or result settlement.
- Added revision + action-key idempotency so concurrent browser requests cannot apply the same resolved turn twice; a stale resolver discards its local result and adopts the peer-committed snapshot.
- Both submitted active Pokémon are marked as participants in the canonical turn snapshot, preserving winner EXP eligibility regardless of which browser wins the resolution race.
- Added `live_battle_state.php`, a read-only no-cache state endpoint used for lightweight 1.2-second synchronization polling. Active pages refresh only when a newer shared revision exists, a waiting phase has genuinely advanced beyond the last committed per-player command clocks, or a terminal result is available.
- Canonical snapshots retain each participant's committed command clocks, preventing stale recovered `user_position_*_2=5` flags from causing previous-turn refresh loops. Polling does not replay POST actions and does not interrupt a trainer merely because the opponent selected a move first.
- Forced-switch active Pokémon IDs continue using the existing server-owned `pokemon_choice_1/2` columns and are remapped into the local session after canonical hydration.
- Added shared last-turn telemetry so both clients can display the same committed turn summary after synchronization.
- Preserved v22.7.2 modern battle presentation, v22.7.1 Wild Battle dependency correction, unified combat/type authority, terminal settlement idempotency, all League/Event/Sidequest progression, maps, collisions, multiplayer presence and economy systems.
- No schema migration; schema remains revision 26.

## v22.7.2 — Battle Presentation & Live PvP End-State Stabilization
- Fixed the Live PvP terminal-state asymmetry where one trainer could receive a durable win/loss while the opposite browser remained stuck on the recovered waiting screen.
- Reconciles each Live Battle request against `settled_1/2` and `outcome_1/2`; peer-settled matches complete the current side through the existing idempotent transaction-backed settlement service.
- Added the same reconciliation to `live_battle_result.php` for stale history/direct result navigation.
- Added safe automatic GET polling to the Live PvP waiting state so both browsers converge without repeated manual refreshes.
- Loaded the modern combat runtime on `live_battle.php` and completed progressive Wild-Battle-style presentation for active Trainer Battle and Live PvP combat.
- Added modern fighter HUDs, HP bars, VS arena, telemetry, Fight/Items command surfaces, forced team selection, NPC result presentation and Live synchronization state.
- Preserved the recovered server forms as the no-JavaScript fallback; modern controls submit the existing CSRF/action tokens and field values rather than duplicating battle authority in JavaScript.
- No database migration; schema remains revision 26.

## v22.7.1 — Wild Battle Combat Dependency Fix
- Fixed the release-blocking Wild Battle regression introduced in v22.7.0 where `includes/wild_battle.php` called the unified `pv_combat_*` helpers without loading `includes/combat.php`.
- Restored Wild Battle move-button rendering and attack execution by explicitly loading the shared combat authority before Wild Battle helper functions are evaluated.
- Added a combat dependency gate that verifies every runtime using `pv_combat_*` has the shared combat module available at runtime.
- Preserved the v22.7.0 unified move/type contract, corrected 18-type chart, NPC battles, Live PvP, Wild Battle state/capture/ledger logic, Sidequests, League/Event progression, maps, collisions, multiplayer and all database content.
- No database migration; schema remains revision 26.

## v22.7.0 — Unified Combat Runtime Reconstruction
- Added `includes/combat.php` as the single shared move-normalization and 18-type-effectiveness contract for NPC, Wild and Live PvP battles.
- Replaced the large duplicated recovered type switches in `battle.php` and `live_battle.php` with the shared combat authority.
- Corrected recovered chart inconsistencies, including Fairy attacks against Steel resolving as 0.5× resistance instead of 0× immunity.
- Routed Live PvP move metadata through prepared shared lookups instead of raw duplicate `SELECT * FROM attacks` calls.
- Routed Wild Battle move/type helpers through the same shared combat contract while preserving its accepted damage/state/capture/ledger runtime.
- Added a progressive modern shell for NPC and Live PvP combat pages using the existing modern navigation/sidebar design; the recovered shell remains the no-JavaScript fallback.
- Added dedicated responsive combat presentation for team tables, controls, notices and Battle Network status.
- Preserved Vortex-specific Dark/Metallic/Mystic/Shiny behavior, action-token anti-replay, League/Event/Sidequest progression, PvP settlement and all v22.6 map/economy/community systems.
- No database migration; schema remains revision 26.

## v22.6.0 — Full Source-Era Sidequest Reconstruction
- Reconstructed the complete Sidequest progression exposed by this recovered generation: **690 battle positions + six reward milestones**, ending at source position **697** after Hoenn.
- Restored all campaign ranges: Kanto 1–101/reward 102, Johto 103–205/reward 206, Sevii 207–306/reward 307, Navel Rock/Birth Island 308–311, TCG 312–361/reward 362, Orange 363–482/reward 483, and Hoenn 484–695/reward 696.
- Added `includes/sidequest_catalog.php` as the authoritative Sidequest contract with **690 trainers / 3,091 deterministic NPC Pokémon** and reserved deterministic NPC IDs.
- Preserved the fully recovered 10 May 2016 Kanto roster exactly (**101 trainers / 447 Pokémon**). Later ordinary missing rows are deterministic source-contract reconstructions; recovered region boundaries, rewards and historical landmark battles are kept explicit.
- Corrected historical landmark placement during release audit: Johto Celebi is #204 and the Sevii Moltres/Shiny Moltres/Dark Moltres encounter is #211.
- Preserved Kanto Lv.120 Zapdos/Articuno/Moltres, Johto Lv.120 Raikou/Entei/Suicune, Navel Rock Ho-oh/Lugia, Birth Island Deoxys forms, TCG Copycat Ditto, Orange Crystal Onix/legendary endgame encounters and Hoenn's final Lv.150 six-variant Rayquaza team.
- Rebuilt the Sidequest page as a polished expedition hub with X/690 completion, schema/catalog telemetry, regional roadmap, current assignment, complete team preview, region map artwork, upcoming serialized steps and source-generation completion state.
- Hardened `battle.php?sidequest=` so requested battle ID must equal the trainer's server-persisted `members.sidequest`, exist in the catalog, pass schema readiness and match the seeded database trainer identity.
- Kept reward positions **102/206/307/362/483/696** outside the trainer catalog so they cannot be entered as fabricated battles.
- Replaced legacy Sidequest win progression with transaction-backed exactly-once sequential advancement.
- Rebuilt all six regional reward milestones with transaction locking, CSRF protection, the recovered item pools and source-era **₽20,000–30,000** money award; duplicate/replayed claims cannot settle twice.
- New accounts initialize at Sidequest #1; existing NULL/0 never-started accounts are repaired to #1 without rewinding any progressed account.
- Advanced schema revision **25 → 26** with additive/idempotent Sidequest table repair, `place` metadata, InnoDB conversion and exact full-catalog synchronization.
- Preserved v22.5.0 Battle Arena/Special Events, all Kanto/Hoenn maps and collisions, cross-browser camera behavior, multiplayer presence, regional variant encounters, Wild Battle/capture, trading and account persistence.

## v22.5.0 — Battle Arena Special Event Reconstruction
- Reconstructed all 32 historical Special Event battle routes from the source-era 10 February 2016 Pokémon Vortex contract.
- Restored Team Rocket, Aqua, Magma, Galactic, Plasma, Flare and Vortex Staff groups with exact recovered route identities, local trainer/banner/badge artwork, source-era rosters and levels.
- Added authoritative `eventpokemon` storage with 181 deterministic NPC Pokémon and reserved IDs.
- Preserved the original non-contiguous event progression identities (g1–g24, g26–g27, g32–g37) and left reserved legacy flags untouched, with seven organization badge completion states.
- Hardened event battle entry by validating route → catalog ID → database trainer identity before team loading.
- Replaced legacy dynamic event settlement with transaction-backed `pv_event_battle_award()`.
- Added a polished Special Events hub with completion telemetry, group filtering, badge status, team previews and Battle/Rebattle actions.
- Added event-specific return routing after wins while preserving League/facility, trainer, sidequest, clan and Live PvP behavior.
- Advanced schema revision 24 → 25 with additive/idempotent Upgrade / Repair support.
- Preserved promo-code `event_completions`, all v22.4.0 league/facility progression, Kanto/Hoenn maps/collision/camera/multiplayer, regional variant encounters and Wild Battle/capture behavior.

## v22.4.0 — Battle Arena League & Facility Reconstruction
- Reconstructed all 95 unique League / Elite Four / Champion / Frontier / Maison progression opponents behind the recovered g1–g95 badge contract.
- Added deterministic server-owned NPC teams: 402 `gympokemon` rows across the 95 authoritative opponents.
- Replaced the legacy Battle Select screen with the modern Battle Arena hub, persistent progress telemetry, team previews, safe trainer lookup and Live PvP handoff.
- Added schema revision 24 with missing badge columns g87–g95, `gympokemon`, InnoDB progression storage and idempotent catalog seeding.
- Replaced the historical giant completion expression (which omitted g45/Marlon) with centralized g1–g95 completion logic.
- Hardened NPC battle route authorization so gymleader URLs must resolve to the authoritative catalog and expected database ID.
- Added safe legacy move metadata fallback when recovered `attacks` metadata is missing or incomplete, matching the accepted Wild Battle fallback contract instead of allowing null/zero-damage turns.
- Special event NPC battles remain intentionally unexposed until their missing authoritative event/eventpokemon source data is reconstructed in a dedicated pass.
- Preserved v22.3.1 Kanto/Hoenn maps, collision, camera stabilization, multiplayer presence, encounter variants and Wild Battle authority.



## v22.3.1 — Edge/Chromium Trainer Camera Lock Stabilization

### Cross-browser region-map camera correction
- Fixed a real-XAMPP browser-specific Kanto/Hoenn map regression where Microsoft Edge/Chromium could leave the scroll viewport detached from the local trainer while Firefox remained correctly centered.
- Corrected the underlying layout contract instead of browser-sniffing Edge: the world viewport inherited `place-items:center` from the generic map wrapper, and Chromium applies that alignment to oversized block children, allowing the native map stage to begin at a negative horizontal layout coordinate.
- Region world viewports now use `place-items:safe center` with a `normal` fallback. Small maps can remain visually centered, while oversized Kanto/Hoenn maps fall back to a safe start edge so their complete width is reachable by positive scroll coordinates.
- Added `overflow-anchor:none` to the world scrollport/stage/actor layer so Chromium scroll anchoring cannot move the camera during two-second multiplayer presence DOM refreshes.
- Rebuilt camera targeting around the stage's actual rendered origin inside the scrollport rather than assuming the stage starts at scroll coordinate zero.
- Camera correction is deterministic and instantaneous, then receives a two-frame settlement pass after movement so rapid Edge/Chromium scrolling cannot lag behind the trainer.
- Added camera re-locks after page restore (`pageshow`), initial page load, map-image decode/load, viewport resize, font layout settlement and tab visibility restoration.
- Preserved manual map scrollbars; manual panning remains available until the next authoritative player movement or browser/layout re-lock event.

### Regression preservation
- No Kanto/Hoenn map PNG, collision JSON, safe spawn, encounter table, variant rarity, Wild Battle, capture, movement endpoint, presence endpoint or database changes.
- Preserved accepted v22.3.0 Normal/Shiny/Dark/Metallic/Mystic/Shadow encounter parity and accepted v22.2.2 collision/spawn behaviour.
- Package/asset version: **22.3.1**; schema remains revision 23.

## v22.3.0 — Kanto + Hoenn Vortex-Style Variant Encounter Parity

### Complete regional variety pool
- Extended every existing Kanto and Hoenn wild encounter species to the recovered six-form Vortex set: **Normal, Shiny, Dark, Metallic, Mystic and Shadow**.
- Kept the existing Kanto/Hoenn encounter profiles and species weights unchanged; the variety roll happens only after the authoritative area species roll has completed.
- Recovered the ordinary Vortex variety distribution from all 16 day/night `mappoke` pool files: each special variety owns five outcomes within the 336 successful ordinary encounter outcomes, with Normal owning the remaining 311.
- Regional successful-encounter odds are therefore Normal **311/336 (92.5595%)** and each of Shiny/Dark/Mystic/Metallic/Shadow **5/336 (1.4881%)**.
- Added deterministic `pv_world_vortex_variant_from_roll()` and cryptographically secure `pv_world_vortex_variant_roll()` helpers so the probability contract can be exhaustively unit-tested without weakening runtime randomness.

### Data and battle authority
- Audited **62 unique Kanto encounter species**, **95 unique Hoenn encounter species**, **141 unique species combined**.
- Verified all **846 required species/form combinations** exist in the packaged pguide data and all 846 matching Pokémon GIF sprites are present.
- Regional scanner tokens now carry the exact selected variant pguide id/name, display name, sprite, base species and variant label.
- Wild Battle, capture creation, ownership checks and the durable battle ledger continue consuming the server-issued encounter state, so the variety cannot reroll between scanner, battle and capture.
- Preserved v22.1 exact wild-level authority and the accepted v22.2.2 full collision/safe-spawn/grass passability baseline.
- No encounter-table, map image, collision, spawn, multiplayer or database-schema changes.
- Package/asset version: **22.3.0**; schema remains revision 23.

## v22.2.2 — Route 113 Ash-Grass Collision Passability Fix

### Real-XAMPP Route 113 correction
- Corrected the remaining Hoenn Route 113 collision issue found during real-XAMPP testing: the pale ash-covered encounter-grass fields were still behaving as walls after v22.2.1 fixed standard green tall grass.
- Visually isolated the Route 113 ash-grass tile family from look-alike classifier fragments used by trees/structures instead of globally unblocking the shared visual label.
- Restored **275 verified ash-grass cells across four contiguous Route 113 fields** to walkable movement.
- Kept **65 small look-alike cluster fragments** blocked because visual inspection shows they belong to trees/structure geometry rather than the ash-grass fields.
- Preserved the fully sealed Route 113 perimeter, canonical spawn, cliffs, boulders, trees, ledges, building collision and all other Hoenn/Kanto collision data.
- No movement-runtime, encounter-table, Wild Battle, map artwork or database-schema changes.
- Package/asset version: **22.2.2**; schema remains revision 23.

## v22.2.1 — Hoenn Tall-Grass Collision Passability Fix

### Real-XAMPP collision correction
- Corrected the v22.2 Hoenn collision classifier after runtime testing showed standard tall encounter grass behaving as blocking terrain.
- Restored **2,393 verified interior grass cells across 17 Hoenn areas** to walkable movement while retaining 22 perimeter grass-looking cells as sealed world-boundary collision.
- Affected areas: Jagged Pass, Mt. Pyre Peak Lower/Upper, Routes 101, 102, 103, 104, 110, 112, 114, 115, 116, 117, 118, 121 and 123, and Safari Zone.
- Kept Verdanturf Town unchanged after visual auditing showed its matching classifier cells are structure geometry rather than tall encounter grass.
- Preserved tree, building, cliff, rock, fence, cave-wall and non-Surf water collision from v22.2.
- Preserved all 110 canonical spawns exactly; no spawn relocation was introduced by this patch.
- Kanto collision files remain byte-identical to v22.2 because the Kanto tall-grass families were already passable.
- Preserved v22.1 wild-level authority, encounters, multiplayer namespace behavior and all regional presentation assets.
- No database migration. Schema remains revision 23.
- Package/asset version: **22.2.1**.

## v22.2.0 — Kanto + Hoenn Collision & Safe Spawn Rebuild

### Complete regional collision reconstruction
- Replaced the old perimeter-only Kanto/Hoenn collision foundation with full logical-grid collision aligned to every supplied regional map image.
- Rebuilt all **45 Kanto** and **65 Hoenn** collision manifests: 110 areas / 206,632 logical cells audited.
- Added interior blocking for map artwork such as buildings, tree masses, cliffs, cave walls, fences, rocks and non-traversable water instead of allowing movement through those surfaces.
- Sealed every regional map perimeter server-side and rejected tiny disconnected visual false-positive islands during collision generation.
- Added an explicit Kanto Route 12 boardwalk traversal correction so the long dock route remains connected instead of being broken by alternating wooden tile families.
- Kept dedicated sea-route water traversal where required by the current reconstructed gameplay; ordinary land/city water remains blocked until a future Surf-state system exists.
- Preserved `world_map_blocks` as an additive database tuning layer over the shipped static collision.

### Canonical safe spawns
- Revalidated the canonical spawn of every Kanto and Hoenn area against the rebuilt collision surface.
- Preserved already-safe positions and corrected 18 unsafe/low-clearance spawns without changing map artwork.
- Every canonical spawn is in-bounds, walkable, outside rejected tiny islands and has at least two cardinal exits.
- Existing saved positions continue to be server-revalidated on area load; locations that are now blocked automatically repair to the canonical spawn through the existing world presence/position persistence path.

### Diagonal corner-clipping protection
- Hardened eight-direction movement so diagonal steps cannot pass through the corner between touching blocked cells.
- The destination and both orthogonal side cells must be open for a diagonal move.
- Unified that rule between authoritative movement and `blockedDirections`, preventing the client controls from advertising a diagonal step that the server should reject.

### Regression preservation
- No map presentation files were rescaled or altered.
- Preserved accepted Kanto v21.5 fixed resized presentation, v22 Hoenn presentation, v22.1 wild-level authority, encounters, multiplayer world/area namespaces and Vortex maps 1–25.
- No database migration. Schema remains revision 23.
- Package/asset version: **22.2.0**.

## v22.1.0 — Wild Encounter Level Authority Fix

### Exact map → battle level preservation
- Fixed a runtime-integrity regression affecting Kanto and Hoenn early-game encounters: the map scanner could correctly generate levels below 5, while the Wild Battle initializer silently raised every level 2–4 encounter to level 5.
- Removed the historical `max(5, ...)` battle-start clamp. The level stored in the one-use encounter session is now authoritative and is preserved exactly for battle display, HP/damage calculations, capture creation and the persistent wild-battle result ledger.
- Added fail-closed validation for corrupt/missing encounter levels instead of silently substituting another level.
- Preserved the accepted Vortex encounter behavior; existing Vortex maps already generate their historical minimum level 5 before the battle handoff.
- No database migration. Schema remains revision 23.
- Package/asset version: **22.1.0**.

## v22.0.0 — Hoenn World Expansion

### Hoenn as the third exploration world
- Added `world_key = hoenn` alongside the accepted Vortex and Kanto worlds.
- Integrated 65 unique user-supplied resized Pokémon Emerald area PNGs; the duplicated Slateport source is deduplicated intentionally.
- Hoenn gameplay maps render at their fixed natural PNG dimensions inside the same accepted scrollable region viewport used by Kanto.
- Logical movement remains on a 16px GBA tile grid while the supplied resized presentation uses 32px per logical cell.
- Added independent per-area saved position, server collision, two-second multiplayer presence and world-scoped wild-battle return handling through the existing v21 world tables.

### Hoenn selector and overworld presentation
- Added Hoenn to World Maps as a third production world card.
- Added the supplied Hoenn overworld artwork to the region summary panel.
- Replaced Kanto's old white-border atlas selector art with the newly supplied Kanto overworld artwork without changing accepted Kanto gameplay maps.
- Generated 65 lightweight local Hoenn WebP thumbnails (~1.7 MB total) from the 36 MB gameplay map pack so map selection does not preload full-size images.

### Hoenn areas and navigation
- Registered towns/cities, Routes 101–106 and 109–124, Petalburg Woods, Granite Cave floors, Fiery Path, Jagged Pass, Meteor Falls floors, Mt. Chimney, Mt. Pyre floors/peaks, Rusturf Tunnel, Safari Zone, Seafloor Cavern, Shoal Cave, Sky Pillar and Victory Road.
- Connected only area relationships that can be supported by supplied maps. Missing Emerald routes such as 107–108 and 125+ are not fabricated or silently bypassed.
- Areas separated by missing source maps remain individually selectable and can be connected later when matching assets are supplied.

### Hoenn wild habitats
- Added 36 Hoenn encounter profiles using 95 species verified against the recovered `original_pguide.sql` catalogue.
- Added route-, forest-, cave-, mountain-, sea-, Safari-, Shoal-, Seafloor-, Sky Pillar- and Victory Road-appropriate encounter pools with normalized 100% weights.
- Hoenn encounters use the same hardened one-use Wild Battle token/ledger flow and return to the exact Hoenn area after Run, Capture, Victory or Defeat.

### Runtime generalization
- Generalized the region-map registry, movement endpoint, presence endpoint, connected-area UI, wild encounter runtime and Wild Battle return routing from Kanto-only logic to reusable Kanto/Hoenn world logic.
- Preserved accepted Vortex map runtime and accepted Kanto v21.5 presentation/gameplay architecture.
- No database migration is required over v21.5; schema revision remains 23.
- Package/asset version: **22.0.0**.

## v21.5.0 — Kanto Resized Fixed Presentation

- Promoted the user-supplied `KantoResized.zip` PNG sections to the Kanto gameplay presentation layer.
- Kanto keeps the accepted v21.4 server-authoritative logical grid, collision, encounters, saved positions and multiplayer state.
- Every resized map is displayed at its own natural PNG dimensions inside the existing scrollable Kanto map panel.
- Added a distinct `display_tile_size` contract: 16px logical FireRed cells remain authoritative while the supplied 2× presentation maps display those cells at 32px.
- Removed any need for user-selectable Kanto scaling; the site has one fixed Kanto presentation size.
- Trainer and remote-player sprites are positioned/scaled to the fixed 32px display grid without changing server X/Y coordinates.
- Kanto area selection continues to use the smaller accepted v21.4 source maps as lazy-loaded previews, avoiding a large full-resolution download before entering an area.
- No database migration. Schema remains revision 23.


## v21.4.0 — Authoritative Native Kanto Map Replacement

### Native FireRed/LeafGreen area maps
- Rebased Kanto presentation on the user-supplied archive of individual FireRed/LeafGreen WebP maps instead of atlas-derived gameplay crops.
- 45 unique Kanto map sections are registered as playable areas; the duplicate Route 22 source is intentionally deduplicated.
- Production map files are byte-for-byte copies of the supplied WebPs and are displayed at their native image dimensions with no browser zoom selector or CSS upscaling.
- Route 1 now uses the supplied 384×640 map directly (24×40 logical 16px cells) instead of the former 128×256 atlas crop.
- The joined FullKantoMap master remains preserved as geographic/reference provenance only.

### Fixed presentation contract
- Removed all player-facing Kanto scale/zoom controls.
- Kanto exploration renders one fixed native-resolution map asset per area.
- No transform, filter, object-fit enlargement, or alternate 2×/3×/4× render assets are used.
- Removed the technical grid and inner vignette from directly over Kanto artwork so the source pixels remain visually unobstructed.

### World reconstruction
- Consolidated split atlas-derived Route 2 and Route 20 sections into the supplied complete Route 2 and Route 20 maps.
- Added authentic supplied interiors/areas including Mt. Moon floors, Diglett's Cave, Seafoam Islands 1F, Victory Road 2F and Safari Zone sections.
- Expanded Kanto encounter coverage for Mt. Moon, Diglett's Cave, Seafoam Islands and Safari Zone using species verified against the recovered Pokédex source.
- Existing world-map DB collision overrides remain available for runtime cell tuning.

### Preserved accepted systems
- v21.1 Kanto wild-battle origin/return flow and synchronized two-account presence architecture remain intact.
- Accepted Vortex map, Vortex presence and v20.3 AI-controlled trainer battle files remain byte-for-byte unchanged.
- Database schema revision remains 23; this update is file/content-only over v21.1.

## v21.1.0 — Kanto Open-World Atlas Integration

### Kanto master atlas
- Integrated the user-supplied `FullKantoMap.png` as the authoritative Kanto overworld source.
- Preserved the 2048×2007 master PNG untouched under `html/static/images/worlds/kanto/FullKantoMap.png`.
- Derived 41 original-scale playable overworld sections without resizing or redrawing the source pixels.
- Added towns/cities, Routes 1–25 where represented by the supplied overworld atlas, Route 2 north/south sections, Route 20 east/west sections, Viridian Forest, Seafoam exterior, Victory Road exterior and Indigo Plateau approach.
- Interior cave/dungeon maps are deliberately not fabricated when they are absent from the supplied atlas.

### Kanto movement and collision
- Added per-area static collision manifests generated from the supplied atlas with conservative walkable-surface classification, plus hand-audited traversal corrections for Cinnabar Island and Seafoam exterior.
- Blank atlas whitespace and obvious water/obstacle cells are rejected server-side for land areas.
- Sea/coast sections use water-aware traversal masks.
- Existing `world_map_blocks` remains an additive database override layer for future hand-tuned collision corrections.
- Every ready area has validated 16px grid dimensions, a valid unblocked spawn selected from its largest reachable walkable component, and persisted per-account world position.

### Connected Kanto network
- Added explicit connected-area navigation between the complete overworld route graph.
- Pallet Town, Viridian, Pewter, Cerulean, Saffron, Celadon, Vermilion, Lavender, Fuchsia, Cinnabar, League approach and southern sea routes are connected through their logical neighboring areas.
- Connected-area navigation only targets validated ready areas.
- Kanto remains independently namespaced from Vortex maps 1–25.

### Kanto wild encounters
- Added 27 data-driven Kanto encounter profiles with 47 recovered/local Pokémon species, with every configured species verified against the recovered `pguide` catalogue and local sprite library.
- Encounter levels and pools are FireRed/LeafGreen-inspired but tuned for this reconstructed MMO rather than claiming byte-for-byte retail encounter tables.
- Towns/cities remain encounter-free; wild routes, forest, coast/sea and Victory Road exterior use location-appropriate profiles.
- Every encounter profile is weight-normalized to 100%. Kanto encounters enter the hardened one-use Wild Battle runtime and return to the correct Kanto area after Run/Capture/Victory/Defeat.
- Existing Vortex wild-battle return behavior remains intact.

### Presentation
- Kanto Map Select is now an active production world rather than a placeholder.
- Added master-atlas provenance panel, grouped settlements/routes/landmarks, live trainer counts, wild-habitat indicators and per-area previews.
- Added connected-area navigation to the Kanto exploration sidebar.

### Compatibility cleanup
- Retired the orphaned legacy AJAX Members tab that still contained historical direct SQL and a missing ad include; old calls now redirect safely into the reconstructed Trainer Network.

### Release metadata
- Package/asset version: **21.1.0**.
- Database schema revision remains **23**; no new database migration is required over v21.0.0. Installations upgrading directly from the accepted v20.3 baseline must still run the v21 Upgrade / Repair migration.

# Changelog

## v21.0.0 — Live PvP Settlement, Event Center Completion & Kanto World Preparation

### Live PvP durable settlement

- Added database-idempotent per-player Live Battle settlement keyed to the immutable `live_battle.id`.
- Victory EXP/money and defeat counters now commit atomically with a one-time settlement marker so retries cannot duplicate rewards.
- Participating Pokémon ownership is revalidated under lock before EXP is applied.
- Once one trainer settles, the second settlement is forced to the complementary outcome under the same row lock, preventing impossible win/win or loss/loss receipts from stale browser/session state.
- Added durable `live_battle_result.php` receipts and a completed challenge state.
- Added schema support for settlement metadata and upgraded the Live Battle challenge ENUM to include `completed`.
- Removed remaining PHP-5-era bareword type/status assumptions from the Live Battle runtime and kept battle actions on direct CSRF/action-token protected forms.

### Event Center production reconstruction

- Rebuilt the Event Center around centralized server-authoritative event helpers and transaction-safe actions.
- Event Ticket unlocks are atomic and cannot double-consume tickets.
- DNA Splicer purchase is an atomic ₽500,000 account purchase.
- Kyurem fusion revalidates ownership, active-team exclusion, compatible species/form variants and inventory under row locks before mutation.
- Cosplay Pikachu collection verification now checks the exact recovered 28 normal Unown forms (A–Z, Ex, Qm) under the current Original Trainer name.
- Added normalized `event_completions` state so one trainer can complete multiple event rotations safely without overloading the historical `done_event.id` semantics.
- Promo-code creation is cryptographically random, one-use compatible and tied back to the event completion row.
- Retired direct historical event include gameplay endpoints behind compatibility redirects and removed exposed legacy event logic from active paths.

### Future Kanto world architecture

- Added an independent `world_key` namespace so Vortex and Kanto presence/state can never collide.
- Existing Vortex maps remain IDs 1–25 and continue using their accepted runtime.
- Added a manifest-driven Kanto registry using stable area slugs, variable map dimensions, exact tile-grid validation, spawn points and explicit transitions.
- Added dedicated `world_map_blocks` collision and `world_map_positions` persistence tables.
- Added Kanto-ready movement and two-second multiplayer presence endpoints using server validation.
- Kanto remains data-gated with zero playable areas until the user-provided FireRed-quality local map assets and collision/transition data are registered and validated. No placeholder/generated Kanto maps are shipped.
- Area entry fails closed if the source image dimensions do not exactly match the configured tile grid, or if no unblocked spawn can be found.

### Runtime compatibility sweep

- Removed additional legacy Ajax initializers from Battle Select and Sidequests.
- Corrected remaining PHP 8 bareword compatibility hazards found in legacy nature/Pokédex/member/utility paths.
- Preserved the real-XAMPP accepted v20.3 AI trainer battle flow and v20.1 realtime Vortex trainer presence behavior while namespacing map presence for future worlds.

### Migration

- Package/asset version: **21.0.0**.
- Database schema revision: **23**.
- Existing installations must run `_setup.php` → **Upgrade / Repair** → `UPGRADE`. Do not Fresh Rebuild an existing database.

## v20.3 — Post-Battle Forbidden & Legacy Ajax Isolation Fix

### Standard trainer battle post-result isolation

- Removed the recovered `gameInit.js` initializer from `battle.php`. Standard/gym/trainer/Sidequest/event/clan battle pages no longer start automatic message-notification traffic after rendering.
- Removed the recovered `functions.js`, `menu.js`, `suggest.js`, and legacy jQuery runtime from the standard battle page. The battle engine now uses normal server-rendered POST/redirect navigation and does not require the historical `AjaxRequest` stack.
- Added a tiny battle-local `disableSubmitButton()` helper and retained the no-JS arithmetic helper without any XHR/fetch/network behavior.
- Converted in-battle Pokédex links from recovered Ajax-sidebar interception to ordinary same-origin navigation.
- Removed the unused legacy `#alert` and `#notification` containers from the standard battle page so an obsolete cached Ajax handler cannot paint a global Forbidden overlay there.
- Marked the page with `data-pv-battle-network-isolated="1"` for future regression audits.

### Cache correctness

- Bumped the package/asset revision to **20.3.0**.
- The v20.2 correction changed `gameInit.js`, but the recovered battle template loaded that file without a release query string while `.htaccess` allows JavaScript to cache for seven days. v20.3 removes that dependency from the battle page entirely, making stale v20.1 notification JavaScript irrelevant to standard battle results.
- The production server-side same-origin/CSRF protections remain unchanged; the fix removes obsolete client requests rather than weakening security.

### Result-page polish

- Improved battle-result option-link contrast so Rebattle / team / collection / PokéMart actions remain readable on the dark navy production surface.

### Preserved systems

- v20.2 first-alive AI opponent selection, PHP 8 battle-type/status hardening, battle form CSRF/action tokens, and v20.1 realtime map presence are preserved.
- No database migration is added; schema revision remains **21**.

### Validation

- Full PHP/JavaScript/CSS validation and archive-integrity results are recorded in the v20.3 audit files included with the package.
- `battle.php` contains no references to `AjaxRequest`, `XMLHttpRequest`, `fetch()`, `gameInit.js`, recovered Ajax sidebar handlers, or legacy JavaScript libraries.

## v20.2 — AI Trainer Battle Runtime & Legacy XHR Fix

### Computer-controlled trainer battle runtime

- Fixed the v20/v20.1 opponent-slot cascade that could advance a valid one-Pokémon opponent from slot 1 through intentionally empty compatibility slots and finally render the recovered `.gif` glitch placeholder at level/HP 0.
- Added a server-side, authoritative first-alive-opponent selector bounded by the hydrated opponent team count. Empty compatibility slots can no longer become active combatants.
- Added stale-session self-repair so an already-open v20/v20.1 battle session pointing at an empty opponent slot is redirected to the first valid living opponent slot when possible.
- Reset the recovered ten-entry attack metadata cache at the start of every standard/gym/trainer/Sidequest/event/clan battle to avoid carrying move metadata across battles.
- Converted historical PHP bareword type/status constants in the standard battle engine to explicit strings for PHP 8 runtime safety.
- Corrected the recovered Fairy-vs-Steel type-chart expression from the undefined bareword `damage` to `$damage`.

### Legacy background-request cleanup

- Replaced the battle-page automatic message-notification lookup with a non-blocking same-origin GET.
- Mirrored the same safe notification transport into the retained non-v3 compatibility `gameInit.js` copy so an older legacy include cannot reintroduce the POST/403 overlay.
- Background notification failures no longer invoke the recovered global Ajax error overlay and therefore cannot cover a battle with a `403 Forbidden` banner.
- Hardened `tabs/toolbox.php`: notification reads are explicit GET operations; notification dismissal is an explicit CSRF-protected POST using prepared statements and escaped output.

### Preserved v20.1 map fix

- No map presence behavior was changed in v20.2. The v20.1 two-browser same-map synchronization/default-visible behavior is preserved exactly after successful real-XAMPP confirmation.
- Schema revision remains **21**; this corrective build requires no new database migration when upgrading from v20.1.
- Asset/package revision bumped to **20.2.0** for cache-busting of the corrected legacy game initialization script.

### Validation

- Full PHP/JavaScript/CSS validation and archive-integrity results are recorded in the v20.2 audit files included with the package.
- Added targeted static/runtime-simulation checks for one-, two-, and gapped-party first-alive opponent selection.
- Standard battle source contains no remaining unquoted Pokémon type `case` labels, bareword status assignments, or recovered `$damage = damage * 0` expression.

## v20.1 — AI Trainer Battle & Realtime Map Presence Fix

### Computer-controlled trainer battles

- Removed the fragile dependency on the global HTML output filter for standard battle CSRF/action-token fields; every standard battle POST form now emits its security fields directly at source.
- Explicitly load the production `vortex-modern.css` and `vortex-modern.js` assets from `battle.php`, so trainer battles retain the modern dark navy/cyan/gold presentation even if a legacy-response rewrite is skipped.
- Removed the retired commented AdBlocker replacement script from the standard battle page, eliminating a legacy fragment that could leak malformed comment text into the rendered page.
- Preserved v19/v20 server-side move, item, active-Pokémon, opponent-route and current-team validation.

### Realtime world-map trainer presence

- Normalized `memonmap` to clear positive semantics: `1 = show other trainers on world maps`, `0 = hide other trainers`.
- World-map trainer visibility is now enabled by default for new trainers and is normalized to enabled once when upgrading a pre-v20.1 database.
- Options now refresh the active session preference immediately after a successful save, removing cross-browser/session drift.
- Map presence reads the authoritative database preference instead of trusting a stale login-time session cache.
- Added `map_presence.php`, a read-only same-origin JSON presence endpoint for the active map.
- Added lightweight two-second visible-tab presence polling so another trainer can appear/move/disappear without requiring the observing player to take a movement step.
- Presence refresh failures are non-fatal; normal movement remains available if a transient poll fails.
- Retained the existing same-map filter, online cutoff, server-recorded positions and local trainer sprites.

### Migration and compatibility

- Upgrade / Repair advances schema metadata to revision `21`.
- Existing `members.memonmap` and `members_options.memonmap` values are normalized to enabled once during the v20.1 upgrade because prior revisions used contradictory boolean meanings.
- Both visibility columns are normalized to `TINYINT NOT NULL DEFAULT 1`.
- Legacy Options compatibility routing now uses the same positive visibility semantics as the modern Options page.
- Asset/package revision bumped to `20.1.0`.

### Runtime issue addressed

This patch directly targets two real-XAMPP regressions reported against v20: a plain/incorrect standard trainer battle page whose POST flow could not progress reliably, and inconsistent same-map trainer visibility between Firefox and Edge despite apparently matching Options settings.

### Validation

- 166/166 PHP files pass PHP 8.4 syntax validation.
- 30/30 bundled JavaScript files pass Node syntax validation.
- 34/34 CSS files pass structural validation.
- All 25 numbered recovered world maps are present.
- All 4 standard battle POST forms contain direct CSRF + one-use action-token fields.
- v20.1 changed-file literal route/asset scan: 93 references / 0 unresolved.
- Critical old combat AJAX submission matches: 0.
- Active PHP `/var/www/...` dependencies: 0.

## v20 — Live Battle Session Integrity & Battle Routing Hardening

### Live Battle identity and lifecycle

- Bound every newly accepted challenge to the exact immutable `live_battle.id` created for that match.
- Added the exact battle ID to both players' server-side Live Battle session state.
- Scoped Live Battle row verification and recovered state mutations to the exact match ID and player pairing.
- Added per-player one-use initialization flags so the same match cannot be re-entered from full HP/status through a replayed entry URL or second browser session.
- Added safe one-time compatibility binding for accepted challenges created immediately before the v20 migration.
- Expire other pending/accepted challenges between the same trainer pair when a match is accepted.
- Expire abandoned accepted challenge records with the 24-hour abandoned battle-row cleanup.
- Added/ensured `idx_live_pair` and `idx_live_created` indexes for recovered Live Battle tables.
- Revalidate both active teams before Live Battle acceptance and initialization.

### Team integrity

- Replaced contiguous-slot assumptions in Live Battle team hydration with ownership-validated integer slot loading that safely supports gaps.
- Deterministically initialize all six local/opponent battle slots before seeding valid rows.
- Reject fainted Pokémon at the Live Battle switching boundary.
- Re-read the trainer's current `members.s1`–`s6` row when a standard battle begins instead of trusting the login-time team session cache.
- Refresh the recovered `my_team` compatibility cache immediately after Change Team saves successfully.

### Standard battle routing

- Normalize battle-entry routes and reject requests that attempt to select multiple opponent modes simultaneously.
- Reject trainer self-battles.
- Use prepared opponent lookups for trainer, gym, event and Sidequest battle entry.
- Reject nonexistent opponent rows and opponent teams with no valid battle Pokémon before combat initialization.
- Reject direct/stale `battle.php` continuation requests when no battle session exists.

### Sidequest progression

- Synchronize Sidequest session progress from the authoritative member row before battle authorization and hub rendering.
- Require a Sidequest battle URL to match current database progression.
- Replace the loose post-victory `sidequest = sidequest + 1` update with row-locked, conditional exactly-once progression advancement.
- Return Sidequest victories through the Sidequest hub rather than linking back to the defeated opponent ID.
- Added CSRF fields to all six milestone Claim forms, matching the transactional reward handler.
- Added a player-facing stale-route message when a Sidequest battle no longer matches server progress.

### Validation

- Bumped package/asset revision to `20.0.0` and schema metadata to revision `20`.
- 165/165 PHP files pass PHP 8.4 syntax validation.
- 30/30 bundled JavaScript files pass syntax validation.
- 34/34 CSS files pass structural validation.

## v19 — Production Runtime Integrity Hardening

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

## v12 — Safekeeping Baseline

- Protected checkpoint before the current wild-battle reconstruction.
- Includes the polished dark navy sci-fi UI, XAMPP installer/upgrade flow, PHP 8 compatibility layer, reconstructed map runtime, transactional trading foundation, fossil restoration, evolution reconstruction and supporting database repair systems.
