#!/usr/bin/env bash
# Build finch-form-x.x.x.zip from src/finch-forms into dist/
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
SRC="$ROOT/src"

# Read version from package.json
VERSION=$(node -p "require('$ROOT/package.json').version")

PLUGIN_DIR_NAME="finch-forms"
ZIP_BASENAME="finch-form"
ZIP_NAME="${ZIP_BASENAME}-${VERSION}.zip"

mkdir -p "$DIST"
cd "$SRC"

zip -r "$DIST/$ZIP_NAME" "$PLUGIN_DIR_NAME" -x "*.DS_Store"

echo "Built: $DIST/$ZIP_NAME"