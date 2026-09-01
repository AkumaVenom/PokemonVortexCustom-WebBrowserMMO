# Pokémon Vortex Custom

### Modernized Web Browser MMO / Web Server Reconstruction

> A modernized reconstruction of the recovered Pokémon Vortex browser MMORPG codebase, restored and progressively hardened for contemporary PHP, MySQL/MariaDB, Apache and modern web browsers.

**Current Development Baseline:** `v20`
**Status:** Work in Progress / Active Development
**Runtime:** PHP 8.x · Apache · MySQL / MariaDB
**License:** MIT for original project code — see [LICENSE](LICENSE)

---

## Overview

**Pokémon Vortex Custom** is a restoration, modernization and continued-development project based on recovered historical Pokémon Vortex browser RPG source material.

The objective is not simply to make the old PHP files execute again.

The project is being systematically reconstructed into a coherent, playable and maintainable browser-based Pokémon MMO while preserving the character of the recovered game wherever practical.

The modernization work focuses on:

* restoring previously broken gameplay systems;
* supporting current PHP environments;
* replacing obsolete PHP APIs and unsafe historical patterns;
* making important gameplay actions server-authoritative;
* improving database transaction integrity;
* protecting actions against duplicate requests and forged input;
* restoring missing or incomplete Pokémon/game data;
* removing obsolete external dependencies;
* preserving recovered maps, sprites and game content;
* rebuilding damaged frontend and gameplay interfaces;
* introducing a consistent modern dark navy / cyan / gold presentation;
* and progressively bringing the entire project toward production-quality standards.

This is an ongoing reconstruction rather than a bit-for-bit archival copy of any historical production Pokémon Vortex server.

<img width="3798" height="1854" alt="MainPage" src="https://github.com/user-attachments/assets/c4410dee-c7e6-4b1e-8a82-ad025746d0a6" />

<img width="3840" height="2079" alt="Create" src="https://github.com/user-attachments/assets/3ecb1a88-958a-4916-9e62-cf67cba68cc4" />

---

# Project Status

## Current Baseline — v20

`v20` is the current authoritative development baseline.

Development is proceeding conservatively from this version with an emphasis on:

* regression prevention;
* server-side validation;
* transactional database operations;
* PHP 8 compatibility;
* secure account handling;
* reliable battle state;
* persistent gameplay state;
* preservation of recovered assets;
* coherent UI/UX;
* maintainable compatibility layers;
* and root-cause fixes rather than temporary workarounds.

The project remains **WIP** and should not yet be considered a finished production MMO.

---

# Major Systems

A significant amount of the recovered game has already been restored or modernized.

## Trainer Accounts

The account system includes modernized handling for:

* trainer registration;
* login;
* password recovery;
* account initialization;
* starter Pokémon creation;
* trainer statistics;
* team assignment;
* Pokédex initialization;
* account options;
* and related persistent account records.

Critical account creation operations are handled transactionally so failed registration attempts do not leave partially-created trainers behind.

Password recovery has also been hardened around single-use reset state and transactional completion.

---

## Pokémon Collection

The project maintains the core Pokémon collection model inherited from the recovered game while progressively improving validation and persistence.

Pokémon records can contain information such as:

* species;
* level;
* experience;
* moves;
* ability;
* nature;
* gender;
* IV information;
* Pokémon variant;
* original trainer;
* capture ball;
* happiness;
* team/storage state;
* and other recovered game metadata.

---

## Pokémon Variants

Recovered visual variants are preserved where corresponding assets are available.

Supported recovered variant families include:

* Normal
* Shiny
* Dark
* Mystic
* Metallic
* Shadow

Special visual forms from the recovered map data are also preserved wherever possible, including species with form-specific artwork even where the historical database only contains one canonical species entry.

---

# World & Exploration

## Map System

The reconstructed game retains the recovered browser-map exploration system plus the additional Kanto & Hoenn Regions.

The current restoration includes:

* recovered map artwork;
* player positioning;
* map navigation;
* encounter generation;
* Pokémon encounter presentation;
* species/form preservation;
* secure battle handoff;
* and local sprite resolution.
* additional Kanto & Hoenn regions.


The project contains the recovered map image set used by the reconstructed exploration runtime.

---

## Secure Wild Encounters

Wild encounters no longer depend on blindly trusting historical client-provided Pokémon identifiers.

Modernized encounter handling includes server-issued encounter state containing information such as:

* species;
* display form;
* sprite;
* level;
* map;
* coordinates;
* and secure encounter identity.

Encounter requests use cryptographically generated one-use state so the browser cannot simply fabricate arbitrary wild encounters.

Expired or previously-consumed encounter state cannot be reused to begin another battle.

---

# Battle System

One of the most heavily reconstructed areas of the project is the battle runtime.

## Wild Battles

