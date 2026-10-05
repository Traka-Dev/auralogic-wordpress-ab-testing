#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for suite in integration builders atomic dashboard; do
    docker compose run --rm cli wp eval-file "wp-content/plugins/builder-ab-testing/tests/${suite}.php"
done
