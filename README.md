# Pokémon Vortex Custom

### Modernized Web Browser MMO / Web Server Reconstruction

> A modernized reconstruction of a recovered Pokémon Vortex browser MMORPG codebase, rebuilt for modern PHP, Apache, MySQL/MariaDB and current desktop web browsers while preserving the character and gameplay of the recovered project.

**Current Project Version:** `v23.5.1 — Production Vortex Collision Traversal Correction`  
**Current Release Status:** XAMPP test candidate  
**Accepted Stable Parent:** `v23.4.1 — Production World Map Copy Polish`  
**Database Schema:** `26`  
**Runtime:** PHP 8.x · Apache 2.4+ · MySQL / MariaDB  
**License:** MIT for project-owned code — see [LICENSE](LICENSE)

---

## Overview

**Pokémon Vortex Custom** is a restoration, modernization and continued-development project built from recovered historical Pokémon Vortex browser RPG source material.

The project has been reconstructed into a coherent browser-based Pokémon MMO with modern account handling, persistent Pokémon ownership, exploration, wild encounters, trainer battles, Battle Arena progression, Special Events, Sidequests, Live PvP, trading, Pokémon Labs, Pokédex features, multiplayer map presence and a unified modern interface.

Development has focused on preserving the identity of the recovered game while replacing broken or unsafe historical behavior with reliable modern implementations.

Major goals of the reconstruction include:

* restoring damaged or incomplete gameplay systems;
* supporting current PHP 8 environments;
* preserving recovered Pokémon, maps, sprites and game content;
* providing reliable persistent accounts and progression;
* protecting important actions against forged or duplicate requests;
* improving battle, trading, reward and inventory integrity;
* rebuilding Kanto and Hoenn as additional explorable regions;
* preserving the original Vortex World and its encounter identity;
* providing real-time multiplayer presence and Live PvP;
* presenting the game through a consistent dark navy / cyan / gold interface;
* keeping normal player-facing text focused on gameplay rather than implementation details;
* and maintaining deterministic release gates for systems that have already been accepted.

The result is a modernized playable reconstruction rather than a bit-for-bit copy of any historical live Pokémon Vortex server.

<img width="3788" height="1842" alt="1" src="https://github.com/user-attachments/assets/53c32e2d-3961-4990-afb3-481ae0e811eb" />
<img width="3795" height="1843" alt="2" src="https://github.com/user-attachments/assets/2b98eb04-d7a5-4169-9fc1-586cd5b02e2d" />
<img width="3797" height="1843" alt="3" src="https://github.com/user-attachments/assets/a4bf51b8-467f-4cf2-a2b6-98d4dc2e2230" />
<img width="3785" height="1846" alt="4" src="https://github.com/user-attachments/assets/069e7f4c-eb3e-4add-8535-5a8787c56f4c" />
<img width="3789" height="1851" alt="5" src="https://github.com/user-attachments/assets/4a7c8c09-2be9-40ec-81e1-88927fd54ab8" />

---

# Major Game Systems

## Trainer Accounts & Persistence

The account system supports:

* trainer registration and login;
* password recovery;
* starter Pokémon creation;
* trainer statistics and progression;
* persistent inventory;
* persistent Pokémon ownership;
* active team assignment;
* Pokédex progress;
* account options;
* online presence;
* and persistent gameplay state across sessions.

Important account and progression operations use modern database validation and transactional handling so partial or duplicate writes do not leave inconsistent trainer data.

---

## Pokémon Collection

Owned Pokémon retain persistent information including:

* species;
* level and experience;
* four move slots;
* ability;
* nature;
* gender;
* IVs;
* Pokémon variant;
* original trainer;
* capture ball;
* happiness;
* active-team or storage state;
* and related collection metadata.

### Pokémon Variants

The reconstructed game preserves the six primary Vortex variant families:

* Normal
* Shiny
* Dark
* Mystic
* Metallic
* Shadow

Recovered visual subforms are also retained where corresponding local artwork is available.

---

# World & Exploration

The project currently contains three explorable world groups.

## Vortex World

The original Vortex World contains **25 connected maps** across its Grass, Cave, Electric, Fire, Ice and Ghost environments.

Current Vortex World functionality includes:

* four-way and diagonal map movement;
* complete terrain collision across all 25 maps;
* one-way ledges;
* connected map transitions;
* special inter-region portals;
* collision-safe entry points;
* persistent trainer position;
* multiplayer trainer presence;
* wild Pokémon habitats;
* regional encounter identity;
* and direct Wild Battle handoff.