The historical wild battle implementation has been replaced with a modern server-controlled workflow.

Current functionality includes:

* Fight;
* Pokémon switching;
* automatic replacement after fainting;
* Items;
* Poké Balls;
* Run;
* Victory;
* Defeat;
* Capture;
* EXP rewards;
* happiness rewards;
* money rewards;
* trainer progression;
* battle result persistence;
* and duplicate-reward protection.

---

## Battle Mechanics

The reconstructed battle runtime includes support for core mechanics such as:

* HP calculation;
* move accuracy;
* STAB;
* Pokémon type effectiveness;
* immunities;
* resistances;
* critical hits;
* bounded damage variance;
* move metadata;
* opponent move selection;
* fainting;
* party replacement;
* and battle completion state.

---

## Battle Integrity

Important battle decisions are validated on the server.

Protection includes:

* Pokémon ownership validation;
* active-team validation;
* move-slot allowlists;
* item allowlists;
* CSRF protection;
* battle identity checking;
* turn sequence validation;
* stale-form rejection;
* duplicate-submit prevention;
* one-use battle actions;
* atomic item consumption;
* and durable result processing.

Battle result state is recorded so a completed reward or capture cannot simply be processed again because of a repeated browser request or interrupted response.

---

# Pokémon Capture

Wild Pokémon can be captured through the reconstructed battle runtime.

Supported capture items include:

* Poké Ball
* Great Ball
* Ultra Ball
* Master Ball

Capture probability can take into account battle state such as:

* remaining target HP;
* capture ball tier;
* and opponent level.

Successful captures create persistent Pokémon records containing reconstructed game data including appropriate catalogue information, moves, types, IVs, nature, gender, ability, original trainer and capture-ball metadata.

Failed capture attempts consume exactly one applicable ball and continue the existing encounter.

---

# Battle Items

Recovered medicine and battle inventory behaviour has been progressively hardened.

Implemented/restored behaviour includes healing items such as:

* Potion
* Super Potion
* Hyper Potion

Historical status-item inconsistencies and several inventory-decrement issues have also been corrected.

Battle inventory operations are designed so applicable items are consumed once rather than being vulnerable to duplicate or inconsistent decrements.

---

# Additional Game Systems

The reconstruction also retains or restores major portions of the wider browser MMO.

Depending on the current development state of each subsystem, this includes:

* PokéMart;
* Pokémon Labs;
* Pokémon collection management;
* trading;
* sidequests;
* Event Center;
* clans;
* community functionality;
* private messages;
* trainer progression;
* Pokédex tracking;
* Live Battle compatibility;
* standard/gym battle compatibility;
* account recovery;
* and persistent trainer data.

These systems continue to receive modernization and integrity passes as development progresses.

---

# Event Center

Recovered Event Center functionality has received additional server-side validation.

Modernization work includes:

* CSRF enforcement;
* rejection of conflicting forged actions;
* transactional purchases;
* duplicate unlock protection;
* ownership checking;
* protected Pokémon validation;
* guarded item/currency updates;
* and improved fusion request validation.

---

# Sidequests & Rewards

Sidequest reward handling has been reconstructed with emphasis on preventing duplicate progression rewards.

Important milestone rewards can be committed transactionally so repeated or interrupted requests do not result in the same reward being collected multiple times.

---

# Modern User Interface

The reconstruction is progressively replacing the fragmented historical presentation with a consistent modern interface.

The established visual direction uses:

* deep navy backgrounds;
* cyan interface highlights;
* gold accent elements;
* polished panels;
* responsive information hierarchy;
* modern status displays;
* clear gameplay actions;
* and game-focused browser MMO presentation.

The reconstructed battle interface includes elements such as:

* player and enemy Pokémon HUDs;
* health bars;
* battle telemetry;
* command tabs;
* move cards;
* item controls;
* capture controls;
* Pokémon switching cards;
* encounter information;
* and responsive gameplay feedback.

The goal is to maintain the identity of a Pokémon browser MMO while presenting it at a substantially higher visual and usability standard than the recovered frontend.

---

# Legacy Compatibility

The recovered source relied heavily on PHP functionality that no longer exists in current PHP releases.

Most notably, historical code depended on the removed:

```text
mysql_*
```

PHP extension.

A compatibility bootstrap maps required legacy database calls onto modern `mysqli` behaviour so reconstructed legacy gameplay pages can continue functioning while progressively modernized systems use safer database access patterns.

This allows restoration to proceed incrementally without requiring the entire historical application to be rewritten in one destructive pass.

Legacy compatibility code should be considered a migration bridge rather than the preferred architecture for newly written systems.

---

# Security & Runtime Hardening

Security and state integrity are major priorities of the reconstruction.

Modernized areas progressively use practices including:

