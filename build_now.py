import os, shutil, zipfile
base = os.getcwd()
src  = os.path.join(base, "pkg_build")
out  = os.path.join(base, "pkg_out", "pkg_clubleaddir.zip")
if os.path.exists(out): os.remove(out)
if os.path.exists(src): shutil.rmtree(src)
os.makedirs(src)

com_source = os.path.join(base, "com_clubleaddir")
com_temp = os.path.join(src, "_temp_com")
os.makedirs(com_temp)
for item in os.listdir(com_source):
    if item == "controller.php":
        continue
    srcpath = os.path.join(com_source, item)
    dstpath = os.path.join(com_temp, item)
    if os.path.isdir(srcpath):
        if os.listdir(srcpath):
            shutil.copytree(srcpath, dstpath)
    else:
        shutil.copy2(srcpath, dstpath)

with zipfile.ZipFile(os.path.join(src, "com_clubleaddir.zip"), 'w', zipfile.ZIP_DEFLATED) as zf:
    for root, dirs, files in os.walk(com_temp):
        dirs[:] = [d for d in dirs if os.listdir(os.path.join(root, d))]
        for f in files:
            fp = os.path.join(root, f)
            arc = os.path.relpath(fp, com_temp)
            zf.write(fp, arc)
shutil.rmtree(com_temp)

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
with zipfile.ZipFile(os.path.join(src, "mod_clubleaddir.zip"), 'w', zipfile.ZIP_DEFLATED) as zf:
    for root, dirs, files in os.walk(mod_temp):
        dirs[:] = [d for d in dirs if os.listdir(os.path.join(root, d))]
        for f in files:
            fp = os.path.join(root, f)
            arc = os.path.relpath(fp, mod_temp)
            zf.write(fp, arc)
shutil.rmtree(mod_temp)

shutil.copy2(os.path.join(base, "pkg", "pkg_clubleaddir.xml"), os.path.join(src, "pkg_clubleaddir.xml"))
shutil.copy2(os.path.join(base, "pkg", "script.php"), os.path.join(src, "script.php"))
old = os.getcwd()
os.chdir(src)
shutil.make_archive(os.path.join(base, "pkg_out", "pkg_clubleaddir"), "zip", ".")
os.chdir(old)
shutil.rmtree(src)

z = zipfile.ZipFile(out)
print('root:', [n for n in z.namelist() if '/' not in n and '\\' not in n])
com = zipfile.ZipFile(z.extract('com_clubleaddir.zip'))
print('com root:', [n for n in com.namelist() if '/' not in n and '\\' not in n])
print('size:', os.path.getsize(out))
