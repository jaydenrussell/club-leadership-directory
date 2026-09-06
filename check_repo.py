import urllib.request, json, os

TOKEN = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN') or ''
REPO = 'jaydenrussell/club-leadership-directory'
HDRS = {'Authorization': 'token ' + TOKEN, 'Accept': 'application/vnd.github+json'}

# Check repo info
url = f'https://api.github.com/repos/{REPO}'
req = urllib.request.Request(url, headers=HDRS)
try:
    with urllib.request.urlopen(req) as r:
        repo = json.load(r)
        print(f'Repo: {repo["name"]}')
        print(f'Default branch: {repo["default_branch"]}')
except urllib.error.HTTPError as e:
    print(f'Error: {e.code} {e.reason}')

# Try listing tags
url2 = f'https://api.github.com/repos/{REPO}/tags?per_page=100'
req2 = urllib.request.Request(url2, headers=HDRS)
try:
    with urllib.request.urlopen(req2) as r2:
        tags = json.load(r2)
        print(f'\nTotal tags: {len(tags)}')
        for tag in tags:
            print(f'  {tag["name"]}')
except urllib.error.HTTPError as e:
    print(f'Tags error: {e.code} {e.reason}')