The corrected v23.5.1 collision reconstruction prevents movement through solid scenery such as trees, water, cliffs, cave walls, rocks, structures, machinery, lava, ice formations and graveyard scenery while explicitly preserving intended roads, grass, bridges, stairs, entrances, floors and height-transition paths.

## Kanto

Kanto is available as a complete additional world containing cities, routes, caves and landmarks with its own regional wild Pokémon habitats and persistent multiplayer exploration.

Kanto uses the established fixed natural-size map presentation inside the game's exploration viewport and retains the accepted regional collision and encounter behavior.

## Hoenn

Hoenn is also available as a complete additional world with towns, routes, caves, seas and regional Pokémon habitats.

Its accepted map presentation, collision, encounters and multiplayer behavior are preserved independently from the Vortex World collision reconstruction.

---

## Multiplayer Map Presence

Logged-in trainers exploring the same supported world area can see one another on the map.

Presence is kept separate by world and area so trainers are displayed only where they are actually exploring.

The **Show Players on Map** option defaults to enabled.

---

# Wild Pokémon & Capture

Wild Pokémon can appear while exploring supported habitats.

The current Wild Battle system supports:

* Fight;
* Pokémon switching;
* automatic replacement after fainting;
* battle items;
* Poké Balls;
* Run;
* Victory;
* Defeat;
* Capture;
* EXP and happiness progression;
* money and trainer progression;
* persistent battle results;
* and duplicate-result protection.

Supported capture balls include:

* Poké Ball
* Great Ball
* Ultra Ball
* Master Ball

Captured Pokémon are added directly to the trainer's persistent collection with their generated level, IVs, nature, gender, ability, form and capture information.

---

# Battle Systems

## AI-Controlled Trainer Battles

Trainers can challenge another player account through the standard Trainer Battle system.

The challenged trainer's team is controlled by the battle engine, allowing traditional asynchronous trainer-vs-team battles without requiring the other player to be online.

The reconstructed standard battle runtime preserves:

* Pokémon team order;
* move selection;
* switching;
* healing and status items;
* victory and defeat;
* EXP, level and happiness progression;
* money rewards;
* clan win/loss integration where applicable;
* and persistent battle results.

---

## Battle Arena

The Battle Arena contains the reconstructed League, Elite Four, Champion, Frontier and facility progression network.

The current catalogue includes:

* **95 progression opponents**;
* **402 deterministic NPC Pokémon**;
* persistent `g1–g95` progression;
* Gym, League, Elite Four, Champion and facility battles;
* complete team previews;
* and replayable completed battles where supported.

---

## Special Events

The Event Center contains the reconstructed historical Special Event battle network.

Current event content includes:

* **32 event opponents**;
* **181 deterministic event NPC Pokémon**;
* Team Rocket;
* Team Aqua;
* Team Magma;
* Team Galactic;
* Team Plasma;
* Team Flare;
* Vortex Staff;
* and seven organization event badges.

Event progression and rewards are persisted through the modern battle and progression systems.

---

## Sidequests

The complete reconstructed Sidequest campaign contains:

* **690 battle positions**;
* **six regional reward milestones**;
* Kanto;
* Johto;
* Sevii;
* Navel Rock / Birth Island;
* TCG;
* Orange;
* and Hoenn content.

The Sidequest catalogue contains **3,091 deterministic NPC Pokémon** and preserves major recovered landmark encounters and reward positions.

Progress is persistent and sequential, with duplicate milestone rewards protected against repeated submission.

---

## Live PvP

Live PvP provides synchronized two-player battles between online trainers.

The modern Live Battle system supports:

* challenge and acceptance;
* synchronized battle state;
* trainer-owned teams;
* move selection;
* switching;
* items;
* forced replacement after fainting;
* turn synchronization;
* shared battle results;
* win/loss settlement;
* and reconnect/refresh-safe match state.

Live PvP is independent from the AI-controlled Trainer Battle mode.

---

# Pokédex

The reconstructed Pokédex provides both collection tracking and detailed Pokémon information.

Selected entries can display:

* Pokédex number;
* species and variant;
* type information;
* abilities;
* default moves;
* evolution paths;
* six-variant family presentation;
* owned copies;
* owned Pokémon details;
* previous/next navigation;
* and local sprite fallbacks for recovered visual forms.

