# Local server command console

Pokemon Vortex v33.0.0 extends the existing local activity console with editable input and a complete non-chat command registry. Open **PokemonVortex_ServerConsole.cmd** in the PokemonVortex folder. The same window accepts commands while displaying the existing filtered activity stream.

## Install or upgrade

1. Back up your installation and database. Apply this release over the supplied v32 baseline, preserving your database connection values in `PokemonVortex/config/app.php`.
2. Start Apache and MySQL in XAMPP as usual.
3. Open `PokemonVortex_ServerConsole.cmd`. Keep `server_console.php`, the `.cmd`, and `PokemonVortex_ServerConsole.ps1` together in the application folder.
4. Type `help`, then `serverinfo`. The first command that needs the database runs the existing additive repair path plus the new console migrations. Existing accounts and progress are retained. The same migrations run during a fresh install or repair through local `_setup.php`.
5. Type `select YourTrainerName`, then `team`, `bag`, or `location`. Type `exit` or press Ctrl+C to close the console.

The launcher discovers XAMPP PHP automatically. If PHP is not found, set the launcher’s `PHP_EXE` path. Windows PowerShell supplies the keyboard editor; PHP remains the authoritative command worker. The frontend and worker communicate only through local process pipes.

There is no browser command page, network command listener, or chat command parser. The existing messaging features are unaffected. Granting a trainer OWNER or another staff rank never grants browser access to this console.

## Typing and selecting targets

Commands accept an optional leading `/`. Use double quotes for names containing spaces. Trainer targets accept an exact name or `#ID`; use `name:1234` for a numeric username. A command requesting `owned-ID` means the individual collection Pokémon ID shown by `pokemon` or `team`, not a species Pokédex number. Species selectors explicitly say `catalog-ID` or `speciesID`.

```
help
select Akuma
team
pokemon
bag
givepokemon Akuma "Shiny Pikachu" 25
giveitem Akuma "Poke Ball" 25
givemoney Akuma 10000
locations kanto
teleport kanto:pallet-town
help event
```

Use `locations` to obtain the installed world and area keys. The examples require that the named trainer exists. Commands with an optional player target use the selected trainer when that argument is omitted. `select none` clears the selection. A selected owner also limits specimen edits to that owner; clear or change selection before editing another trainer’s specimen.

The editor supports Backspace, Delete, arrows, Home, End, Up/Down history, Ctrl+U to clear a line, and Ctrl+L to clear the display. History exists only in the current process. Credential and confirmation operations are excluded from history. Activity messages redraw around your unfinished input. Command lines are bounded and parsed as data; they are never evaluated as shell code.

## Permissions and confirmation

`config/app.php` contains `admin_console` settings:

```php
'admin_console' => [
    'enabled' => true,
    'operator_name' => '',
    'operator_rank' => 'OWNER',
    'enable_developer_commands' => false,
    'disabled_commands' => [],
    // Keep the included motd and rules settings as desired.
],
```

An empty operator name uses the local operating-system account. The local rank limits command availability. Ranks ascend through PLAYER, VIP, HELPER, MODERATOR, GAME MASTER, ADMIN, DEVELOPER, OWNER. `help` and `commands` honor the configured policy. Disable individual commands using canonical names or aliases; `event reward` and other event subcommands can also be disabled. Aliases cannot bypass a disabled canonical command.

Developer commands require explicit `enable_developer_commands => true`, even for OWNER. After changing this setting, reopen the console. When already enabled, `reloadconfig` reloads policy by restarting the command worker. Reloading clears the selected trainer, pending confirmations and queued input. This opt-in protects production data from testing operations such as permanent specimen cloning.

Destructive operations print their exact action and bound trainer ID, then generate a one-use confirmation token. Nothing changes until you type the displayed `confirm TOKEN` within 60 seconds. `cancel`, a changed selection, an expired token, or a replayed token cannot execute the pending operation. Trainer names are resolved to IDs before confirmation so a recreated name cannot redirect a pending action.

