import zipfile, io

z = zipfile.ZipFile('pkg_out/pkg_clubleaddir.zip')
print('PACKAGE ROOT FILES:')
for n in z.namelist():
    if '/' not in n and '\\' not in n:
        print(' ', n)

print('\ncom_clubleaddir.zip paths:')
for n in z.namelist():
    if 'com_clubleaddir.zip' in n.replace('\\','/'):
        print(' ', n)

print('\nmod_clubleaddir.zip paths:')
for n in z.namelist():
    if 'mod_clubleaddir.zip' in n.replace('\\','/'):
        print(' ', n)

# Check if controller.php is inside component zip
cz = zipfile.ZipFile(io.BytesIO(z.read('com_clubleaddir.zip')))
print('\ncom_clubleaddir.zip files containing controller:')
for n in cz.namelist():
    if 'controller' in n.lower():
        print(' ', n)
        print('    size:', len(cz.read(n)))
