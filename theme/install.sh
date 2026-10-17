#!/usr/bin/env bash
set -euo pipefail
# Usage: bash scripts/install.sh /absolute/path/to/dolibarr/htdocs
root="${1:?Pass the Dolibarr htdocs directory}"
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
source_dir="$script_dir/../theme/indy"
# The Railway repository puts this installer directly in its theme/ directory.
if [[ -f "$script_dir/indy/style.css.php" ]]; then
    source_dir="$script_dir/indy"
fi
[[ -f "$root/theme/eldy/style.css.php" ]] || { echo 'Eldy not found in the supplied htdocs directory.' >&2; exit 1; }
mkdir -p "$root/theme/indy"
# Native PHP code sometimes addresses current-theme images/editor config directly.
# Inherit those from the installed Dolibarr version instead of vendoring old copies.
for resource in img ckeditor tinymce; do
    cp -R "$root/theme/eldy/$resource" "$root/theme/indy/"
done
cp -R "$source_dir/." "$root/theme/indy/"
echo 'Indy installed. Select indy in Setup > Display > Theme, then save.'
