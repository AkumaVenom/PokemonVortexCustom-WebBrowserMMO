# Pokémon Vortex Custom

### Modern Browser Pokémon MMO Reconstruction

> A modernized, expanded browser-based Pokémon MMORPG built from recovered historical Pokémon Vortex source material and rebuilt for current PHP, Apache, MySQL/MariaDB and modern desktop browsers.

**Runtime:** PHP 8.x · Apache 2.4+ · MySQL / MariaDB  
**License:** MIT for project-owned code — see [LICENSE](LICENSE)

---

# Welcome to Pokémon Vortex Custom

**Pokémon Vortex Custom** is a playable browser MMO reconstruction focused on preserving the spirit of the recovered game while rebuilding its systems to modern standards.

Explore multiple regions, hunt and capture Pokémon, build teams, challenge trainers, clear large progression campaigns, trade with other players, compete in Live PvP, climb a shared competitive ladder and fight against a persistent population of autonomous AI trainers that continue developing even when you are not watching them.

The project is designed to feel like a complete game rather than a historical code archive. Recovered content is preserved where possible, while broken, unsafe or incomplete systems are replaced with reliable modern implementations.

### Core goals

- Preserve recovered Pokémon, sprites, maps, encounters and gameplay identity.
- Keep player accounts, Pokémon, inventory and progression persistent.
- Support current PHP 8 environments.
- Use server-authoritative validation for important gameplay actions.
- Protect battle rewards, trading, item use and progression against duplicate or forged requests.
- Maintain responsive, animated, game-focused presentation across the site.
- Keep accepted gameplay systems protected through repeatable regression testing.
- Continue expanding the project without casually rewriting systems that already work.

The result is an actively developed browser Pokémon MMO with a modernized backend, persistent multiplayer systems and a much more polished player-facing experience.

---

# Game Highlights

- **Three explorable world groups** with persistent trainer position and multiplayer presence.
- **Wild Pokémon encounters and capture** with Poké Balls, EXP, happiness and persistent collection storage.
- **Traditional Trainer Battles** against another trainer's AI-controlled team.
- **Live PvP** with synchronized turns, switching, items, reconnect-safe state and shared results.
- **Rival Network** with ranked opponents, elite rivals, active competitors, retaliation and protection mechanics.
- **Trainer Rankings** shared between human players and autonomous trainers.
- **Persistent autonomous AI population** that battles, develops and actively tries to climb the competitive ladder.
- **AI Activity feed** showing autonomous trainer operations across the world.
- **Battle Arena**, League, Elite Four, Champion and facility progression.
- **Special Event battles** against major organizations and staff teams.
- **Large Sidequest campaign** with regional milestones and persistent progression.
- **Pokédex**, Evolution Lab, Move Lab and Fossil Lab.
- **PokéMart, trading, clans, profiles, messaging and community systems.**
- **Animated Pokémon-themed UI** with interactive panels, hover motion, sprite decoration, Poké Ball imagery and site-wide visual effects.
- **Responsive containment protections** to prevent oversized UI elements or artwork from breaking normal pages.
- **Native map presentation preserved** for large regional maps inside their intended scrolling exploration viewports.

---

# Screenshots

<img width="3787" height="1850" alt="1" src="https://github.com/user-attachments/assets/67f1482e-562a-42bb-bad0-bb8e8fb1c0fd" />

<img width="3786" height="1840" alt="2" src="https://github.com/user-attachments/assets/a6363549-03a2-4e99-bc4a-4aed72e46ff6" />

<img width="3789" height="1853" alt="3" src="https://github.com/user-attachments/assets/7e928f10-3f80-4e23-af7e-3af981543e89" />

<img width="3791" height="1851" alt="4" src="https://github.com/user-attachments/assets/e24fd72f-77e7-434d-8cbe-56021ea53b82" />

<img width="3791" height="1851" alt="5" src="https://github.com/user-attachments/assets/3af3d95c-8f97-4756-9070-d7b3683f8bea" />

---