Pokédex detail access is read-only and ownership-sensitive where trainer-specific information is shown.

---

# Pokémon Labs

## Evolution Lab

Eligible owned Pokémon can be evolved through the Evolution Lab.

The current system supports:

* evolution requirements;
* ownership validation;
* required-item consumption;
* variant-aware evolution;
* optional evolved moveset adoption;
* ability updates;
* persistent species/form updates;
* and transaction-safe completion.

When **Adopt evolved moveset** is enabled, duplicate or blank evolved-species move slots are repaired using unique moves the Pokémon already knows, preventing duplicate attacks such as the historical Cyndaquil → Quilava Smokescreen issue.

## Move Lab

The Move Lab allows supported owned Pokémon to manage their move sets through the reconstructed move catalogue and collection system.

## Fossil Lab

The Fossil Lab restores supported fossil Pokémon from items in the trainer's inventory and creates the restored Pokémon directly in the trainer's collection.

---

# Trading, Economy & Community

The wider MMO includes:

* PokéMart and item purchasing;
* persistent trainer inventory;
* Pokémon trade listings;
* trade offers;
* protected Pokémon reservation while an offer is active;
* trainer profiles;
* clans;
* private messages;
* trainer listings;
* account options;
* and community pages.

Gameplay mutations such as purchases, trades, rewards and item consumption are protected by the modern validation and persistence layers used throughout the project.

---

# Modern User Interface

The reconstructed frontend uses a consistent game-wide visual direction built around:

* deep navy backgrounds;
* cyan highlights;
* gold accents;
* polished information panels;
* clear navigation;
* responsive gameplay layouts;
* modern battle HUDs;
* readable collection and Pokédex views;
* focused trainer-facing messages;
* and game-oriented rather than developer-oriented copy.

The player-facing copy reconstruction completed in the v23.4.x line removed implementation and asset-production terminology from normal gameplay pages while retaining technical detail in development documentation where it belongs.

---

# Production Hardening

The modernization effort includes:

* PHP 8 compatibility;
* prepared `mysqli` access on modern runtime paths;
* CSRF protection;
* server-side ownership and action validation;
* single-use command tokens;
* duplicate-submit protection;
* transaction-backed progression and rewards;
* guarded item consumption;
* safe Pokémon ownership checks;
* persistent battle-result handling;
* HTTP isolation of retained helper/source-only PHP surfaces;
* same-origin restrictions where applicable;
* production-safe player error messages;
* and release-blocking regression gates.

Historical compatibility code that remains in the repository is retained only where it is still needed for compatibility or source preservation. Modern gameplay code should use the current runtime/service architecture.

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

Download and install a current version of **XAMPP**.

The installation needs:

* Apache;
* PHP;
* MariaDB/MySQL;
* and optionally phpMyAdmin.

The normal default installation location is:

```text
C:\xampp
```

## 2. Copy Pokémon Vortex into `htdocs`

Open the project package and locate:

```text
public_html
```

Create a folder such as:

```text
C:\xampp\htdocs\PokemonVortex
```

Copy **the contents of `public_html`** into that folder.

The finished layout should look similar to:

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

Do not place `public_html` itself one level too deep. `index.php` and `_setup.php` should be directly inside the `PokemonVortex` folder.

## 3. Start Apache and MySQL

Open the **XAMPP Control Panel** and start:

```text
Apache
MySQL
```

Both services should show as running before continuing.

## 4. Check the Database Configuration

Open:

```text
config/app.php
```

Make sure the database settings match your local XAMPP installation.

A typical default local XAMPP setup uses:

```text
Host:      localhost
User:      root
Password:  [blank]
Database:  pokemon_vortex
```

If your XAMPP/MySQL installation uses a password or a different database name, enter those values instead.

> A blank MySQL root password is common for a local XAMPP development machine. Do not use that configuration for a public Internet-facing server.

## 5. Open `_setup.php`

With Apache and MySQL running, open this address in your browser:

```text
http://localhost/PokemonVortex/_setup.php
```

`_setup.php` is the local installation and database maintenance page.

### Brand-New Installation

For a completely new installation with no progress to preserve:

1. Choose **Fresh Rebuild**.
2. Type:

```text
INSTALL
```

3. Click **Build Fresh Database**.
4. Wait for the success message.

**Fresh Rebuild deletes existing Pokémon Vortex accounts and game progress.** Use it only for a new installation or when you intentionally want to reset the database.

### Updating an Existing Installation

