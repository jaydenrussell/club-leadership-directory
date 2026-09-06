import urllib.request, json, os

TOKEN = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN') or ''
REPO = 'jaydenrussell/club-leadership-directory'
HDRS = {'Authorization': 'token ' + TOKEN, 'Accept': 'application/vnd.github+json'}

tags_to_check = ['v3.21.9', 'v3.21.8', 'v3.21.7', 'v3.21.6', 'v3.21.5', 
                 'v3.21.4', 'v3.21.3', 'v3.21.2', 'v3.9.0', 'v3.27.4']

for tag in tags_to_check:
    url = f'https://api.github.com/repos/{REPO}/git/refs/tags/{tag}'
    req = urllib.request.Request(url, headers=HDRS)
    try:
        with urllib.request.urlopen(req) as r:
            data = json.load(r)
            print(f'{tag}: EXISTS (ref={data["ref"]}, node_id={data["node_id"]})')
    except urllib.error.HTTPError as e:
        print(f'{tag}: {e.code} {e.reason}')
