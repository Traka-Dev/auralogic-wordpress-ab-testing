#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
find includes scripts tests -name '*.php' > /tmp/bat-php-files
while IFS= read -r file; do
    php -l "$file"
done < /tmp/bat-php-files
php -l plugin-ab-testing.php
