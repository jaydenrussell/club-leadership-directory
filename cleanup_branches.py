import urllib.request, json, os

TOKEN = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN') or ''
REPO = 'jaydenrussell/club-leadership-directory'
HDRS = {'Authorization': 'token ' + TOKEN, 'Accept': 'application/vnd.github+json'}

url = f'https://api.github.com/repos/{REPO}/branches'
req = urllib.request.Request(url, headers=HDRS)
with urllib.request.urlopen(req) as r:
    branches = json.load(r)
    branch_names = [b['name'] for b in branches]
    print(f'Branches: {branch_names}')
    
    # Delete all branches except master
    for branch in branches:
        if branch['name'] != 'master':
            print(f'Deleting branch: {branch["name"]}')
            del_url = f'https://api.github.com/repos/{REPO}/git/refs/heads/{branch["name"]}'
            req2 = urllib.request.Request(del_url, headers=HDRS, method='DELETE')
            try:
                with urllib.request.urlopen(req2) as r2:
                    print(f'  Deleted branch {branch["name"]}')
            except urllib.error.HTTPError as e:
                print(f'  Error deleting {branch["name"]}: {e.code} {e.reason}')
