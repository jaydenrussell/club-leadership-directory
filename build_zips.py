#!/usr/bin/env python3
"""Assemble the Club Leadership standalone package zips."""
import os, zipfile, shutil

ROOT = os.path.dirname(os.path.abspath(__file__))
BUILD = ROOT
OUT = os.path.join(os.path.dirname(ROOT), 'pkg_out')
os.makedirs(OUT, exist_ok=True)

def zip_dir(src_dir, zip_path, prefix=''):
    """Zip the contents of src_dir (not including src_dir itself)."""
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
        for base, dirs, files in os.walk(src_dir):
            for f in files:
                full = os.path.join(base, f)
                rel = os.path.relpath(full, src_dir)
                if prefix:
                    arcname = prefix + '/' + rel
                else:
                    arcname = rel
                z.write(full, arcname)

def main():
    # 1. Component zip
    comp_src = os.path.join(BUILD, 'clubleadership')
    comp_zip = os.path.join(OUT, 'com_clubleadership.zip')
    if os.path.exists(comp_zip):
        os.remove(comp_zip)
    zip_dir(comp_src, comp_zip)
    print('wrote', comp_zip, os.path.getsize(comp_zip), 'bytes')

    # 2. Module zip
    mod_src = os.path.join(BUILD, 'mod_clubleadership')
    mod_zip = os.path.join(OUT, 'mod_clubleadership.zip')
    if os.path.exists(mod_zip):
        os.remove(mod_zip)
    zip_dir(mod_src, mod_zip)
    print('wrote', mod_zip, os.path.getsize(mod_zip), 'bytes')

    # 3. Package zip: pkg manifest + the two zips inside
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
