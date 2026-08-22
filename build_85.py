# -*- coding: utf-8 -*-
import os, shutil, subprocess, zipfile

base = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership"
out  = os.path.join(base, "pkg_out", "pkg_clubleaddir.zip")
src  = os.path.join(base, "pkg_build")
if os.path.exists(out): os.remove(out)
if os.path.exists(src): shutil.rmtree(src)
os.makedirs(src)

# --- build nested child zips (Joomla package expects these as files) ---
def build_child(name):
    d = os.path.join(src, "_" + name)
    os.makedirs(d)
    shutil.copytree(os.path.join(base, name), os.path.join(d, name))
    archive = os.path.join(src, name + ".zip")
    old = os.getcwd(); os.chdir(d)
    shutil.make_archive(os.path.join(src, name), "zip", ".")
    os.chdir(old); shutil.rmtree(d)
    return archive

build_child("com_clubleaddir")
build_child("mod_clubleaddir")

# --- package manifest at root ---
shutil.copy2(os.path.join(base, "pkg", "pkg_clubleaddir.xml"), os.path.join(src, "pkg_clubleaddir.xml"))

# --- zip the package (should contain pkg_clubleaddir.xml + com_clubleaddir.zip + mod_clubleaddir.zip) ---
old = os.getcwd()
os.chdir(src)
shutil.make_archive(os.path.join(base, "pkg_out", "pkg_clubleaddir"), "zip", ".")
os.chdir(old)
shutil.rmtree(src)

# --- sanity: confirm nested structure ---
z = zipfile.ZipFile(out)
names = z.namelist()
need = ["pkg_clubleaddir.xml", "com_clubleaddir.zip", "mod_clubleaddir.zip"]
missing = [n for n in need if n not in names]
assert not missing, "missing in package: " + str(missing)
print("BUILT", os.path.getsize(out), "bytes; nested:", [n for n in names if n.endswith('.zip') or n.endswith('.xml')])

# --- commit + push ---
subprocess.run(["git", "add", "-A"], cwd=base, check=True)
r = subprocess.run(["git", "commit", "-m", "Fix package build: nest child zips (com/mod .zip) so full-package install works; drop stray scriptfile"], cwd=base, capture_output=True, text=True)
print(r.stdout.strip() or r.stderr.strip())
subprocess.run(["git", "push"], cwd=base, check=True)
print("PUSHED")