# Trainer Accounts & Persistent Progress

Trainer accounts are designed around long-term persistent play.

Supported account data includes:

- registration and login;
- password recovery;
- starter Pokémon creation;
- trainer level and progression;
- persistent money and inventory;
- owned Pokémon;
- active team assignment;
- Pokédex progress;
- account options;
- multiplayer presence;
- world position;
- competitive ranking data;
- and persistent gameplay state across sessions.

Important account and progression operations use modern database validation and transaction-backed writes so interrupted or duplicate actions do not leave partial player state behind.

---

# Pokémon Collection

Every owned Pokémon retains persistent gameplay data such as:

- species;
- level and experience;
- four move slots;
- ability;
- nature;
- gender;
- IVs;
- Pokémon variant;
- original trainer;
- capture ball;
- happiness;
- active-team or storage state;
- and related collection metadata.

## Pokémon Variants

The game preserves the six primary Vortex variant families:

- Normal
- Shiny
- Dark
- Mystic
- Metallic
- Shadow

Recovered visual subforms are also retained when matching local artwork is available.

---

# World & Exploration

The game currently includes three major explorable world groups.

## Vortex World

The original Vortex World contains **25 connected maps** across Grass, Cave, Electric, Fire, Ice and Ghost environments.

Exploration includes:

- four-way and diagonal movement;
- complete terrain collision;
- one-way ledges;
- connected map transitions;
- special inter-region portals;
- collision-safe entry points;
- persistent trainer position;
- multiplayer trainer presence;
- wild Pokémon habitats;
- regional encounter identity;
- and direct handoff into Wild Battle.

The collision reconstruction prevents players from walking through solid scenery such as trees, water, cliffs, cave walls, rocks, structures, machinery, lava, ice formations and graveyard scenery while preserving intended roads, grass, bridges, stairs, entrances and height-transition paths.

## Kanto

Kanto is available as an additional explorable region containing cities, routes, caves and landmarks with its own regional wild Pokémon habitats and persistent multiplayer exploration.

Large regional artwork is displayed at its intended natural presentation inside the exploration viewport instead of being destructively stretched to fit ordinary UI panels.

## Hoenn

Hoenn is also available as a full additional world with towns, routes, caves, seas and regional Pokémon habitats.

Its map presentation, collision, encounters and multiplayer state are kept independent from the original Vortex World.

---

# Multiplayer Map Presence

Trainers exploring the same supported area can see one another on the map.

Presence is separated by world and area so players only appear where they are actually exploring.

**Show Players on Map** is enabled by default.

---

# Wild Pokémon & Capture

Wild Pokémon can appear while exploring supported habitats.

Wild battles support:

- Fight;
- Pokémon switching;
- automatic replacement after fainting;
- battle items;
- Poké Balls;
- Run;
- Victory;
- Defeat;
- Capture;
- EXP gain;
- happiness progression;
- money and trainer progression;
- persistent battle results;
- and duplicate-result protection.

Supported capture balls include:

- Poké Ball
- Great Ball
- Ultra Ball
- Master Ball

Captured Pokémon are written directly into the trainer's persistent collection with their generated level, IVs, nature, gender, ability, form and capture information.

---

# Battle Systems

## Standard Trainer Battles

Players can challenge another trainer account through the traditional Trainer Battle system.

The opponent's saved team is controlled by the battle engine, allowing asynchronous trainer-vs-team battles without requiring the other trainer to be online.

The battle runtime preserves:

- Pokémon team order;
- move selection;
- switching;
- healing and status items;
- victory and defeat;
- EXP and level progression;
- happiness progression;
- money rewards;
- clan win/loss integration where applicable;
- animated battle presentation;
- battle effects;
- and persistent result settlement.

---

# Rival Network

The **Rival Network** adds a persistent competitive layer on top of the traditional trainer-battle experience.

Instead of only choosing arbitrary opponents, trainers can enter a dedicated competitive hub that surfaces meaningful targets around their current level of competition.

The Rival Network can present:

