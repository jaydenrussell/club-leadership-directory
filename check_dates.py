import urllib.request, json, os

TOKEN = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN') or ''
REPO = 'jaydenrussell/club-leadership-directory'
HDRS = {'Authorization': 'token ' + TOKEN, 'Accept': 'application/vnd.github+json'}

url = f'https://api.github.com/repos/{REPO}/releases?per_page=100'
req = urllib.request.Request(url, headers=HDRS)
with urllib.request.urlopen(req) as r:
    releases = json.load(r)

print(f'Total releases: {len(releases)}')
for i, rel in enumerate(releases):
    created = rel.get('created_at', 'N/A')
    published = rel.get('published_at', 'N/A')
    print(f'{i+1}. {rel["tag_name"]} (id={rel["id"]})')
    print(f'   Created: {created}')
    print(f'   Published: {published}')