Administrative execution records the local operator, configured rank, command, target, timestamp and outcome in `console_audit`. The normal activity log also records the audit reference. `audit` reads recent records. Generated account passwords are shown once in the immediate command result, omitted from audit and history, and stored using the normal password hash. Password commands do not accept plaintext passwords as arguments.

## Behavior in this PHP server

The proposal includes commands designed for a continuously running game process. This release gives each one explicit behavior suitable for the existing PHP/XAMPP architecture.

| Command area | Implemented behavior |
| --- | --- |
| `save`, `saveall`, `dbsave` | Gameplay already commits during each request. `save` refreshes a trainer’s authoritative progression; `saveall` verifies persisted populations; `dbsave` reports database durability settings. No unsubmitted browser input is recoverable. |
| `shutdown`, `restart`, `resume` | Schedule a persisted application maintenance gate. Shutdown closes this game until `resume`. Restart closes it for two seconds and refreshes supported request caches. Schedules still apply if the console closes. Shared Apache, MySQL and other hosted games remain running. |
| `reloadscripts` | Restart the console worker and request invalidation of this application’s cached PHP scripts on the next web request. `serverinfo` reports the last web reload result. Restricted OPcache APIs require a local Apache restart. |
| `ping`, `uptime`, `threads`, `memory`, `connections` | Report actual database round-trip timing, console uptime, database thread/connection status and console memory. They do not invent player latency or a persistent PHP world-process uptime. |
| `battle`, `win`, `lose`, `sethp`, `setstatus`, `addmove`, `clearmoves` | Operate on persistent isolated console test duels. `battle attack` advances a test simulation using real team/move/type data with a documented base-50 developer stat model. Test outcomes never grant live rewards or ranked results. `learn` edits real moves; `addmove` edits the test snapshot. |
| Nature, ability, IV and EV commands | Update actual specimen metadata and expose it through Pokémon inspection. EVs and nicknames are visible in the owned Pokédex. Existing live Vortex combat formulas remain intact; IV/EV/nature-derived test statistics belong to the console test model. Existing wild HP continues to use its normal HP-IV calculation. |
| `heal`, `healall` | Recover active unranked PvE session HP/status on the next request. Teams outside battle already start healed. Protected live PvP and trade state cannot be overwritten. |
| `spawn`, `despawn` | Queue one normal catchable wild encounter for the selected human trainer’s current map. It has an expiry, unique receipt and normal battle/capture rules. Despawn invalidates encounters whose battles have not started. It does not introduce a second NPC engine. |
| `weather`, `day`, `night` | Weather controls map atmosphere. Day/night also selects the existing Vortex encounter pools, including AI selection. Weather does not add combat damage modifiers. `day auto` restores normal map-time preferences. |
| `jail`, `freeze` | Enforce a server-side holding/restriction view that blocks movement, battles, trading and other gameplay until release. Jail can expire automatically; no new traversable prison map is invented. |
| `warn` | Persist a moderation record. No chat or private message is sent. |
| `event create` | Create a staff-hosted event visible in the Event Center. The operator manages enrollment and grants item, Pokémon or currency rewards. Built-in event mechanics remain available. Reward grants and receipts commit together; repeating a reward intentionally grants again. |
| `trade`, `friend`, `spectate` | Reuse real trade escrow and friend requests; spectate reads an existing authoritative PvP snapshot. Trade acceptance remains in the established Trade Center. No communication channel is added. |
| `playtime` | Track observed active request intervals starting with this update. AFK periods and gaps over five minutes are excluded; historical playtime is unavailable. |

Changing inventory, specimens, team or currency invalidates stale gameplay snapshots. Existing web sessions refresh their authoritative values and reject a stale mutation with a refresh message. Kick, bans, locks and password resets invalidate existing sessions. Timed bans expire through login and request enforcement. Teleports use validated map coordinates and a revision so an old map request cannot move the trainer back.