- recommended rivals;
- stronger **Elite Rivals**;
- recently active competitors;
- incoming attackers;
- active protection status;
- retaliation targets;
- recent ranked battle history;
- ranking position;
- and competitive rating information.

Ranked Rival battles continue into the existing animated Pokémon battle flow rather than replacing it with a simplified simulator.

## Protection & Retaliation

After a ranked attack, the defending trainer receives temporary protection from normal repeat attacks.

This system is designed to reduce dog-piling while still keeping competition active.

Key rules include:

- protected trainers cannot normally be challenged again during the protection window;
- being attacked can generate a retaliation opportunity against the attacker;
- retaliation can bypass the attacker's protection where allowed;
- launching your own normal Rival challenge removes your own protection;
- using retaliation also returns you to the active competitive pool;
- battle launch revalidates protection server-side instead of trusting stale browser state;
- and retaliation opportunities expire automatically.

The result is a competitive loop that rewards activity without letting one trainer be endlessly attacked while offline.

---

# Trainer Rankings

Human players and autonomous trainers climb the same competitive ladder.

Ranking is based on a dedicated competitive rating rather than repurposing normal collection or progression statistics.

Tracked competitive data includes:

- current rating;
- peak rating;
- wins;
- losses;
- current streak;
- ranking tier;
- recent activity;
- and ranked battle history.

This makes the ladder useful both as a public leaderboard and as an active matchmaking signal for the Rival Network.

---

# Autonomous Trainers

The world contains a persistent population of **2,000 autonomous trainers**.

These AI trainers are more than static battle targets. They participate in the competitive ecosystem and continue operating independently.

Autonomous trainers can:

- challenge eligible opponents;
- preferentially seek meaningful competition;
- battle human trainers where appropriate;
- battle other autonomous trainers;
- gain and lose competitive rating;
- climb or fall through ranking tiers;
- build win/loss histories;
- respect attack protection;
- avoid excessive repeated attacks;
- and continue progressing through elapsed-time catch-up when the site has not been actively watched.

A small number can develop into especially dangerous high-ranked rivals over time.

---

# AI Activity

The **AI Activity** page exposes the living autonomous side of the game world.

It provides a readable activity feed showing autonomous trainer operations such as:

- ranked battles;
- wins and losses;
- competitive movement;
- recovery and development activity;
- active rival status;
- and other persistent AI behavior.

This gives players visibility into what the autonomous trainer population has been doing instead of hiding all simulation behind the scenes.

---

# Live PvP

Live PvP provides synchronized battles between two online trainers.

The Live Battle system supports:

- challenge and acceptance;
- synchronized battle state;
- trainer-owned teams;
- move selection;
- switching;
- items;
- forced replacement after fainting;
- turn synchronization;
- shared battle results;
- win/loss settlement;
- reconnect and refresh-safe match state;
- animated battle presentation;
- and existing combat effects.

Live PvP remains independent from asynchronous Trainer Battles and the autonomous Rival Network.

---

# Battle Arena

The Battle Arena contains the reconstructed League, Elite Four, Champion, Frontier and facility progression network.

The catalogue includes:

- **95 progression opponents**;
- **402 deterministic NPC Pokémon**;
- persistent progression through the full opponent chain;
- Gym battles;
- League battles;
- Elite Four battles;
- Champion battles;
- facility battles;
- complete team previews;
- and replayable completed encounters where supported.

---

# Special Events

The Event Center contains the reconstructed historical Special Event battle network.

Current content includes:

- **32 event opponents**;
- **181 deterministic event NPC Pokémon**;
- Team Rocket;
- Team Aqua;
- Team Magma;
- Team Galactic;
- Team Plasma;
- Team Flare;
- Vortex Staff;
- and seven organization event badges.

Event progression and rewards persist through the modern battle and progression systems.

---

# Sidequests

The reconstructed Sidequest campaign contains:

