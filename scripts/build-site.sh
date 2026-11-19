#!/usr/bin/env bash
# Builds the hosted demo (site/compassmep/demo/index.html) from prototype/index.html,
# wrapping it in a full HTML document with a permanent DEMO banner, then zips the
# compassmep site for upload to cPanel.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/site/compassmep/demo/index.html"
{
cat <<'HEAD'
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<style>
:root{color-scheme:light;padding:env(safe-area-inset-top,0px) 0 env(safe-area-inset-bottom,0px)}
body{margin:0;font:14px system-ui,-apple-system,"Segoe UI",sans-serif}
img{max-width:100%}
[hidden]{display:none!important}
.demo-banner{background:#B86A00;color:#FFFFFF;font:600 13px/1.4 system-ui,sans-serif;padding:8px max(16px,env(safe-area-inset-left,0px));display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.demo-banner b{letter-spacing:.14em}
.demo-banner a{color:#FFFFFF}
</style>
</head>
<body>
<div class="demo-banner" role="note"><span><b>DEMO</b> · Sample data only. Nothing here is real, and nothing you enter reaches the live system.</span><a href="../">Leave the demo</a></div>
HEAD
cat "$ROOT/prototype/index.html"
printf '\n</body>\n</html>\n'
} > "$OUT"
sed -i 's|<title>Compass Job Portal</title>|<title>DEMO · Compass MEP Reactive Maintenance</title>|' "$OUT"
cd "$ROOT/site"
rm -f compassmep-upload.zip
(cd compassmep && zip -qr ../compassmep-upload.zip . -x '*.DS_Store')
echo "Built $OUT and site/compassmep-upload.zip"