## Command reference

The tables below are generated from the shipped registry. `*` means explicit developer opt-in is required. `Confirm` identifies operations requiring a one-use token. All commands run only in the local console, regardless of the rank name.

### General and server management

| Syntax | Local rank | Confirm | Effect |
| --- | --- | --- | --- |
| `help [command]` | PLAYER | No | Show available commands or detailed syntax. |
| `commands` | PLAYER | No | List commands allowed by the local console configuration. |
| `select <username\|#ID\|none>` | PLAYER | No | Choose the trainer used by commands with an optional player argument. |
| `who` | PLAYER | No | List recently active trainers (last five minutes). |
| `online` | PLAYER | No | Count recently active human trainers. |
| `serverinfo` | PLAYER | No | Show application, database and local console status. |
| `version` | PLAYER | No | Show the installed application release. |
| `uptime` | PLAYER | No | Show console uptime; PHP web requests do not share a persistent game process. |
| `time` | PLAYER | No | Show current UTC server time. |
| `ping` | PLAYER | No | Measure a local database round trip in milliseconds. |
| `motd` | PLAYER | No | Read the configured operator message of the day. |
| `rules` | PLAYER | No | Read the configured server rules in this console. |
| `debug [on\|off]` | DEVELOPER* | No | Toggle detailed local diagnostic output. |
| `reload` | DEVELOPER* | No | Reload configuration and invalidate supported data caches. |
| `reloadconfig` | DEVELOPER* | No | Reload configuration, command policy and database connection. |
| `reloadscripts` | DEVELOPER* | No | Invalidate server OPcache and restart this command worker. |
| `saveall` | ADMIN | No | Verify committed database state and count persistent trainers and Pokémon. |
| `dbsave` | DEVELOPER* | No | Verify storage engine durability settings; writes commit immediately. |
| `clearcache` | DEVELOPER* | No | Advance server cache revision and clear console-local cache values. |
| `gc` | DEVELOPER* | No | Collect PHP cycles in this console process. |
| `memory` | DEVELOPER* | No | Report actual memory use of this console process. |
| `threads` | DEVELOPER* | No | Show MySQL thread counters and this single-threaded PHP worker. |
| `connections` | DEVELOPER* | No | Show current database connections visible to the configured DB account. |
| `logs [count]` | DEVELOPER* | No | Read recent sanitized application activity (1–100 lines). |
| `audit [count]` | ADMIN | No | Read recent command audit records without credential output. |
| `test` | DEVELOPER* | No | Run read-only installation, registry and schema diagnostics. |
| `shutdown <seconds>` | ADMIN | Yes | Schedule game maintenance; shared XAMPP services keep running. Requires confirmation. |
| `restart <seconds>` | ADMIN | Yes | Schedule a two-second game maintenance window and fresh request caches. Requires confirmation. |
| `resume` | ADMIN | No | Cancel scheduled maintenance and reopen the game. |
| `confirm <token>` | PLAYER | No | Confirm the exact pending destructive command within 60 seconds. |
| `cancel` | PLAYER | No | Discard the pending confirmation without changing game state. |
| `exit` | PLAYER | No | Close this operator console; the game keeps running. |

### Accounts and moderation