- **690 battle positions**;
- six regional reward milestones;
- Kanto;
- Johto;
- Sevii;
- Navel Rock / Birth Island;
- TCG;
- Orange;
- Hoenn content;
- and **3,091 deterministic NPC Pokémon**.

Progress is persistent and sequential, with milestone rewards protected against repeated or duplicate submission.

---

# Pokédex

The Pokédex combines collection tracking with detailed species information.

Selected entries can display:

- Pokédex number;
- species and variant;
- type information;
- abilities;
- default moves;
- evolution paths;
- six-variant family presentation;
- owned copies;
- owned-Pokémon details;
- previous/next navigation;
- and local sprite fallbacks for recovered visual forms.

Trainer-specific information is ownership-aware and read-only where appropriate.

---

# Pokémon Labs

## Evolution Lab

Eligible Pokémon can evolve through the Evolution Lab.

The system supports:

- evolution requirements;
- ownership validation;
- required-item consumption;
- variant-aware evolution;
- optional evolved moveset adoption;
- ability updates;
- persistent species and form updates;
- and transaction-safe completion.

When **Adopt evolved moveset** is enabled, duplicate or blank evolved move slots are repaired using unique moves already known by the Pokémon when possible.

## Move Lab

The Move Lab allows supported owned Pokémon to manage their move sets through the reconstructed move catalogue.

## Fossil Lab

The Fossil Lab restores supported fossil Pokémon from inventory items and creates the restored Pokémon directly in the trainer's collection.

---

# Trading, Economy & Community

The wider MMO includes:

- PokéMart and item purchasing;
- persistent trainer inventory;
- Pokémon trade listings;
- trade offers;
- protected Pokémon reservation while an offer is active;
- trainer profiles;
- clans;
- private messages;
- trainer listings;
- account options;
- and community pages.

Gameplay mutations such as purchases, trades, rewards and item consumption are protected by modern server-side validation and persistence handling.

---

# Pokémon-Styled User Interface

The frontend has been rebuilt around a much more game-focused Pokémon presentation rather than a generic technical dashboard.

The visual system includes:

- Pokémon-inspired blue, yellow, red and green accents;
- bright cream and white content panels;
- banner-style section headers;
- Pokémon artwork throughout major pages;
- Poké Ball, Great Ball and Ultra Ball iconography;
- animated Pokémon sprites;
- interactive card motion;
- raised hover states;
- glow and highlight effects;
- animated entrances;
- layered visual depth;
- responsive layouts;
- a continuously animated scanning-light effect across the site;
- polished battle HUDs;
- image-based navigation where appropriate;
- and gamer-facing copy instead of developer-facing implementation text.

The Rival Network, Rankings and AI Activity pages follow the same visual language as the rest of the site so competitive play feels like a natural part of the game.

## Responsive & Image Containment

Normal pages include additional layout containment to prevent oversized artwork, panels or flex/grid children from unexpectedly expanding the full interface.

This protection is intentionally not applied destructively to world maps that rely on their native-size presentation inside scrollable map viewports.

---

# Technical Architecture

The project has been modernized around a current PHP/MySQL runtime while still preserving selected historical compatibility code where necessary.

Important architecture principles include:

- PHP 8 compatibility;
- prepared `mysqli` access on modern runtime paths;
- server-side authority for important gameplay actions;
- persistent relational storage for accounts, Pokémon, battles and progression;
- transaction-backed mutations where partial writes would be dangerous;
- ownership validation for Pokémon and inventory actions;
- protected battle settlement;
- duplicate-submit protection;
- CSRF protection;
- single-use action tokens where appropriate;
- safe item consumption;
- same-origin restrictions where applicable;
- HTTP isolation for retained helper/source-only PHP surfaces;
- production-safe player error messages;
- and deterministic release gates.

Historical compatibility code remains only where it is still required for retained gameplay or source preservation. New gameplay work should use the modern runtime and service patterns.

---

# Production Hardening

Important protected areas include:

