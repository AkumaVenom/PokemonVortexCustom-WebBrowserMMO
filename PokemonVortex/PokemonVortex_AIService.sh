#!/usr/bin/env sh
set -eu
PV_AI_DIR=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
PV_AI_PHP=${PV_PHP_EXE:-php}
if ! command -v "$PV_AI_PHP" >/dev/null 2>&1; then
    printf '%s\n' 'PHP CLI was not found. Install PHP CLI or set PV_PHP_EXE.' >&2
    exit 1
fi
if [ "$#" -eq 0 ]; then set -- --continuous; fi
exec "$PV_AI_PHP" -d display_errors=0 -d display_startup_errors=0 "$PV_AI_DIR/ai_service.php" "$@"
