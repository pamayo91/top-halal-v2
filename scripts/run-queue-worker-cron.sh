#!/usr/bin/env bash

set -Eeuo pipefail

readonly APP_PATH='/home/meyo5199/top-halal-v2'
readonly PHP_BINARY='/opt/alt/php84/usr/bin/php'
readonly LOCK_FILE="$APP_PATH/storage/framework/queue-worker.lock"

cd "$APP_PATH"

exec /usr/bin/flock -n "$LOCK_FILE" \
    "$PHP_BINARY" artisan queue:work database \
    --queue=default \
    --stop-when-empty \
    --tries=4 \
    --timeout=75 \
    --sleep=1 \
    --no-interaction
