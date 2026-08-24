# Club Leadership Directory — Joomla 3 Standalone Extension

A Joomla 3 component + module that publishes your club's leadership roster
(officers, directors, league-appointed roles, staff) on the front end.

**Why "standalone"?** The data is stored in its own **SQLite file** under
`media/com_clubleaddir/data/` — completely separate from the Joomla / Community
Builder MySQL database. There is no shared table and no CB dependency, so the
extension can never take down the site database or leak into CB. If the server
lacks the `pdo_sqlite` PHP extension, it automatically falls back to a **JSON**
file with identical behaviour. A corrupted store is quarantined (renamed with a
timestamp suffix) and a fresh one is created — the public page never 500s.

---

## Features

- **Component** (`com_clubleaddir`) — administrator CRUD for leadership records:
  name, role type (Officer / Director / League-Appointed / Staff), league name,
  board term, bio, photo, contact linkage to `com_contact`, publish state and
  manual ordering.
- **Single source of configuration** — every display / contact / vacancy option
  lives in **Components → Club Leadership Directory → Options**
  (`administrator/components/com_clubleaddir/config.xml`). The menu item offers
  only the standard page-heading options; the module offers only position,
  layout and CSS class.
- **Status history** — every record is `active` or `archived`. Archiving a board
  keeps it as a permanent record and removes it from the public display.
- **Module** (`mod_clubleaddir`) — drops the current **published** leadership
  onto any page, grouped by role type, using the same shared renderers as the
  component view.
- **Registered menu item type** ("Leadership Directory") so it appears in
  **Menus → New Item** like any core component view (`site/views/leaderships/metadata.xml`).
- **Vacancy handling** — vacant roles render a Vacant card; enquiries go to one
  global target (a Joomla Contact or a default email). A banner can be shown
  when any role is vacant.
- **Isolated, injection-proof data store** — prepared statements (SQLite) or a
  plain file (JSON). No user input ever reaches a query string.
- **Safe uploads** — photos are validated by real MIME type (`finfo`), capped at
  2 MB, stored under a random name in `images/clubleaddir/photos/` (`.htaccess`
  locked down).

## Requirements

| | |
|---|---|
| Joomla | 3.x (tested against 3.10.x) |
| PHP | 7.4+ recommended |
| Storage | `pdo_sqlite` (preferred) — **or** any PHP install for the JSON fallback |
| Web server | Apache (data + upload folders get generated `.htaccess` files). On nginx/LiteSpeed add your own `deny` rule — see *Security* below. |

## Install (one file, one click)

The extension ships as a **single package**: **`pkg_clubleaddir.zip`**.

1. Download **`pkg_clubleaddir.zip`** from the [Releases](../../releases) page.
2. **System → Extensions → Install**, upload the package.
3. **Components → Club Leadership Directory** to add / edit / archive records;
   use its **Options** button for all display settings.
4. Either publish the **Club Leadership Directory** module, or create a
   **menu item** of type *Club Leadership Directory → Leadership Directory*.
5. Upgrading from v2.x? The installer auto-repairs legacy leftovers: it removes
   the old hidden menu + "Inquire" item, zombie package rows, stale update-site
   entries, root-level debug logs, and re-enables its own extension rows.

## Uninstall behaviour

1. The roster is exported to `images/clubleaddir-backup-YYYYMMDD-HHMM.json`
   first (a message shows where).
2. All code, media, data files, upload folders, own menu items, hidden-menu
   artifacts, logs and orphan manifests are removed.
3. Nothing is written to `#__extensions` beyond this extension's own rows, which
   are removed by Joomla's normal package uninstall.

## Update server

The package manifest points at the GitHub Pages collection
(`update.xml` → `update-full.xml`). To cut a release:

- bump `<version>` in all three manifests (`com_clubleaddir.xml`,
  `mod_clubleaddir.xml`, `pkg/pkg_clubleaddir.xml`),
- run the build, compute the SHA-256 of `dist/pkg_clubleaddir.zip`,
- set `version`, `downloadurl` and `sha256` in `update-full.xml` (+ version in
  `update.xml`),
- tag and attach `dist/pkg_clubleaddir.zip` to the matching GitHub release.

## Build from source

Requires PowerShell 7+:

```powershell
pwsh -NoProfile -File scripts/build.ps1
# produces dist/com_clubleaddir.zip, dist/mod_clubleaddir.zip,
#          dist/pkg_clubleaddir.zip
```

## Security notes

- **No SQL injection surface.** Roster reads/writes go through parameterized
  SQLite statements; the JSON fallback has no query language at all.
- The installer never manipulates `#__update_sites` or hand-inserts extension
  rows — Joomla's own installer handles everything.
- **Output is escaped**; phone numbers are stripped before being placed in a
  `tel:` link.
- **CSRF** protected on every write (POST + token).
- **ACL** gates delete / publish / reorder.
- **Data file exposure:** the installer writes `Deny from all` `.htaccess`
  files into the data and photo folders. On **nginx or LiteSpeed** add your own
  rule, e.g.:

  ```nginx
  location ~* /media/com_clubleaddir/data/ { deny all; }
  ```

## License

GNU General Public License version 2 or later. See [LICENSE](LICENSE).