| Syntax | Local rank | Confirm | Effect |
| --- | --- | --- | --- |
| `profile [player\|#ID]` | PLAYER | No | Show public trainer and collection information. |
| `stats [player\|#ID]` | PLAYER | No | Show authoritative trainer statistics. |
| `playtime [player\|#ID]` | PLAYER | No | Show observed active time since console tracking was installed. |
| `playerinfo [player\|#ID]` | MODERATOR | No | Show account, rank and moderation state. |
| `afk [player\|#ID]` | PLAYER | No | Set the selected or named trainer AFK. |
| `back [player\|#ID]` | PLAYER | No | Clear the selected or named trainer AFK status. |
| `save [player\|#ID]` | ADMIN | No | Recalculate and commit trainer progression; requests already persist gameplay automatically. |
| `resetplayer <player\|#ID>` | ADMIN | Yes | Reset progression, collection, inventory, clan membership and world position to a level-18 Bulbasaur start; retain identity, credentials and moderation. |
| `warn <player\|#ID> <reason>` | MODERATOR | No | Record a moderation warning (no chat/message is sent). |
| `warnings <player\|#ID>` | MODERATOR | No | List the latest 50 recorded warnings. |
| `kick <player\|#ID> <reason>` | MODERATOR | No | Revoke all active sessions; the trainer can sign in again. |
| `ban <player\|#ID> <duration\|permanent> <reason>` | MODERATOR | Yes | Ban and revoke sessions. Duration: 30s, 15m, 2h, 7d or permanent. |
| `unban <player\|#ID>` | MODERATOR | No | Remove a temporary, permanent or legacy account ban. |
| `jail <player\|#ID> <duration\|permanent> <reason>` | MODERATOR | Yes | Place a trainer in the administrative holding area; all gameplay is blocked until release. |
| `unjail <player\|#ID>` | MODERATOR | No | Release a trainer from the administrative holding area. |
| `freeze <player\|#ID>` | MODERATOR | No | Suspend movement and gameplay actions. |
| `unfreeze <player\|#ID>` | MODERATOR | No | Resume movement and gameplay actions. |
| `invisible [player\|#ID]` | GAME MASTER | No | Hide trainer presence on maps and public online lists. |
| `visible [player\|#ID]` | GAME MASTER | No | Restore trainer map and public online visibility. |
| `ipinfo <player\|#ID>` | ADMIN | No | Show the last direct connection address and user agent; never trusts forwarded headers. |
| `history <player\|#ID>` | MODERATOR | No | List the latest 100 account and moderation actions. |
| `createaccount <username>` | ADMIN | No | Create a complete trainer with a level-18 starter and a generated password shown once. |
| `deleteaccount <player\|#ID>` | OWNER | Yes | Delete account and owned progression atomically; return other trainers’ escrow and preserve administration audit. |
| `resetpassword <player\|#ID>` | ADMIN | Yes | Generate a replacement password, invalidate reset links and revoke sessions; display secret once. |
| `setrank <player\|#ID> <rank>` | OWNER | Yes | Set stored trainer rank. Browser accounts never receive console access. |
| `promote <player\|#ID>` | OWNER | Yes | Advance trainer rank by one tier; does not grant browser console access. |
| `demote <player\|#ID>` | OWNER | Yes | Lower trainer rank by one tier. |
| `lockaccount <player\|#ID>` | ADMIN | Yes | Lock account login and revoke current sessions. |
| `unlockaccount <player\|#ID>` | ADMIN | No | Unlock account login; existing bans remain enforced. |

### Pokémon items economy and testing

