import zipfile
z = zipfile.ZipFile('v2.0.147_check.zip')
print('PACKAGE ROOT:')
for n in z.namelist():
    if '/' not in n and '\\' not in n:
        print(' ', n)
for name in ['com_clubleaddir.zip', 'mod_clubleaddir.zip']:
    print('\n' + name + ':')
    for n in z.namelist():
        if name in n.replace('\\','/'):
            print(' ', n)
    inner = zipfile.ZipFile(z.extract(name))
    print(' inner root:')
    for m in inner.namelist():
        if '/' not in m and '\\' not in m:
            print('  ', m)
    print(' all inner:')
    for m in inner.namelist():
        print('  ', m)