- account persistence;
- trainer ownership checks;
- Pokémon ownership checks;
- capture settlement;
- battle rewards;
- ranked battle settlement;
- retaliation validation;
- protection-state validation;
- Live PvP synchronization;
- inventory mutation;
- evolution;
- trading;
- progression rewards;
- database upgrades;
- duplicate request handling;
- and direct access to retained internal helper surfaces.

Where a gameplay action changes important persistent state, the server is expected to be the final authority.

---

# Requirements

A normal local installation can be run with **XAMPP**.

| Component | Recommended |
| --- | --- |
| Web server | Apache 2.4+ |
| PHP | PHP 8.2+ |
| Database | MariaDB 10.4+ or MySQL 8+ |
| PHP database extension | `mysqli` |
| Browser | Current Firefox, Chromium or Edge |
| Local development package | Current XAMPP release or equivalent |

---

# Quick Local Installation with XAMPP

This is the simplest way to run the project locally on Windows.

## 1. Install XAMPP

Install a current XAMPP release with:

- Apache;
- PHP;
- MariaDB/MySQL;
- and optionally phpMyAdmin.

A normal installation is commonly located at:

```text
C:\xampp
```

## 2. Copy the Game into `htdocs`

Locate the project package's:

```text
public_html
```

Create a local folder such as:

```text
C:\xampp\htdocs\PokemonVortex
```

Copy the **contents of `public_html`** into that folder.

The result should look similar to:

```text
C:\xampp\htdocs\PokemonVortex\
    assets\
    config\
    includes\
    sql\
    index.php
    _setup.php
    ...
```

Do not place the `public_html` directory itself one level too deep.

`index.php` and `_setup.php` should be directly inside the `PokemonVortex` web folder.

## 3. Start Apache and MySQL

Open the **XAMPP Control Panel** and start:

```text
Apache
MySQL
```

Both services should be running before continuing.

## 4. Review Database Configuration

Open:

```text
config/app.php
```

Confirm the local database host, user, password and database name match the MySQL/MariaDB account used by your XAMPP environment.

The project supports running beside other local browser-game projects as long as the configured MySQL credentials are compatible.

> Never expose a local development database configuration directly to the public Internet. Use dedicated production credentials and server hardening for any real deployment.

## 5. Open the Setup Utility

With Apache and MySQL running, browse to:

```text
http://localhost/PokemonVortex/_setup.php
```

The setup utility supports both brand-new databases and maintenance of an existing installation.

### Brand-New Installation

For a completely new installation with no progress to preserve:

1. Choose **Fresh Rebuild**.
2. Enter the confirmation text requested by the setup page.
3. Start the database build.
4. Wait for the success result before opening the game.

**Fresh Rebuild deletes existing Pokémon Vortex accounts and game progress.**

Use it only for a new database or when you intentionally want a complete reset.

### Updating or Repairing an Existing Installation

For an existing database:

1. Back up the database first.
2. Open `_setup.php`.
3. Choose **Upgrade / Repair**.
4. Enter the confirmation text requested by the setup page.
5. Run the upgrade.

Upgrade / Repair is designed to add or repair required database structures without intentionally deleting normal trainer progression.

## 6. Launch the Game

Open:

```text
http://localhost/PokemonVortex/
```

Create a trainer account, sign in and begin playing.

---

# Updating an Existing Local Installation

Before replacing an older project copy:

1. Stop using the site while files are being replaced.
2. Back up the current Pokémon Vortex folder.
3. Back up the MySQL/MariaDB database.
4. Replace the web files with the updated project files.
5. Review the README, CHANGELOG and testing notes included with the package.
6. Run `_setup.php` using **Upgrade / Repair** when the updated package includes database changes.
7. Hard-refresh the browser after updating so current CSS and JavaScript assets are loaded.

Never use **Fresh Rebuild** on a database containing progress you want to keep.

---

# Common XAMPP Troubleshooting

## Apache Will Not Start

Another application may already be using Apache's configured HTTP or HTTPS ports.

Check the XAMPP Control Panel logs before changing project files.

## MySQL Will Not Start

