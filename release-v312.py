import urllib.request, urllib.parse, json, hashlib, os, sys, base64

REPO = "jaydenrussell/club-leadership-directory"
UPDATES_REPO = "jaydenrussell/club-leadership-directory"
UPDATES_BRANCH = "master"
TOKEN = os.environ.get("GH_TOKEN") or os.environ.get("GITHUB_TOKEN") or ""
if not TOKEN:
    print("ERROR: Set GH_TOKEN env var", file=sys.stderr)
    sys.exit(1)
TAG = "v3.28.44"
NAME = f"Club Leadership Directory v3.28.44"
BODY = "Version 3.28.44: security release - store data is now kept out of the web root.\n\nStorage relocation: the roster JSON (clubleaddir.json and every by-product), the audit log and the uninstall backup no longer live inside the document root, where anonymous HTTP could download members' names/emails/photos on any server that ignores .htaccess (nginx, LiteSpeed, LAMP-tweaks, etc.). The preferred location is a sibling of the site root (dirname(JPATH_ROOT)/com_clubleaddir-data, never web-served); fallbacks are a hardened JPATH_ROOT/tmp/ or the legacy in-component data folder, each with .htaccess + IIS web.config + empty index.html written on creation. On first load after this update, existing data, its .bak/.meta/snapshot files, the old media/com_clubleaddir/data copy and the audit log migrate out of the web root automatically (one-time, flock-guarded, idempotent). Stale uninstall backups left in /logs by previous versions are removed.\n\nUninstall is now safe: the roster backup is written to a directory that survives the component removal (dirname(JPATH_ROOT)/com_clubleaddir-backups, or hardened /logs), owner-only 0600 with a random filename suffix, and the live store directory is deleted only after a backup actually succeeds. This also fixes a silent data-loss bug where a broken require path made the uninstall backup empty.\n\nmbstring is no longer required: every mb_strlen/mb_substr call now has a plain-PHP fallback, so saves and imports no longer 500 on budget hosts without the extension. Verified on a PHP 8.2 build with no mbstring.\n\nOther fixes: a failed admin save now stays on the form instead of silently discarding the input; editing a record without a photo no longer emits an undefined-key warning; inserts are capped at 50,000 records; ZIP import CRC handling is 32-bit-safe; and the site front-end no longer fatal-errors on a stale store require path.\n\nVerified: php -l clean on all touched files; migration/exposure harness (every former PII URL returns 404 on a static .htaccess-ignoring server); mb-fallback harness (no mbstring installed); CRC harness (MSB-set crc round-trip + corruption rejection); record-cap harness; and the existing store/import/edit harnesses all pass."
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
