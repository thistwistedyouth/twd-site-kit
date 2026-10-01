# dist

Reserved for the self-hosted updater (later slice) and the install zip.

- `twd-site-kit-latest.zip` is the plugin, ready to upload in WordPress (Plugins, Add New, Upload Plugin). It holds the plugin files, the stylesheet, the bundled fonts, the style packs and the gallery. Built by `bin/build-zip.sh`.
- `twd-site-kit-update.json` is what every site checks for updates: the newest version, its changelog and the sha256 of the zip. Written by `bin/update-json.php`, checked by `bin/check-release.php`. The zip and this file are always committed together.
- **This repo must stay public.** The updater on each client site reads this folder and the releases without logging in. Never put credentials or client content here.
