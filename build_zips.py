#!/usr/bin/env python3
"""Assemble the Club Leadership standalone PACKAGE zip (the only artifact you ship).

The package contains the component zip + module zip nested inside, plus the
package manifest, so a single `pkg_clubleadership.zip` both installs and
updates the extension. The com_/mod_ zips are NOT shipped on their own.
"""
import os, zipfile, shutil, tempfile

ROOT = os.path.dirname(os.path.abspath(__file__))
BUILD = ROOT
OUT = os.path.join(os.path.dirname(ROOT), 'pkg_out')
os.makedirs(OUT, exist_ok=True)
# Nested com/mod zips are intermediates — build them in a temp dir so pkg_out
# ends up containing ONLY the single shipped package zip.
TMP = tempfile.mkdtemp(prefix='cl-build-')

def zip_dir(src_dir, zip_path, prefix=''):
    """Zip the contents of src_dir (not including src_dir itself)."""
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
        for base, dirs, files in os.walk(src_dir):
            for f in files:
                full = os.path.join(base, f)
                rel = os.path.relpath(full, src_dir)
                arcname = (prefix + '/' + rel) if prefix else rel
                z.write(full, arcname)

def main():
    comp_src = os.path.join(BUILD, 'com_clubleadership')
    mod_src = os.path.join(BUILD, 'mod_clubleadership')

    # 1. Component zip (nested inside the package, not shipped separately)
    comp_zip = os.path.join(TMP, 'com_clubleadership.zip')
    if os.path.exists(comp_zip):
        os.remove(comp_zip)
    zip_dir(comp_src, comp_zip)

    # 2. Module zip (nested inside the package, not shipped separately)
    mod_zip = os.path.join(TMP, 'mod_clubleadership.zip')
    if os.path.exists(mod_zip):
        os.remove(mod_zip)
    zip_dir(mod_src, mod_zip)

    # 3. Package zip: pkg manifest + the two zips inside. This is the ONLY file shipped.
    pkg_zip = os.path.join(OUT, 'pkg_clubleadership.zip')
    if os.path.exists(pkg_zip):
        os.remove(pkg_zip)
    with zipfile.ZipFile(pkg_zip, 'w', zipfile.ZIP_DEFLATED) as z:
        z.write(os.path.join(BUILD, 'pkg', 'pkg_clubleadership.xml'), 'pkg_clubleadership.xml')
        z.write(comp_zip, 'com_clubleadership.zip')
        z.write(mod_zip, 'mod_clubleadership.zip')
    print('wrote', pkg_zip, os.path.getsize(pkg_zip), 'bytes')

if __name__ == '__main__':
    main()