* prepared statements;
* server-authoritative validation;
* CSRF protection;
* transactional database operations;
* guarded database updates;
* row locking where appropriate;
* cryptographically generated tokens;
* single-use actions;
* POST → Redirect → GET workflows;
* ownership verification;
* command allowlists;
* duplicate-request protection;
* immutable battle identifiers;
* persistent result ledgers;
* same-origin restrictions;
* and production-safe error handling.

The project intentionally avoids blindly preserving insecure historical implementation details where they would compromise the reconstructed game.

---

# Database Reconstruction

The project database is assembled from recovered Pokémon Vortex SQL material together with supplementary Pokémon and ability datasets used during restoration.

Some original production-era data was unavailable.

Where required, compatibility records and supporting data have therefore been reconstructed so the game can operate coherently.

The goal is:

> **A functional, internally consistent and playable reconstruction — not a forensic bit-for-bit replica of the original live database.**

---

# Requirements

Recommended environment:

| Component              | Recommended                         |
| ---------------------- | ----------------------------------- |
| Web Server             | Apache 2.4+                         |
| PHP                    | PHP 8.2+                            |
| Database               | MariaDB 10.4+ or MySQL 8+           |
| PHP Database Extension | `mysqli`                            |
| Browser                | Current Firefox, Chromium or Edge   |
| Development Package    | Current XAMPP release or equivalent |

The project has undergone compatibility validation against modern PHP 8 environments during development.

---

# Local Installation with XAMPP

## 1. Install XAMPP

Install a current XAMPP environment containing:

* Apache;
* PHP;
* MariaDB/MySQL;
* and phpMyAdmin if desired.

---

## 2. Copy the Web Application

Place the contents of the project's:

```text
public_html
```

directory into a directory such as:

```text
C:\xampp\htdocs\PokemonVortex
```

Your resulting layout should begin approximately like:

```text
C:\xampp\htdocs\PokemonVortex\
    config\
    ...
    index.php
    _setup.php
```

---

## 3. Start the Required Services

Start:

```text
Apache
MySQL
```

from the XAMPP Control Panel.

---

## 4. Configure the Database

Check:

```text
config/app.php
```

and confirm that the configured database credentials match your local environment.

A typical default XAMPP installation uses values equivalent to:

```text
Host:      localhost
User:      root
Password:  [blank]
Database:  pokemon_vortex
```

Do not use blank database credentials on an Internet-facing production server.

---

## 5. Run the Local Setup Utility

From the machine hosting XAMPP, open:

```text
http://localhost/PokemonVortex/_setup.php
```

and run the supplied installation/database import process.

The setup utility is intentionally restricted to localhost and is not intended to appear in normal player navigation.

---

## 6. Launch the Game

Open:

```text
http://localhost/PokemonVortex/
```

Create a trainer account and sign in.

---

# Version History

## v19 — Production Runtime Integrity Hardening

### Standard & Live Battle

* Added strict server-side allowlists for submitted move slots and battle items.
* Added active-team validation for switching.
* Rejected fainted or forged Pokémon IDs.
* Removed the historical standard-battle double medicine decrement path.
* Made applicable item decrements atomic and single-use.
* Corrected historical status medicine naming inconsistencies.
* Corrected poison item-turn damage handling.
* Corrected historical Live Battle inventory update defects.
* Synchronized applicable Live Battle medicine changes with persistent inventory.
* Prevented ineffective status medicine from being consumed.

### Accounts & Recovery

* Made trainer registration transactional across related account records.
* Failed registration now rolls back cleanly.
* Expanded transactional database engine coverage.
* Removed new writes to obsolete password mirrors.
* Made password reset completion atomic.
* Added single-use reset token consumption.
* Made reset-token replacement transactional.

### Event Center

* Enforced CSRF protection.
* Rejected conflicting forged actions.
* Made event unlocks atomic and idempotent.
* Made applicable Event Center purchases transactional.
* Hardened fusion request validation.
* Added stronger Pokémon ownership and identity validation.
* Removed inappropriate production error output.

### Security

* Tightened frame restrictions after obsolete embeds were retired.
* Continued production runtime hardening.
* Completed full PHP and JavaScript syntax validation for the corresponding development package.

---

## v18 — Battle, Sidequest & Production Safekeeping

* Established the verified safekeeping baseline used for subsequent runtime-hardening work.
* Preserved exact map encounter identity through battle initialization.
* Reconstructed Fight, Items, Poké Balls, Switch, Run, victory, defeat and capture flows.
* Added CSRF validation and one-use battle actions.
* Added durable battle result processing.
* Hardened standard/gym and Live Battle compatibility.
* Added transactional sidequest milestone rewards.
* Added duplicate reward protection.
* Continued removal of obsolete dependencies and historical restoration/debug output.
* Preserved trading, labs, PokéMart, collection, community, messaging and clan functionality.

