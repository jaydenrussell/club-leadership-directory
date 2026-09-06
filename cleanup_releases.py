import urllib.request, json, os, sys, time

TOKEN = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN') or ''
REPO = 'jaydenrussell/club-leadership-directory'
HDRS = {'Authorization': 'token ' + TOKEN, 'Accept': 'application/vnd.github+json'}

# Fetch all releases
url = f'https://api.github.com/repos/{REPO}/releases?per_page=100'
req = urllib.request.Request(url, headers=HDRS)
with urllib.request.urlopen(req) as r:
    releases = json.load(r)

print(f'Total releases found: {len(releases)}')

# Sort by release ID (newest first, which is how GitHub returns them)
# Keep the 10 most recent, delete the rest
to_delete = releases[10:]
to_keep = releases[:10]

print(f'Keeping {len(to_keep)} most recent releases:')
for rel in to_keep:
    print(f'  KEEP: {rel["tag_name"]} (id={rel["id"]})')

print(f'\nDeleting {len(to_delete)} older releases...')

for rel in to_delete:
    tag = rel['tag_name']
    rel_id = rel['id']
    
    # Delete release
    del_url = f'https://api.github.com/repos/{REPO}/releases/{rel_id}'
    req = urllib.request.Request(del_url, headers=HDRS, method='DELETE')
    try:
        with urllib.request.urlopen(req) as r:
            print(f'  Deleted release {tag} (id={rel_id})')
    except urllib.error.HTTPError as e:
        print(f'  Error deleting release {tag}: {e.code} {e.reason}')
    
    # Delete tag
    tag_url = f'https://api.github.com/repos/{REPO}/git/refs/tags/{tag}'
    req2 = urllib.request.Request(tag_url, headers=HDRS, method='DELETE')
    try:
        with urllib.request.urlopen(req2) as r2:
            print(f'  Deleted tag {tag}')
    except urllib.error.HTTPError as e2:
        if e2.code == 422:
            print(f'  Tag {tag} already deleted or not found')
        else:
            print(f'  Error deleting tag {tag}: {e2.code} {e2.reason}')
    
    time.sleep(0.5)  # Rate limit courtesy

print('\nDone')