| Syntax | Local rank | Confirm | Effect |
| --- | --- | --- | --- |
| `pokemon [player] [page]` | PLAYER | No | List owned Pokémon with unique specimen IDs, 30 per page. Omit player to use select. |
| `team [player]` | PLAYER | No | Inspect the six active team slots; omit player to use select. |
| `bag [player]` | PLAYER | No | Show nonzero inventory counts; omit player to use select. |
| `givepokemon <player> <species\|#catalog-ID> [level]` | GAME MASTER | No | Create an owned specimen (default level 5, max 100), metadata and collection count; fill an empty team slot. Quote names with spaces. |
| `removepokemon <player> <owned-ID>` | GAME MASTER | Yes | Permanently remove one owned specimen; rejects escrow/live battles and the last active team member. |
| `heal [player]` | GAME MASTER | No | Queue one full HP/status recovery for unranked PvE sessions on their next request. Idle teams already start battles healed. |
| `healall` | GAME MASTER | No | Queue unranked PvE team recovery for online players; skip players with protected battle/trade state. |
| `evolve <owned-ID> [target-species]` | GAME MASTER | No | Force a configured evolution child route, preserving variant, specimen ID, moves and metadata. Branches require target; no item cost. |
| `devolve <owned-ID> [target-species]` | GAME MASTER | No | Force a configured evolution parent route, preserving variant, specimen ID, moves and metadata. Branches require target; no item cost. |
| `setlevel <owned-ID> <1-100>` | GAME MASTER | No | Set level and the matching Vortex EXP threshold (level × 500); refresh progression. |
| `setexp <owned-ID> <0-2000000000>` | GAME MASTER | No | Set EXP and recompute level using Vortex EXP/500 progression capped at 100. |
| `learn <owned-ID> <move> [slot:1-4]` | GAME MASTER | No | Permanently teach a catalogued move. Without slot, use a blank slot; full movesets require an explicit slot. |
| `forget <owned-ID> <move\|slot:1-4>` | GAME MASTER | No | Permanently remove a move or slot. Keep at least one move; clearmoves is sandbox-only. |
| `setnature <owned-ID> <nature>` | GAME MASTER | No | Store one of the 25 specimen natures. Existing battles retain Vortex balance; test snapshots expose calculated nature stats. |
| `setability <owned-ID> <ability>` | GAME MASTER | No | Set a legal species ability from the recovered ability catalog. Ability remains specimen metadata as in this baseline. |
| `setivs <owned-ID> <HP> <ATK> <DEF> <SPA> <SPD> <SPE>` | GAME MASTER | No | Set six IVs (0-31). Wild HP already uses HP IV; other values remain specimen metadata and sandbox stat inputs. |
| `setevs <owned-ID> <HP> <ATK> <DEF> <SPA> <SPD> <SPE>` | GAME MASTER | No | Set six EVs (0-252, total ≤510), displayed in owned Pokédex and used for sandbox stats. Live balance is unchanged. |
| `setshiny <owned-ID> <true\|false>` | GAME MASTER | No | Convert normal↔Shiny catalog variant; refuses to erase Dark/Metallic/Mystic/Shadow variants. |
| `setgender <owned-ID> <male\|female\|genderless>` | GAME MASTER | No | Set specimen gender in both canonical and stats rows; applies to gender-gated evolution. |
| `setnickname <owned-ID> <nickname\|none>` | GAME MASTER | No | Set a plain-text nickname (40 characters maximum), visible in owned Pokédex. Species identity and sprites stay intact. |
| `clonepokemon <owned-ID> [recipient]` | DEVELOPER* | No | Duplicate a specimen with a new unique ID, preserving IVs/EVs/metadata. This permanently creates a real collection Pokémon. |
| `pokemoninfo <owned-ID>` | PLAYER | No | Inspect species, owner, moves, metadata, IVs/EVs and derived sandbox stats. |
| `giveitem <player> <item> <amount>` | GAME MASTER | No | Adjust a real inventory column (0-1,000,000 cap); exact catalog labels or column keys accepted. |
| `removeitem <player> <item> <amount>` | GAME MASTER | No | Adjust a real inventory column (0-1,000,000 cap); exact catalog labels or column keys accepted. |
| `setitem <player> <item> <amount>` | GAME MASTER | No | Adjust a real inventory column (0-1,000,000 cap); exact catalog labels or column keys accepted. |
| `iteminfo [item]` | PLAYER | No | Inspect an item or list all supported inventory keys. |
| `clearinventory <player>` | ADMIN | Yes | Set all item quantities to zero; irreversible without a backup. |
| `money [player]` | PLAYER | No | Show real currency balance; omit player to use select. |
| `givemoney <player> <amount>` | GAME MASTER | No | Adjust currency transactionally; maximum balance 2,000,000,000, no negative balances. |
| `removemoney <player> <amount>` | GAME MASTER | No | Adjust currency transactionally; maximum balance 2,000,000,000, no negative balances. |
| `setmoney <player> <amount>` | ADMIN | No | Adjust currency transactionally; maximum balance 2,000,000,000, no negative balances. |
| `economy` | ADMIN | No | Show player count, currency supply/min/max/average and item supply. |
| `battle <opponent> [player] \| battle status \| battle attack <move-slot:1-4> [side:1\|2]` | GAME MASTER* | No | Start/inspect/advance a persisted console-only test duel using real team snapshots and Vortex move/type data. Omitted player uses select. No rewards, rank changes, or browser battle. |
| `win` | DEVELOPER* | No | Complete the current console test duel as a victory for side 1. No live rewards or results. |
| `lose` | DEVELOPER* | No | Complete the current console test duel as a loss for side 1. No live rewards or results. |
| `sethp <owned-ID> <HP>` | DEVELOPER* | No | Set a fighter’s HP only in the current active console test duel (0 through max HP). |
| `setstatus <owned-ID> <none\|poison\|burn\|sleep\|frozen\|paralyzed>` | DEVELOPER* | No | Set status only in the current console test duel; attack simulation consumes status effects. |
| `addmove <owned-ID> <move> [slot:1-4]` | DEVELOPER* | No | Teach a move only to the current test-duel snapshot; real collection remains unchanged. Use learn for permanent changes. |
| `clearmoves <owned-ID>` | DEVELOPER* | No | Clear only a test-duel fighter’s moves; fallback Struggle remains available to simulate combat. |

