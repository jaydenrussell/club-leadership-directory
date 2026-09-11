import urllib.request, urllib.parse, json, hashlib, os, sys, base64

REPO = "jaydenrussell/club-leadership-directory"
UPDATES_REPO = "jaydenrussell/club-leadership-directory"
UPDATES_BRANCH = "master"
TOKEN = os.environ.get("GH_TOKEN") or os.environ.get("GITHUB_TOKEN") or ""
if not TOKEN:
    print("ERROR: Set GH_TOKEN env var", file=sys.stderr)
    sys.exit(1)
TAG = "v3.28.43"
NAME = f"Club Leadership Directory v3.28.43"
BODY = "Version 3.28.43: fix admin save bugs. Role/Title is now persisted correctly when editing or adding director/staff/league records: the edit form no longer wipes the role field client-side on load (init now preserves the server-rendered value; the role is only cleared when the type is actually changed). The Save button now saves and stays on the entry (new apply-style toolbar labelled 'Save'), with an explicit 'Save & Close' action renamed accordingly; for new entries Save redirects to the actual saved record. The model exposes the newly inserted id (lastSavedId) so redirects land on the created record, and it resets on every save call. New language key COM_CLUBLEADDIR_TOOLBAR_SAVE_CLOSE.

This release also fixes drag-and-drop ordering (the admin list now renders exactly as dropped - Ordering saves correctly) and the ID column sort, and adds a pass of hardening: the admin list/editor views are ACL-gated (core.manage), uninstalling writes the roster backup to the site's global /logs folder (it can no longer be deleted with the component) at owner-only permissions, export staging files and the backup zip are chmod 0600, the admin list paginates past 100 rows (with page-aware reordering), the photo directory .htaccess rules are single-sourced between installer and import, photo URLs are restricted to /images and /media, the export stream no longer dies on max_execution_time, and the single-host JSON storage model is documented. Verified: php -l clean on all touched files, ordering-logic harness passes (WYSIWYG + paginated reorder), no file regressions in the package build."
PKG = str((__import__("pathlib").Path(__file__).parent / "dist" / "pkg_clubleaddir.zip").resolve())
UPDATE_XML = str((__import__("pathlib").Path(__file__).parent / "update.xml").resolve())
HDRS = {"Authorization": "token " + TOKEN, "Accept": "application/vnd.github+json"}
TIMEOUT = 30

# 1. Create release
data = json.dumps({"tag_name": TAG, "name": NAME, "body": BODY, "draft": False, "prerelease": False}).encode()
req = urllib.request.Request(f"https://api.github.com/repos/{REPO}/releases", data=data, headers=HDRS, method="POST")
with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
    rel = json.load(r)
    rel_id = rel["id"]
    upload_url = rel["upload_url"].split("{")[0]
    print(f"Release created: id={rel_id}")

# 2. Upload pkg asset
with open(PKG, "rb") as f:
    asset_data = f.read()
sha = hashlib.sha256(asset_data).hexdigest()
print(f"pkg sha256: {sha}")
params = {"name": os.path.basename(PKG)}
url = f"{upload_url}?{urllib.parse.urlencode(params)}"
req = urllib.request.Request(url, data=asset_data, headers={**HDRS, "Content-Type": "application/zip"}, method="POST")
with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
    print(f"Asset uploaded: {json.load(r)['browser_download_url']}")

pkg_url = f"https://github.com/{REPO}/releases/download/{TAG}/pkg_clubleaddir.zip"

# 3. Push the single update.xml (a standard <update> extension feed) to the main
#    repo on master branch; Joomla's ExtensionAdapter consumes it directly.
def github_push_file(repo, branch, path, content, message):
    api_url = f"https://api.github.com/repos/{repo}/contents/{path}"
    req = urllib.request.Request(api_url, headers=HDRS)
    sha = None
    try:
        with urllib.request.urlopen(req) as r:
            existing = json.load(r)
            sha = existing.get("sha", None)
    except urllib.error.HTTPError as e:
        if e.code == 404:
            sha = None
        else:
            raise

    payload = {
        "message": message,
        "content": base64.b64encode(content.encode("utf-8")).decode("ascii"),
        "branch": branch,
    }
    if sha:
        payload["sha"] = sha

    data = json.dumps(payload).encode()
    req = urllib.request.Request(api_url, data=data, headers={**HDRS, "Content-Type": "application/json"}, method="PUT")
    with urllib.request.urlopen(req) as r:
        result = json.load(r)
        print(f"Updated {path}: {result['content']['html_url']}")

for xml_path, xml_name in [(UPDATE_XML, "update.xml")]:
    with open(xml_path, "r", encoding="utf-8") as f:
        xml_content = f.read()
    github_push_file(UPDATES_REPO, UPDATES_BRANCH, xml_name, xml_content, f"Update {xml_name} to {TAG}")
    print(f"Pushed {xml_name} to {UPDATES_REPO}/{xml_name}")

print("DONE")
