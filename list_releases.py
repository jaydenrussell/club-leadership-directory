import urllib.request, json, os, sys

TOKEN = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN') or ''
REPO = 'jaydenrussell/club-leadership-directory'

# Get all releases
url = f'https://api.github.com/repos/{REPO}/releases?per_page=100'
req = urllib.request.Request(url, headers={'Authorization': 'token ' + TOKEN, 'Accept': 'application/vnd.github+json'})
try:
    with urllib.request.urlopen(req) as r:
        releases = json.load(r)
        print(f'Total releases: {len(releases)}')
        for i, rel in enumerate(releases):
            print(f'{i+1}. {rel["tag_name"]} (id={rel["id"]})')
except urllib.error.HTTPError as e:
    print(f'Error: {e.code} {e.reason}')
    sys.exit(1)