Check the MySQL log and confirm another MySQL/MariaDB service is not already using the same port.

## Database Connection Error

Verify:

```text
config/app.php
```

and confirm MySQL/MariaDB is running.

If you run multiple local browser-game projects side by side, make sure they are all using the database credentials you intended.

## `_setup.php` Does Not Open

Confirm:

- Apache is running;
- the project is inside `C:\xampp\htdocs`;
- the browser URL matches the folder name;
- and the setup utility is being opened locally through `localhost`.

## Page Styling Looks Old After an Update

Perform a hard refresh after replacing project files.

Browser-cached CSS or JavaScript can make an updated installation look like an older build.

## UI or Images Appear Oversized

First perform a hard refresh.

Normal interface artwork is constrained by the responsive layout system, while large world maps intentionally retain their own map-viewport behavior.

If a normal page still overflows after a hard refresh, include the affected page, browser and a screenshot in the bug report.

---

# Release Validation

The project uses deterministic release gates to protect accepted gameplay from regression.

Validation covers areas including:

- Vortex World collision definitions;
- map connections and traversal;
- wild encounters;
- Kanto and Hoenn rendering;
- responsive UI containment;
- Pokémon artwork and visual assets;
- Evolution Lab behavior;
- standard Trainer Battles;
- Rival Network battles;
- trainer rankings;
- shield and retaliation logic;
- autonomous trainer activity;
- Live PvP;
- Pokédex behavior;
- player-facing copy;
- helper-surface isolation;
- PHP syntax;
- JavaScript parsing;
- CSS structure;
- and complete package integrity.

Real XAMPP/browser testing remains the final acceptance step for runtime-sensitive changes.

---

# Development Standard

Changes should continue to prioritize:

1. preservation of accepted runtime behavior;
2. PHP 8 compatibility;
3. server-side authority for important gameplay actions;
4. database integrity;
5. safe ownership and input validation;
6. root-cause fixes instead of temporary workarounds;
7. local asset reliability;
8. consistent Pokémon-focused UI/UX;
9. responsive presentation;
10. preservation of battle animation and effects;
11. deterministic regression testing;
12. and clear README, changelog and testing documentation.

Large rewrites of already accepted systems should be avoided unless there is a clear correctness, security, gameplay or maintainability benefit.

---

# Issues & Bug Reports

A useful bug report should include:

- PHP version;
- MariaDB/MySQL version;
- operating system;
- browser;
- affected page or gameplay system;
- expected behavior;
- actual behavior;
- reproduction steps;
- relevant Apache/PHP errors;
- and screenshots where useful.

Do **not** post passwords, database credentials, session cookies, reset tokens or other sensitive information in public bug reports.

---

# Project Direction

The major reconstruction and modernization work has established a broad playable MMO foundation.

Future development can focus on:

- gameplay expansion;
- new competitive systems;
- AI behavior;
- balancing;
- additional world content;
- visual polish;
- browser compatibility;
- performance;
- security maintenance;
- newly discovered bugs;
- and continued quality improvements.

Accepted core systems should remain stable unless a verified issue requires a focused change.

---

# License

The repository contains an **MIT License** covering code owned and released by the project author.

See:

```text
LICENSE
```

for the complete license terms.

## Third-Party Intellectual Property

Pokémon names, characters, imagery, game concepts, trademarks and other Pokémon-related intellectual property belong to their respective rights holders.

The MIT license supplied with this repository does **not** grant ownership of, or additional rights to, third-party Pokémon intellectual property or recovered third-party assets.

This project is an independent fan-made restoration/reconstruction project and should not be interpreted as an official Pokémon product or as being endorsed by the relevant rights holders.

---

# Disclaimer

This software is supplied for development, preservation, educational and research purposes.

It is provided **as-is**, without warranty.

Anyone hosting or distributing a modified instance is responsible for reviewing:

- applicable intellectual-property requirements;
- security configuration;
- privacy obligations;
- database protection;
- local laws;
- and third-party asset licensing.

---
