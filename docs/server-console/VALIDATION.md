# v33 console validation

Validation used PHP 8.3.6 with mysqli/mysqlnd and MariaDB 10.11.14 in an isolated database imported from the uploaded baseline. The baseline archive was reconstructed from all eight supplied parts and every ZIP entry passed CRC verification before extraction.

## Performed checks

| Area | Result |
| --- | --- |
| Actual local setup routes | Fresh Rebuild followed by Upgrade/Repair passed against a brand-new database: 11 console tables, all 10,000 AI trainers and console schema version 330001 retained. |
| Broad command integration suite | 19 groups passed: migrations, proposal coverage, lexer, permissions, developer opt-in, disabled aliases, credentials, audit redaction, bounded mutations, confirmation lifecycle, social state, HTTP denials, moderation enforcement, stale sessions, maintenance, test-battle isolation, reset and deletion. |
| Pokémon inventory economy and live acceptance | 41 focused checks passed, including rollback on invalid values, ownership, legal evolution/ability routes, population counters, cloning, healing, active-battle protection and concurrent participant locks. |
| Account moderation lifecycle | 25 database checks and nine real HTTP authentication/signup checks passed, including complete account defaults, generated password hashes, expiry, revocation, progression reset, returned trade escrow and transferred clan leadership. |
| World and events | 30 database checks and three actual HTTP teleport assertions passed: collision-checked destinations, pending teleport revisions, spawns, event enrollment/rewards, period/weather, titles and poses. |
| Trading and friendship | 21 focused checks passed: real offer escrow, cancellation, reciprocal blocks, friend requests, repeated friendship history and migration repeatability. |
| Console transport and keyboard editor | 17 focused checks passed, including a real Unix PTY with partially typed input while activity arrives, worker protocol, command/error exit codes, redaction, terminal escape filtering, log rotation, bounded input and worker reload. |
| Background AI and ranked safeguards | 16 pure state-boundary checks and 823 database predicate, expiry, lock-contention, maintenance and candidate-query checks passed. Restricted or busy AI actors are skipped, and maintenance pauses background work. |
| Source syntax | All 230 PHP files linted successfully. Both changed JavaScript files passed node syntax checks; the shell launcher passed shell syntax checks. |
| Asset preservation | All 19,075 original media files and 1,221 map/data assets match the baseline bytes by CRC and size; no baseline files are missing. |
| HTTP isolation | Real localhost PHP HTTP requests returned 404 for the console, every CLI command handler and the guarded integration-test runner. There is no command HTTP transport. |
| Windows frontend | PowerShell script parsed without syntax errors; launcher path/argument handling reviewed. Windows console UI and XAMPP were not available for a native runtime test. |

The reusable broad suite is `PokemonVortex/tools/test_admin_console.php`. It refuses execution unless both `PV_ADMIN_TEST_DATABASE=1` and `--allow-disposable-db` are supplied. It creates and removes test fixtures and must only run against a disposable database. Normal operator `test` is a separate read-only diagnostic command.

The focused harnesses used disposable transactions and scratch fixtures. Test database files, accounts, generated passwords and appended test logs are excluded from the deliverable. Original game asset files are preserved.

The authentication checks verify redirects and session behavior; full dashboard rendering was outside that test because the isolated PHP runtime lacks the baseline UI’s mbstring extension.

## Local Windows check

Start XAMPP, open `PokemonVortex_ServerConsole.cmd`, and type `help`, `serverinfo`, `select <your trainer>`, `team` and `bag`. While moving in another browser window, type part of a command and verify that live activity leaves the unfinished line editable. This is the remaining platform-specific smoke check; no claim of Windows UI runtime verification is made.
