#!/usr/bin/env python3
"""Build script to create a distributable Joomla package."""

import zipfile
import hashlib
from pathlib import Path

# Configuration
REPO_ROOT = Path(__file__).parent
DIST_DIR = REPO_ROOT / "dist"
COMPONENT_DIR = REPO_ROOT / "com_clubleaddir"
MODULE_DIR = REPO_ROOT / "mod_clubleaddir"
PACKAGE_FILE = DIST_DIR / "pkg_clubleaddir.zip"
COMPONENT_PKG = DIST_DIR / "com_clubleaddir.zip"
MODULE_PKG = DIST_DIR / "mod_clubleaddir.zip"
PKG_MANIFEST = REPO_ROOT / "pkg" / "pkg_clubleaddir.xml"

# Ensure dist directory exists
DIST_DIR.mkdir(parents=True, exist_ok=True)

EXCLUDE_COMP = {
    "controller.php",
    "clubleaddir.php",
    "helper.php",
    "views/leaderships.phtml",
    "views/leaderships.css",
}
def zip_directory(src_dir, output_file):
    """Create a zip file from a directory — skips dead orphan files (C-2)."""
    is_component = (src_dir.resolve() == COMPONENT_DIR.resolve())
    exclude = EXCLUDE_COMP if is_component else set()
    with zipfile.ZipFile(output_file, "w", zipfile.ZIP_DEFLATED) as zipf:
        for item in sorted(src_dir.rglob("*")):
            if item.is_file():
                arcname = item.relative_to(src_dir)
                rel_posix = arcname.as_posix()
                if rel_posix in exclude:
                    print(f"  skipping dead file ({src_dir.name}): {rel_posix}")
                    continue
                # Skip hidden/system files
                if any(p.startswith(".") for p in arcname.parts):
                    continue
                zipf.write(item, arcname)

# Create component package
zip_directory(COMPONENT_DIR, COMPONENT_PKG)
print(f"Component package created: {COMPONENT_PKG}")

# Create module package
zip_directory(MODULE_DIR, MODULE_PKG)
print(f"Module package created: {MODULE_PKG}")

# Create main package with nested zips
with zipfile.ZipFile(PACKAGE_FILE, "w", zipfile.ZIP_DEFLATED) as zipf:
    # Add package manifest
    if PKG_MANIFEST.exists():
        zipf.write(PKG_MANIFEST, "pkg_clubleaddir.xml")
        print(f"Added manifest: {PKG_MANIFEST}")
    else:
        print(f"WARNING: Manifest not found at {PKG_MANIFEST}")
    
    # Add component zip
    if COMPONENT_PKG.exists():
        zipf.write(COMPONENT_PKG, "com_clubleaddir.zip")
    
    # Add module zip
    if MODULE_PKG.exists():
        zipf.write(MODULE_PKG, "mod_clubleaddir.zip")

# Calculate SHA256 hash
with open(PACKAGE_FILE, "rb") as f:
    sha256_hash = hashlib.sha256(f.read()).hexdigest()

print(f"Package created: {PACKAGE_FILE}")
print(f"SHA256: {sha256_hash}")

# H-1 — single-source update channel: derive version from manifest and rewrite update files
try:
    import xml.etree.ElementTree as ET
    import re
    comp_xml = REPO_ROOT / "com_clubleaddir" / "com_clubleaddir.xml"
    m = re.search(r"<version>([^<]+)</version>", comp_xml.read_text(encoding="utf-8"))
    ver = m.group(1).strip() if m else "0.0.0"
    # update-full.xml
    full = REPO_ROOT / "update-full.xml"
    if full.exists():
        txt = full.read_text(encoding="utf-8")
        txt = re.sub(r"<version>[^<]+</version>", f"<version>{ver}</version>", txt, count=1)
        txt = re.sub(r"/v[0-9.]+/pkg_clubleaddir\.zip", f"/v{ver}/pkg_clubleaddir.zip", txt)
        txt = re.sub(r"<sha256>[^<]+</sha256>", f"<sha256>{sha256_hash}</sha256>", txt)
        full.write_text(txt, encoding="utf-8")
        print(f"Updated {full} to v{ver}")
    # update.xml
    upd = REPO_ROOT / "update.xml"
    if upd.exists():
        txt = upd.read_text(encoding="utf-8")
        txt = re.sub(r'version="[^"]+"', f'version="{ver}"', txt, count=1)
        upd.write_text(txt, encoding="utf-8")
        print(f"Updated {upd} to v{ver}")
    # remove typo artifact
    typo = DIST_DIR / "mod_clbleaddir.zip"
    if typo.exists():
        typo.unlink()
        print(f"Removed typo artifact {typo}")
except Exception as e:
    print(f"WARNING: update channel auto-write failed: {e}")