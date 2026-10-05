#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose up -d wordpress
ready=false
for attempt in $(seq 1 60); do
    if docker compose run --rm cli wp core version >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 2
done
if [ "$ready" != true ]; then
    echo 'WordPress no está listo; revisa docker compose logs.' >&2
    exit 1
fi
if ! docker compose run --rm cli wp core is-installed >/dev/null 2>&1; then
    docker compose run --rm cli wp core install --url=http://localhost:8098 --title='Aura Logic A/B Testing local' --admin_user=batadmin --admin_password=bat-local-development --admin_email=dev@example.test --skip-email
fi
docker compose run --rm cli wp plugin activate builder-ab-testing
if ! docker compose run --rm cli wp plugin is-installed elementor >/dev/null 2>&1; then
    docker compose run --rm cli wp plugin install elementor --version=4.3.4 --activate
else
    docker compose run --rm cli wp plugin activate elementor
fi
docker compose run --rm cli wp eval-file wp-content/plugins/builder-ab-testing/scripts/demo.php
docker compose run --rm cli wp eval-file wp-content/plugins/builder-ab-testing/scripts/builders-demo.php