If a future release specifically requires a database upgrade:

1. Back up the database first.
2. Open `_setup.php`.
3. Choose **Upgrade / Repair**.
4. Type:

```text
UPGRADE
```

5. Click **Upgrade Existing Database**.

Upgrade / Repair adds required compatibility fields and indexes without intentionally deleting trainer progress.

### v23.4.1 → v23.5.1

The current v23.5.1 candidate remains on **schema revision 26**.

If you are updating an existing v23.4.1 installation to v23.5.1:

**Do not run Fresh Rebuild and do not run Upgrade / Repair.**

Replace the web files with the new package while keeping your existing database.

## 6. Launch the Game

Open:

```text
http://localhost/PokemonVortex/
```

Create a trainer account, sign in and begin playing.

---

# Updating an Existing Local Installation

Before replacing an older installation:

1. Stop using the site while files are being replaced.
2. Back up your current Pokémon Vortex folder.
3. Back up the MySQL/MariaDB database.
4. Replace the web files with the new release.
5. Read that release's README, CHANGELOG and testing notes.
6. Run `_setup.php` **only when the release specifically says a database upgrade is required**.
7. Hard-refresh the browser after the update so new CSS/JavaScript assets are loaded.

Never use **Fresh Rebuild** on a database containing progress you want to keep.

---

# Common XAMPP Troubleshooting

## Apache Will Not Start

Another program may already be using Apache's configured HTTP/HTTPS ports.

Check the XAMPP Control Panel logs before changing project files.

## MySQL Will Not Start

Check the MySQL log from the XAMPP Control Panel and make sure another MySQL/MariaDB service is not already using the same port.

## Database Connection Error

Verify the values in:

```text
config/app.php
```

and confirm MySQL is running.

## `_setup.php` Does Not Open

Make sure:

* Apache is running;
* the project is inside `C:\xampp\htdocs`;
* the URL matches the folder name;
* and you are opening the setup utility locally through `localhost`.

## Page Styling Looks Old After an Update

Perform a hard refresh in the browser after replacing the project files.

---

# Release Validation

The project uses deterministic release gates to protect accepted systems from regression.

The current v23.5.1 candidate validates areas including:

* all 25 Vortex collision definitions;
* Vortex encounters;
* Kanto and Hoenn regional rendering;
* Evolution Lab moveset adoption;
* AI-controlled Standard Battle;
* Live PvP;
* Pokédex detail behavior;
* player-facing copy;
* retained helper-surface isolation;
* PHP syntax;
* JavaScript parsing;
* CSS structure;
* accepted-parent file deltas;
* and complete package SHA-256 integrity.

The release package remains on **database schema revision 26**.

---

# Recent Version History

## v23.5.1 — Production Vortex Collision Traversal Correction

* Corrects the rejected v23.5.0 collision candidate after real XAMPP testing found legitimate Grass-region stair/height transitions blocked.
* Keeps complete collision coverage across all 25 original Vortex World maps with **7,434 audited terrain blockers**.
* Reopens exactly 13 confirmed Grass Region I–III staircase/height-transition cells while keeping adjacent cliffs and scenery solid.
* Adds direct staircase crossing and whole-map walkable-component reachability checks to the release gate.
* Preserves all 56 directed map connections, eight special inter-region portals, one-way ledges and collision-safe saved-position recovery.
* Preserves Kanto, Hoenn, Vortex encounters, battles, multiplayer and all accepted gameplay systems.
* Schema remains revision 26.

## v23.5.0 — Rejected Collision Test Candidate

* Not accepted as a stable baseline.
* Real XAMPP testing found that rare Grass-region staircase/height-transition artwork had been classified as solid collision.
* Superseded by v23.5.1.

## v23.4.1 — Production World Map Copy Polish

* Replaced the final Kanto/Hoenn asset-production wording with gamer-ready world descriptions.
* Preserved the accepted map assets and behavior unchanged.
* Strengthened permanent player-facing copy regression coverage.

## v23.4.0 — Production Player-Facing Copy Reconstruction

* Reworked normal trainer-facing text across the site.
* Removed unnecessary implementation, reconstruction and developer terminology from gameplay pages.
* Preserved technical detail in maintenance, testing and development documentation.

## v23.3.1 — Production Evolution Moveset Adoption Integrity Fix

* Fixed duplicate evolved moves when **Adopt evolved moveset** is enabled.
* Added catalogue-wide duplicate-default protection.
* Preserved valid existing moves when the evolved default profile contains duplicate or blank slots.

