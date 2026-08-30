# PokemonVortexCustom-WebBrowserMMO-WebServer WIP

This package is a carefully modernized style reconstruction of the recovered Pokémon Vortex browser RPG source, prepared for a current XAMPP/PHP environment.

## Install on XAMPP

1. Copy the `public_html` folder to `C:\xampp\htdocs\PokemonVortex`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Confirm `public_html/config/app.php` matches your local MySQL credentials. The default XAMPP configuration is already supplied (`root`, blank password, database `pokemon_vortex`).
4. Browse locally to `http://localhost/PokemonVortex/_setup.php`.
5. Run the local installer/import process.
6. Open `http://localhost/PokemonVortex/`, create a trainer account and sign in.

`_setup.php` is intentionally localhost-only and does not appear in normal player navigation.

## Production-facing design

The v4 interface uses a coherent dark navy/cyan sci‑fi visual system across both reconstructed modern pages and older recovered game pages. The presentation is built with CSS geometry, linework, grids, layered panels and subtle HUD accents so it does not require large decorative background images.

Public pages suppress PHP error output, keep technical details in server-side logs, use hardened session cookies, and apply basic browser security headers. Private configuration, include, storage, SQL and log files are blocked from direct web access through `.htaccess`.

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
