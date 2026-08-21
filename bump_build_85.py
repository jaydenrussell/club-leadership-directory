# -*- coding: utf-8 -*-
import re, os, subprocess, glob
base = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership"
ver = "2.0.85"
for f in ["com_clubleaddir/clubleaddir.xml","mod_clubleaddir/mod_clubleaddir.xml","pkg/pkg_clubleaddir.xml"]:
    p = os.path.join(base, f)
    s = open(p, encoding="utf-8").read()
    s2 = re.sub(r'(<version>)\d+\.\d+\.\d+(</version>)', r'\g<1>'+ver+r'\g<2>', s, count=1)
    if s2 == s:
        # already at target version; ensure it really is
        assert ver in s, "version not bumped in "+f
    open(p, "w", encoding="utf-8").write(s2)
print("versions bumped to", ver)

# build zip
import shutil
out = os.path.join(base, "pkg_out", "pkg_clubleaddir.zip")
if os.path.exists(out):
    os.remove(out)
old = os.getcwd()
os.chdir(base)
subprocess.run(["python3","-c","import shutil,os; os.makedirs('pkg_build',exist_ok=True); shutil.make_archive('pkg_out/pkg_clubleaddir','zip','.')"], check=True)
os.chdir(old)
print("BUILT", os.path.getsize(out), "bytes")

# github push
subprocess.run(["git","add","-A"], cwd=base, check=True)
r = subprocess.run(["git","commit","-m","Bump 2.0.85: vacant uses global Vacant Enquiry Contact -> Vacancy Default Email only"], cwd=base, capture_output=True, text=True)
print(r.stdout.strip() or r.stderr.strip())
subprocess.run(["git","push"], cwd=base, check=True)
print("PUSHED")
