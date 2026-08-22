import re, os
base = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership"
ver = "2.0.92"
for f in ["com_clubleaddir/clubleaddir.xml", "mod_clubleaddir/mod_clubleaddir.xml", "pkg/pkg_clubleaddir.xml"]:
    p = os.path.join(base, f)
    s = open(p, encoding="utf-8").read()
    s2 = re.sub(r"<version>\d+\.\d+\.\d+</version>", "<version>%s</version>" % ver, s)
    assert s2 != s, "version not replaced in " + f
    open(p, "w", encoding="utf-8").write(s2)
print("bumped to", ver)
