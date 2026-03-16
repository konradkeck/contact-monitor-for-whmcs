#!/usr/bin/env bash
set -euo pipefail

NAME="contact-monitor-whmcs"
MODULE_DIR="whmcs/modules/addons/contact_monitor_whmcs"
OUT_DIR="releases"

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
  echo "Usage: ./ops/build-release.sh <version>"
  echo "Example: ./ops/build-release.sh 0.1.0"
  exit 1
fi

mkdir -p "$OUT_DIR"
ZIP_PATH="${OUT_DIR}/${NAME}-${VERSION}.zip"

rm -f "$ZIP_PATH"
( cd "$MODULE_DIR/.." && zip -r "../../../../../$ZIP_PATH" "contact_monitor_whmcs" -x "*.DS_Store" )

echo "Built: $ZIP_PATH"