### World events and cosmetics

| Syntax | Local rank | Confirm | Effect |
| --- | --- | --- | --- |
| `location [player]` | PLAYER | No | Show selected or named player world and exact position. |
| `where [player]` | MODERATOR | No | Show authoritative map position. |
| `locations [world] [search]` | GAME MASTER | No | List valid teleport world:map locations; quote search text. |
| `unstuck [player]` | PLAYER | No | Move to a collision-checked safe entrance in the current area. |
| `teleport <world:area\|vortex:1> [x y]` | GAME MASTER | No | Teleport selected player; coordinates must be walkable. |
| `goto <player>` | GAME MASTER | No | Teleport selected player to another trainer. |
| `bring <player>` | GAME MASTER | No | Bring a player to the selected trainer. |
| `teleportplayer <player> <world:area> [x y]` | GAME MASTER | No | Teleport a named player to a validated destination. |
| `setlocation <player> <world:area> [x y]` | ADMIN | No | Set persistent position with authoritative session synchronization. |
| `spawn <species\|#speciesID> [level] [minutes]` | GAME MASTER | No | Queue one catchable wild encounter for the selected player in their current map. |
| `despawn <spawnID>` | GAME MASTER | No | Remove an unstarted admin encounter. |
| `weather [clear\|rain\|sun\|snow\|fog\|storm]` | GAME MASTER | No | Read/set live map atmosphere; no combat damage or species changes. |
| `clearweather` | GAME MASTER | No | Clear the global map atmosphere override. |
| `day [auto]` | GAME MASTER | No | Force daytime; auto restores each trainer map-time preference. |
| `night` | GAME MASTER | No | Force nighttime and Vortex nighttime wild encounter tables. |
| `event <list\|info\|create\|start\|stop\|join\|leave\|reward> [...]` | PLAYER | No | Manage Event Center events; quote names containing spaces. |
| `sit` | PLAYER | No | Toggle selected trainer sitting pose on multiplayer maps. |
| `dance` | PLAYER | No | Toggle selected trainer dance animation on multiplayer maps. |
| `title <unlocked title\|none>` | PLAYER | No | Equip an unlocked map title for the selected trainer. |
| `titles [player]` | PLAYER | No | List unlocked titles and the currently equipped title. |
| `settitle <player> <title\|none>` | GAME MASTER | No | Grant and equip a persistent title; none unequips it. |

