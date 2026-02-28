#!/usr/bin/env bash
# Build finch-form-x.x.x.zip from src/finch-form into dist/
# Version in the plugin is read from package.json and injected into finch-form.php.
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
SRC="$ROOT/src"
PLUGIN_DIR="$SRC/finch-form"
PHP_MAIN="$PLUGIN_DIR/finch-form.php"

# Read version from package.json (single source of truth)
VERSION=$(node -p "require('$ROOT/package.json').version")

# Inject version into finch-form.php (plugin header and FINCH_FORM_VERSION constant)
node -e "
var fs = require('fs');
var version = process.argv[1];
var content = fs.readFileSync('$PHP_MAIN', 'utf8');
content = content.replace(/^(\s*\* Version: ).*$/m, '\$1' + version);
content = content.replace(/(define\s*\(\s*'FINCH_FORM_VERSION',\s*')[^']*('\s*\))/, '\$1' + version + '\$2');
fs.writeFileSync('$PHP_MAIN', content);
" "$VERSION"

PLUGIN_DIR_NAME="finch-form"
ZIP_BASENAME="finch-form"
ZIP_NAME="${ZIP_BASENAME}-${VERSION}.zip"

mkdir -p "$DIST"
cd "$SRC"

zip -r "$DIST/$ZIP_NAME" "$PLUGIN_DIR_NAME" -x "*.DS_Store"

echo "Built: $DIST/$ZIP_NAME"