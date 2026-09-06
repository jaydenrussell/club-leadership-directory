import urllib.request, json, os

TOKEN = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN') or ''
REPO = 'jaydenrussell/club-leadership-directory'
HDRS = {'Authorization': 'token ' + TOKEN, 'Accept': 'application/vnd.github+json'}

# Check if v3.27.4 tag exists
url = f'https://api.github.com/repos/{REPO}/git/refs/tags/v3.27.4'
req = urllib.request.Request(url, headers=HDRS)
try:
    with urllib.request.urlopen(req) as r:
        print('v3.27.4 tag exists')
        print(json.load(r))
except urllib.error.HTTPError as e:
    print(f'v3.27.4 tag: {e.code} {e.reason}')

# List all tags
url2 = f'https://api.github.com/repos/{REPO}/git/refs/tags'
req2 = urllib.request.Request(url2, headers=HDRS)
with urllib.request.urlopen(req2) as r2:
    tags = json.load(r2)
    print(f'\nTotal tags: {len(tags)}')
    for tag in tags:
        print(f'  {tag["ref"].replace("refs/tags/", "")}')
