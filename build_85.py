# -*- coding: utf-8 -*-
import os, shutil, subprocess

base = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership"
out  = os.path.join(base, "pkg_out", "pkg_clubleaddir.zip")
src  = os.path.join(base, "pkg_build")
if os.path.exists(out): os.remove(out)
if os.path.exists(src): shutil.rmtree(src)
os.makedirs(src)

# copy only the package pieces
shutil.copytree(os.path.join(base, "com_clubleaddir"), os.path.join(src, "com_clubleaddir"))
shutil.copytree(os.path.join(base, "mod_clubleaddir"), os.path.join(src, "mod_clubleaddir"))
shutil.copy2(os.path.join(base, "pkg", "pkg_clubleaddir.xml"), os.path.join(src, "pkg_clubleaddir.xml"))

old = os.getcwd()
os.chdir(src)
shutil.make_archive(os.path.join(base, "pkg_out", "pkg_clubleaddir"), "zip", ".")
os.chdir(old)
shutil.rmtree(src)
print("BUILT", os.path.getsize(out), "bytes")

# commit + push (git push may need credential; run with generous timeout)
subprocess.run(["git", "add", "-A"], cwd=base, check=True)
r = subprocess.run(["git", "commit", "-m", "Bump 2.0.85: vacant uses global Vacant Enquiry Contact -> Vacancy Default Email only"], cwd=base, capture_output=True, text=True)
print(r.stdout.strip() or r.stderr.strip())
subprocess.run(["git", "push"], cwd=base, check=True)
print("PUSHED")
