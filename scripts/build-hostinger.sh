#!/bin/bash
# Builds ./hostinger-upload/ (and a .zip) with exactly the files the live site needs.
# Upload the CONTENTS of that folder into public_html on Hostinger.
set -e
cd "$(dirname "$0")/.."
OUT=hostinger-upload
rm -rf "$OUT" "$OUT.zip"
mkdir -p "$OUT/api" "$OUT/admin" "$OUT/Ruban"
cp index.html content.json favicon.ico robots.txt sitemap.xml site.webmanifest .htaccess "$OUT/"
cp -R fonts "$OUT/"
# Exhibition page at /catamarans/ (works on any host, no rewrite rules needed)
mkdir -p "$OUT/catamarans"
cp messe.html "$OUT/catamarans/index.html"
# Old address forwards to the new one
cat > "$OUT/messe.html" <<'HTML'
<!doctype html>
<html lang="de"><head><meta charset="utf-8"><title>RUBAN MARINE 400</title>
<meta name="robots" content="noindex"><link rel="canonical" href="https://rubanmarine.de/catamarans/">
<meta http-equiv="refresh" content="0; url=/catamarans/">
<script>location.replace('/catamarans/' + location.search + location.hash);</script>
</head><body><a href="/catamarans/">rubanmarine.de/catamarans</a></body></html>
HTML
rsync -a --exclude '.DS_Store' --exclude 'uploads/' Ruban/web "$OUT/Ruban/"
rsync -a --exclude '.DS_Store' --exclude 'README.md' --exclude 'config.example.php' --exclude 'config.php' admin/ "$OUT/admin/"
cp api/enquiry.php api/.htaccess "$OUT/api/"
# Secret files (api/config.php, admin/config.php) are never included —
# they already live on the server and must not be overwritten.
find "$OUT" -name '.DS_Store' -delete
(cd "$OUT" && zip -qr -X "../$OUT.zip" . )
echo "Built $OUT ($(du -sh "$OUT" | cut -f1)) and $OUT.zip ($(du -h "$OUT.zip" | cut -f1))"
