#!/usr/bin/env python3
"""Build a TEST zip of the plugin OUTSIDE the repo, for a staging site (never dist/).

Usage: python bin/build-test-zip.py 0.22.0-dev1 [output-folder]

Why this exists: on Windows there is no `zip`, and PowerShell's Compress-Archive and `tar -a`
make zips WordPress calls an "incompatible archive". This writes the same plain shape as the
release zips: one top-level twd-site-kit folder, forward slashes, LF line endings in text files,
Unix file attributes, no directory entries. It sets the version in the main file's header and in
TWD_SK_VERSION so the browser does not keep the old stylesheet and script addresses.
"""
import os, re, sys, shutil, tempfile, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FOLDERS = ['includes', 'assets', 'packs', 'starters', 'templates', 'modules']
TEXT = ('.php', '.js', '.css', '.json', '.txt', '.html', '.svg')


def main():
    if len(sys.argv) < 2 or not re.match(r'^\d+\.\d+\.\d+(-[a-z0-9.]+)?$', sys.argv[1]):
        sys.exit('Give a version label such as 0.22.0-dev1')
    version = sys.argv[1]
    out_dir = sys.argv[2] if len(sys.argv) > 2 else os.path.join(os.path.dirname(ROOT), 'test-zips')
    if os.path.abspath(out_dir).startswith(os.path.abspath(ROOT)):
        sys.exit('The output folder must be outside the repository.')
    os.makedirs(out_dir, exist_ok=True)
    tmp = tempfile.mkdtemp()
    dest = os.path.join(tmp, 'twd-site-kit')
    os.makedirs(dest)
    for f in ('twd-site-kit.php', 'readme.txt'):
        shutil.copy(os.path.join(ROOT, f), dest)
    for d in FOLDERS:
        shutil.copytree(os.path.join(ROOT, d), os.path.join(dest, d))
    main_file = os.path.join(dest, 'twd-site-kit.php')
    s = open(main_file, encoding='utf-8', newline='').read().replace('\r\n', '\n')
    s, n1 = re.subn(r'(\* Version:\s*)\S+', r'\g<1>' + version, s, count=1)
    s, n2 = re.subn(r"(define\( 'TWD_SK_VERSION', ')[^']+(' \);)", r'\g<1>' + version + r'\g<2>', s, count=1)
    if n1 != 1 or n2 != 1:
        sys.exit('Could not set the version in the main file.')
    open(main_file, 'w', encoding='utf-8', newline='').write(s)
    out = os.path.join(out_dir, 'twd-site-kit-%s.zip' % version)
    count = 0
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
        for root, dirs, files in os.walk(dest):
            dirs.sort()
            for f in sorted(files):
                p = os.path.join(root, f)
                name = os.path.relpath(p, tmp).replace(os.sep, '/')
                data = open(p, 'rb').read()
                if f.lower().endswith(TEXT):
                    data = data.replace(b'\r\n', b'\n')
                zi = zipfile.ZipInfo(name, (2026, 1, 1, 12, 0, 0))
                zi.create_system = 3
                zi.compress_type = zipfile.ZIP_DEFLATED
                zi.external_attr = 0o100644 << 16
                z.writestr(zi, data)
                count += 1
    shutil.rmtree(tmp)
    print('%s (%d files)' % (out, count))


main()
