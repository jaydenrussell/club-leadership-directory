#!/usr/bin/env python3
"""Assemble the Club Leadership standalone PACKAGE zip (the only artifact you ship).

The package contains the component zip + module zip nested inside, plus the
package manifest, so a single `pkg_clubleadership.zip` both installs and
updates the extension. The com_/mod_ zips are NOT shipped on their own.

This script is the one-stop release builder:
  - zips component + module (as nested intermediates),
  - builds the single shipped package zip,
  - computes its SHA256 and writes it into update.xml so Joomla's update
    manager checksum check always matches (no manual step).

After running, commit build_zips.py + update.xml and publish the new
pkg_clubleadership.zip to a GitHub release whose tag matches the version in
pkg/pkg_clubleadership.xml (the download URL in update.xml must point there).
"""
import os, re, zipfile, shutil, tempfile, hashlib

ROOT = os.path.dirname(os.path.abspath(__file__))
BUILD = ROOT
OUT = os.path.join(os.path.dirname(ROOT), 'pkg_out')
PKG_MANIFEST = os.path.join(BUILD, 'pkg', 'pkg_clubleadership.xml')
UPDATE_XML = os.path.join(BUILD, 'update.xml')
os.makedirs(OUT, exist_ok=True)
# Nested com/mod zips are intermediates — build them in a temp dir so pkg_out
# ends up containing ONLY the single shipped package zip.
TMP = tempfile.mkdtemp(prefix='cl-build-')


def zip_dir(src_dir, zip_path, prefix=''):
    """Zip the contents of src_dir (not including src_dir itself).

    Files are added in sorted order so builds are deterministic: identical
    input source always yields a byte-identical zip (and thus a stable SHA256),
    which keeps the checksum in update.xml meaningful across rebuilds.
    """
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
        for base, dirs, files in os.walk(src_dir):
            dirs.sort()
            for f in sorted(files):
                full = os.path.join(base, f)
                rel = os.path.relpath(full, src_dir)
                arcname = (prefix + '/' + rel) if prefix else rel
                z.write(full, arcname)


def read_version():
    with open(PKG_MANIFEST, 'r', encoding='utf-8') as fh:
        m = re.search(r'<version>(.*?)</version>', fh.read())
    if not m:
        raise SystemExit('ERROR: no <version> found in pkg/pkg_clubleadership.xml')
    return m.group(1).strip()


def write_checksum_to_update_xml(sha):
    with open(UPDATE_XML, 'r', encoding='utf-8') as fh:
        xml = fh.read()
    if re.search(r'<sha256>', xml):
        xml = re.sub(r'<sha256>.*?</sha256>', f'<sha256>{sha}</sha256>', xml, count=1)
    else:
        # Insert before <maintainer> as a sensible sibling of <downloads>.
        xml = xml.replace('<maintainer>', f'<sha256>{sha}</sha256>\n        <maintainer>', 1)
    with open(UPDATE_XML, 'w', encoding='utf-8') as fh:
        fh.write(xml)


def main():
    version = read_version()
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

    sha = hashlib.sha256(open(pkg_zip, 'rb').read()).hexdigest()
    write_checksum_to_update_xml(sha)

    print('version :', version)
    print('wrote   :', pkg_zip, os.path.getsize(pkg_zip), 'bytes')
    print('sha256  :', sha)
    print('updated :', os.path.relpath(UPDATE_XML, ROOT), '(checksum injected)')


if __name__ == '__main__':
    main()
