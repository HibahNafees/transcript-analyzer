#!/usr/bin/env bash
# Deploy on the server from /var/www/transcripts: test first, then migrate and cache.
set -euo pipefail
cd "$(dirname "$0")"

composer install --no-interaction --quiet   # keeps dev deps: the tests run on this box
php artisan optimize:clear >/dev/null    # tests must not see cached production config
php artisan test
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "Deployed $(git rev-parse --short HEAD)"
