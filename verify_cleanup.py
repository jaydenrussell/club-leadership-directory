import urllib.request, json, os, sys

TOKEN = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN') or ''
REPO = 'jaydenrussell/club-leadership-directory'
HDRS = {'Authorization': 'token ' + TOKEN, 'Accept': 'application/vnd.github+json'}

# Fetch all releases
url = f'https://api.github.com/repos/{REPO}/releases?per_page=100'
req = urllib.request.Request(url, headers=HDRS)
with urllib.request.urlopen(req) as r:
    releases = json.load(r)

print(f'Total releases: {len(releases)}')
print('10 most recent releases:')
for i, rel in enumerate(releases[:10]):
    print(f'  {i+1}. {rel["tag_name"]} (id={rel["id"]})')

print(f'\nOldest deleted releases (next 10):')
for i, rel in enumerate(releases[10:20]):
    print(f'  {i+11}. {rel["tag_name"]} (id={rel["id"]})')
