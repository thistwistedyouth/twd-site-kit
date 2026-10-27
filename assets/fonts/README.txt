Bundled fonts (self-hosted so client sites make no calls to Google Fonts).

Cormorant Garamond, Lora, Jost, Nunito, and from 0.14.0 EB Garamond and Inter:
variable fonts, Latin subset, woff2. All are licensed under the SIL Open Font
License 1.1. The licence text for each is in the OFL-*.txt file beside it. Font
files come from the Fontsource packages (@fontsource-variable/*, version 5.3.0
for EB Garamond and Inter; the files are files/<family>-latin-wght-<style>.woff2
inside the package, copied unchanged, no CDN).

To add a font: copy the latin woff2 files and the LICENSE here (as OFL-<slug>.txt),
add the family to TWD_SK_Packs::fonts() with its weight range, and run the tests.

Files are referenced only from includes/class-twd-sk-packs.php (the font list).
