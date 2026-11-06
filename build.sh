#!/bin/sh
# Builds dist/kwtsms-osticket-<version>.zip: the plugin folder "kwtsms" with the kwtSMS PHP library bundled in vendor/.
# Usage: sh build.sh
set -eu
VERSION=$(sed -n "s/.*'version' *=> *'\([^']*\)'.*/\1/p" plugin.php)
rm -rf build dist && mkdir -p build/kwtsms dist
cp plugin.php kwtsms.php config.php class.kwtsms.php composer.json LICENSE build/kwtsms/
(cd build/kwtsms && composer install -q --no-dev --no-interaction --optimize-autoloader && rm composer.json composer.lock \
    && find vendor/kwtsms/kwtsms-php -mindepth 1 -maxdepth 1 ! -name src ! -name LICENSE ! -name composer.json -exec rm -rf {} +)
(cd build && zip -qr ../dist/kwtsms-osticket-$VERSION.zip kwtsms)
rm -rf build
echo "dist/kwtsms-osticket-$VERSION.zip"
