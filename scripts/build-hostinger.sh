#!/bin/bash
# Builds ./hostinger-upload/ (and a .zip) with exactly the files the live site needs.
# Upload the CONTENTS of that folder into public_html on Hostinger.
set -e
cd "$(dirname "$0")/.."
OUT=hostinger-upload
rm -rf "$OUT" "$OUT.zip"
mkdir -p "$OUT/api" "$OUT/admin" "$OUT/Ruban"
cp index.html messe.html content.json favicon.ico robots.txt sitemap.xml site.webmanifest .htaccess "$OUT/"
cp -R fonts "$OUT/"
rsync -a --exclude '.DS_Store' --exclude 'uploads/' Ruban/web "$OUT/Ruban/"
rsync -a --exclude '.DS_Store' --exclude 'README.md' --exclude 'config.example.php' --exclude 'config.php' admin/ "$OUT/admin/"
cp api/enquiry.php api/.htaccess "$OUT/api/"
# Secret files (api/config.php, admin/config.php) are never included —
# they already live on the server and must not be overwritten.
find "$OUT" -name '.DS_Store' -delete
(cd "$OUT" && zip -qr -X "../$OUT.zip" . )
echo "Built $OUT ($(du -sh "$OUT" | cut -f1)) and $OUT.zip ($(du -h "$OUT.zip" | cut -f1))"