---

## v16 — Wild Encounter & Battle Reconstruction

### Encounters

* Removed dependence on unreliable historic numeric encounter Pokémon IDs.
* Added species-name resolution against the installed Pokémon catalogue.
* Preserved recovered form-specific encounter artwork.
* Added cryptographically generated one-use encounter state.
* Added encounter expiry and replay protection.
* Reconstructed compatibility encounter data where required.

### Wild Battle

* Replaced the damaged recovered AJAX battle flow.
* Added same-origin POST battle commands.
* Added CSRF validation.
* Added POST → Redirect → GET handling.
* Added per-turn command sequence validation.
* Added immutable battle IDs.
* Added team ownership validation.
* Added HP, accuracy, STAB, type, critical-hit and damage calculations.
* Added opponent battle logic.
* Added Pokémon switching and automatic faint replacement.
* Added battle telemetry and completion locking.
* Added durable result processing.

### Capture & Items

* Added atomic medicine and Poké Ball consumption.
* Added Potion, Super Potion and Hyper Potion support.
* Added multiple Poké Ball tiers.
* Added persistent captured Pokémon creation.
* Added progression/Pokédex refresh.
* Ensured failed captures continue the existing battle correctly.

### Rewards

* Added one-time battle reward processing.
* Added team EXP.
* Added happiness progression.
* Added trainer wins.
* Added money rewards.
* Added authoritative progression recalculation.
* Added defeat recording.

### Presentation

* Reconstructed the wild battle interface.
* Added combat HUDs and HP displays.
* Added telemetry.
* Added command tabs and move cards.
* Added item and capture controls.
* Added team switching.
* Added sprite fallback handling.
* Added the modernized map encounter panel.

---

# Contributing

This project is under active reconstruction.

When contributing, changes should prioritize:

1. preserving validated behaviour;
2. PHP 8 compatibility;
3. server-side authority;
4. database integrity;
5. secure input validation;
6. clear and maintainable code;
7. local asset reliability;
8. UI consistency;
9. regression testing;
10. documented behavioural changes.

Large rewrites of functioning legacy systems should be avoided unless they provide a clear technical or security benefit.

---

# Issues & Bug Reports

Useful bug reports should include:

* project version;
* PHP version;
* database version;
* operating system;
* browser;
* affected page/system;
* expected behaviour;
* actual behaviour;
* reproduction steps;
* relevant Apache/PHP errors;
* relevant screenshots;
* and whether the issue occurs on a clean database.

Avoid posting passwords, database credentials, session cookies, reset tokens or other sensitive information in public issues.

---

# Roadmap

Ongoing development includes progressively improving:

* remaining legacy PHP paths;
* battle compatibility;
* account security;
* Pokémon management;
* trading;
* multiplayer/community features;
* Events;
* sidequests;
* frontend consistency;
* responsive presentation;
* administrator tooling;
* database integrity;
* security hardening;
* documentation;
* and production deployment readiness.

The guiding objective is a complete, reliable and highly polished modern browser MMO reconstruction while retaining the identity and recovered functionality of the original source.

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

The MIT license supplied with this repository does **not** grant ownership of or additional rights to third-party Pokémon intellectual property or recovered third-party assets.

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
<img width="3840" height="2076" alt="hoenn3" src="https://github.com/user-attachments/assets/380c15f9-3250-4e8e-834d-20429f39116f" />
<img width="3840" height="2077" alt="hoenn1" src="https://github.com/user-attachments/assets/227e3042-ba59-46dd-a728-e331384b3fe2" />
<img width="3840" height="2080" alt="evolutionlab" src="https://github.com/user-attachments/assets/35978ec2-ea94-40ce-af11-f5fada26c0e3" />
<img width="3798" height="2086" alt="team" src="https://github.com/user-attachments/assets/8492f085-b8fd-4c93-83a1-9ca2bd7d4b40" />
<img width="3807" height="2074" alt="Shop" src="https://github.com/user-attachments/assets/72233f4f-1d46-4296-919f-307972e73d2e" />
<img width="3840" height="2082" alt="AI Controlled Player Battles Fully Working" src="https://github.com/user-attachments/assets/6f658f0b-badf-4cd4-aa0e-5b6728ca4032" />
<img width="3840" height="2079" alt="AI Controlled Player Battles Win Or Defeat" src="https://github.com/user-attachments/assets/86d6b6a4-6f57-44c6-965c-ef93f6ff36f6" />
<img width="3840" height="2082" alt="leuge" src="https://github.com/user-attachments/assets/6a506c4d-5a78-465a-af23-ba39f476a3ce" />
<img width="3840" height="2080" alt="battlearena" src="https://github.com/user-attachments/assets/b43d3a07-c77d-4ae6-a6b6-a4d9cb73a651" />