### Trading and social state

| Syntax | Local rank | Confirm | Effect |
| --- | --- | --- | --- |
| `trade <target> [listing-pokemon-id offered-pokemon-id ...]` | PLAYER | No | For the selected trainer, inspect a target's listings or place a real escrow offer of up to six boxed Pokémon. |
| `tradecancel [offer-id\|all]` | PLAYER | No | Withdraw selected trainer's outgoing offers and return their escrowed Pokémon; defaults to all. |
| `tradehistory [player]` | MODERATOR | No | Read the latest 50 real trade offers involving a trainer (defaults to selected). |
| `spectate [player]` | MODERATOR | No | Read a current authoritative Live PvP snapshot without controlling the battle. |
| `blocktrade [target [on\|off]]` | MODERATOR | No | List or change the selected trainer's trade blocks; blocks are enforced in browser offer creation and acceptance. |
| `friends [player]` | PLAYER | No | List real friendships and pending friend requests for a trainer (defaults to selected). |
| `friend <target> [add\|accept\|decline\|remove]` | PLAYER | No | Manage the selected trainer's real friend-request lifecycle; add accepts an incoming request or sends one. |

### Aliases

| Alias | Command |
| --- | --- |
| `quit` | `exit` |
| `gp` | `givepokemon` |
| `gi` | `giveitem` |
| `balance` | `money` |
| `tp` | `teleport` |
| `setweather` | `weather` |

### Event subcommands

PLAYER: event list | event info <key> | event join [key] | event leave [key]. Join/leave act on the selected trainer.

GAME MASTER: event create <name> [summary] | event start <key> | event stop [key].

GAME MASTER: event reward <player> money <amount> | event reward <player> item <item> <amount> | event reward <player> pokemon <species> [level].

Rewards require enrollment in the active event. Grants and receipts commit atomically; repeating a reward intentionally grants another.

Built-in challenges retain their existing reward mechanics. Custom events are staff-hosted challenges displayed in the Event Center. Console join grants access without spending a ticket.

## Alternate launch modes

```
PokemonVortex_ServerConsole.cmd --help
PokemonVortex_ServerConsole.cmd --gameplay-only
PokemonVortex_ServerConsole.cmd --tail-only
PokemonVortex_ServerConsole.cmd --command "serverinfo"
php server_console.php --command "help givepokemon" --no-color
```

`--tail-only` retains the original passive log viewer. `--gameplay-only` hides request traffic. `--apache-access` explicitly adds the existing Apache access-log stream. One-shot commands return a nonzero exit code on error. Use the interactive console for confirmation flows; separate one-shot processes do not share a pending token. Redirected input is processed line by line and terminates at EOF without an endless log tail. Linux/macOS can use `sh PokemonVortex_ServerConsole.sh` or `php server_console.php`; native Unix editing uses `stty` when available.

## Excluded communication commands

There are no `say`, `shout`, `global`, `whisper`, `w`, `reply`, `r`, `ignore`, `unignore`, `mute`, `unmute`, `announce`, `broadcast`, `event announce`, or text `emote` commands. `friends` and `friend` manage the existing friend relationships without adding chat.

## Recovery and verification

If a command cannot connect, check the running XAMPP MySQL service and the existing `db` configuration. If migration fails, repair through local `_setup.php`; do not reimport the base SQL over an existing player database. Failed validation rolls back the applicable transaction. If a process terminates during a command or reports a final audit failure, inspect the resulting state before repeating a grant.

`help` works without a database connection. With developer commands explicitly enabled, `test` runs read-only installation/registry/storage checks. The guarded regression suite under `PokemonVortex/tools/test_admin_console.php` is for a disposable test database only and requires both its environment flag and explicit command-line acknowledgement. Do not point that integration suite at a live database.

See `docs/server-console/VALIDATION.md` for the performed checks and platform limits.