## v23.3.0 — Production Legacy Helper Surface Isolation

* Made retained root compatibility helpers unavailable as direct browser pages.
* Preserved required internal PHP include behavior.
* Reduced unnecessary executable legacy surface area.

## v23.2.0 — Production Standard Battle Runtime Reconstruction

* Moved the standard AI-controlled Trainer Battle database boundary away from the old `mysql_*` compatibility API.
* Preserved accepted battle mechanics and presentation.
* Added owner-bound party, inventory, progression and win/loss persistence.

## v23.1.0 — Production Pokédex Detail Reconstruction

* Restored selected Pokédex detail pages.
* Added variant-aware evolution information.
* Added abilities, moves, variant-family presentation and owned-Pokémon information.
* Added recovered visual-form sprite fallbacks.

## v23.0.0 — Production Vortex / Wild / Live PvP Baseline

The v23 line consolidated the modern production architecture built during earlier Battle Arena, Events, Sidequest, regional map, Wild Battle and Live PvP reconstruction work.

Earlier detailed release history is preserved in:

```text
CHANGELOG.md
```

---

# Project Development Standard

Changes to the project should continue to prioritize:

1. preservation of accepted runtime behavior;
2. PHP 8 compatibility;
3. server-side authority for important gameplay actions;
4. database integrity;
5. safe ownership and input validation;
6. root-cause fixes rather than temporary workarounds;
7. local asset reliability;
8. consistent gamer-facing UI/UX;
9. deterministic regression testing;
10. clear README, changelog and testing documentation.

Large rewrites of already accepted systems should be avoided unless there is a clear correctness, security or maintainability benefit.

---

# Issues & Bug Reports

A useful bug report should include:

* project version;
* PHP version;
* MariaDB/MySQL version;
* operating system;
* browser;
* affected page or system;
* expected behavior;
* actual behavior;
* reproduction steps;
* relevant Apache/PHP errors;
* and screenshots where useful.

Do **not** post passwords, database credentials, session cookies, reset tokens or other sensitive information in public bug reports.

---

# Current Development Direction

The major reconstruction and hardening phase is now near completion.

Once v23.5.1 passes its final real-XAMPP collision acceptance test, future work can primarily focus on:

* newly discovered bugs;
* gameplay polish;
* balancing;
* optional content expansion;
* browser compatibility maintenance;
* security maintenance;
* and documentation improvements.

Core accepted systems should remain stable unless a verified issue requires a focused change.

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

* applicable intellectual-property requirements;
* security configuration;
* privacy obligations;
* database protection;
* local laws;
* and third-party asset licensing.

## Current in-game Screenshots
<img width="3803" height="2076" alt="allpokemonvarietiesrestored" src="https://github.com/user-attachments/assets/cbebbe81-6489-42db-87cf-c672bcb23c65" />
<img width="3840" height="2085" alt="battles" src="https://github.com/user-attachments/assets/faab5162-65ba-4e45-b6c0-e643774a2981" />
<img width="3800" height="2071" alt="capturingwildpokemon" src="https://github.com/user-attachments/assets/b48629bf-5868-440b-9a91-34f35b1c90e9" />
<img width="3840" height="2078" alt="didit2" src="https://github.com/user-attachments/assets/72c2209d-5cd5-4920-9810-7b93ac03c7e4" />
<img width="3799" height="2073" alt="restored maps to explore" src="https://github.com/user-attachments/assets/c9f4735d-b587-46af-8d29-05f2a847ff35" />
<img width="3840" height="2080" alt="evolutionlab" src="https://github.com/user-attachments/assets/35978ec2-ea94-40ce-af11-f5fada26c0e3" />
<img width="3798" height="2086" alt="team" src="https://github.com/user-attachments/assets/8492f085-b8fd-4c93-83a1-9ca2bd7d4b40" />
<img width="3807" height="2074" alt="Shop" src="https://github.com/user-attachments/assets/72233f4f-1d46-4296-919f-307972e73d2e" />
<img width="3840" height="2082" alt="AI Controlled Player Battles Fully Working" src="https://github.com/user-attachments/assets/6f658f0b-badf-4cd4-aa0e-5b6728ca4032" />
<img width="3840" height="2079" alt="AI Controlled Player Battles Win Or Defeat" src="https://github.com/user-attachments/assets/86d6b6a4-6f57-44c6-965c-ef93f6ff36f6" />
