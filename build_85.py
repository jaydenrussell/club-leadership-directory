# -*- coding: utf-8 -*-
import os, shutil, subprocess, zipfile, io

base = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership"
out  = os.path.join(base, "pkg_out", "pkg_clubleaddir.zip")
src  = os.path.join(base, "pkg_build")
if os.path.exists(out): os.remove(out)
if os.path.exists(src): shutil.rmtree(src)
os.makedirs(src)

def zip_dir(path, zf, prefix=""):
    """Add directory tree to zip, skipping empty dirs."""
    for root, dirs, files in os.walk(path):
        # Skip empty dirs
        dirs[:] = [d for d in dirs if os.listdir(os.path.join(root, d))]
        for f in files:
            fp = os.path.join(root, f)
            arc = os.path.relpath(fp, path)
            zf.write(fp, arc)

def build_child(name, root_files):
    d = os.path.join(src, "_" + name)
    os.makedirs(d)
    # Copy files/dirs from source
    for item in os.listdir(os.path.join(base, name)):
        srcpath = os.path.join(base, name, item)
        dstpath = os.path.join(d, item)
        if os.path.isdir(srcpath):
            if os.listdir(srcpath):
                shutil.copytree(srcpath, dstpath)
        else:
            shutil.copy2(srcpath, dstpath)
    # Root-level files
    for rf in root_files:
        shutil.copy2(os.path.join(base, rf), os.path.join(d, os.path.basename(rf)))
    # Build zip manually to skip empty dirs
    archive = os.path.join(src, name + ".zip")
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as zf:
        zip_dir(d, zf)
    return archive

build_child("com_clubleaddir", ["com_clubleaddir/clubleaddir.xml", "com_clubleaddir/config.xml"])
build_child("mod_clubleaddir", ["mod_clubleaddir/mod_clubleaddir.xml"])

shutil.copy2(os.path.join(base, "pkg", "pkg_clubleaddir.xml"), os.path.join(src, "pkg_clubleaddir.xml"))
shutil.copy2(os.path.join(base, "pkg", "script.php"), os.path.join(src, "script.php"))

old = os.getcwd()
os.chdir(src)
shutil.make_archive(os.path.join(base, "pkg_out", "pkg_clubleaddir"), "zip", ".")
os.chdir(old); shutil.rmtree(src)

z = zipfile.ZipFile(out)
names = z.namelist()
need = ["pkg_clubleaddir.xml", "com_clubleaddir.zip", "mod_clubleaddir.zip"]
missing = [n for n in need if n not in names]
assert not missing, "missing in package: " + str(missing)
for child in ["com_clubleaddir.zip", "mod_clubleaddir.zip"]:
    cz = zipfile.ZipFile(io.BytesIO(z.read(child)))
    root_names = [n for n in cz.namelist() if "/" not in n]
    print(child, "root files:", root_names)
    manifest = "clubleaddir.xml" if "com" in child else "mod_clubleaddir.xml"
    assert manifest in root_names, child + " missing " + manifest + " at root"

print("BUILT", os.path.getsize(out), "bytes")

subprocess.run(["git", "add", "-A"], cwd=base, check=True)
r = subprocess.run(["git", "commit", "-m", "Fix: use zipfile directly to skip empty dirs in child zips"], cwd=base, capture_output=True, text=True)
print(r.stdout.strip() or r.stderr.strip())
subprocess.run(["git", "push"], cwd=base, check=True)
print("PUSHED")
