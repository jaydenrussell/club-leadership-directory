# -*- coding: utf-8 -*-
# Fixed build script - creates clean child zips Joomla can install
import os, shutil, zipfile, io, subprocess

base = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership"
out  = os.path.join(base, "pkg_out", "pkg_clubleaddir.zip")
src  = os.path.join(base, "pkg_build")
if os.path.exists(out): os.remove(out)
if os.path.exists(src): shutil.rmtree(src)
os.makedirs(src)

def build_child_zip(name, source_dir):
    """Build a child zip with all files at root level, no staging dirs."""
    archive = os.path.join(src, name + ".zip")
    
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(source_dir):
            # Skip empty directories
            dirs[:] = [d for d in dirs if os.listdir(os.path.join(root, d))]
            
            for f in files:
                fp = os.path.join(root, f)
                # Calculate relative path from source_dir
                arc = os.path.relpath(fp, source_dir)
                zf.write(fp, arc)
    
    return archive

# Build component zip - copy all files from source to flat structure
com_source = os.path.join(base, "com_clubleaddir")
com_temp = os.path.join(src, "_temp_com")
os.makedirs(com_temp)

# Copy everything from component source
for item in os.listdir(com_source):
    srcpath = os.path.join(com_source, item)
    dstpath = os.path.join(com_temp, item)
    if os.path.isdir(srcpath):
        if os.listdir(srcpath):
            shutil.copytree(srcpath, dstpath)
    else:
        shutil.copy2(srcpath, dstpath)

# Build clean component zip
com_zip = build_child_zip("com_clubleaddir", com_temp)
shutil.rmtree(com_temp)

# Build module zip
mod_source = os.path.join(base, "mod_clubleaddir")
mod_temp = os.path.join(src, "_temp_mod")
os.makedirs(mod_temp)

for item in os.listdir(mod_source):
    srcpath = os.path.join(mod_source, item)
    dstpath = os.path.join(mod_temp, item)
    if os.path.isdir(srcpath):
        if os.listdir(srcpath):
            shutil.copytree(srcpath, dstpath)
    else:
        shutil.copy2(srcpath, dstpath)

mod_zip = build_child_zip("mod_clubleaddir", mod_temp)
shutil.rmtree(mod_temp)

# Build package zip
shutil.copy2(os.path.join(base, "pkg", "pkg_clubleaddir.xml"), os.path.join(src, "pkg_clubleaddir.xml"))
shutil.copy2(os.path.join(base, "pkg", "script.php"), os.path.join(src, "script.php"))

# Create final package zip
old = os.getcwd()
os.chdir(src)
shutil.make_archive(os.path.join(base, "pkg_out", "pkg_clubleaddir"), "zip", ".")
os.chdir(old)

# Clean up temp build dir
shutil.rmtree(src)

# Verify package
z = zipfile.ZipFile(out)
print("✓ Package root files:", [n for n in z.namelist() if '/' not in n])
for child in ["com_clubleaddir.zip", "mod_clubleaddir.zip"]:
    cz = zipfile.ZipFile(io.BytesIO(z.read(child)))
    root_names = [n for n in cz.namelist() if '/' not in n]
    print(f"✓ {child} root: {root_names[:5]}...")
    # Verify manifest is at root
    expected = {"com_clubleaddir.xml", "mod_clubleaddir.xml"}
    actual = set(root_names)
    assert expected & actual, f"Missing manifest in {child}"

print(f"✓ Built: {os.path.getsize(out)} bytes")
subprocess.run(["git", "add", "-A"], cwd=base, check=True)
subprocess.run(["git", "commit", "-m", "Fix: rebuild child zips with clean structure"], cwd=base, capture_output=True)
print("✓ Committed")
subprocess.run(["git", "push"], cwd=base, check=True)
print("✓ Pushed")