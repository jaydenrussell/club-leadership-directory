# Club Leadership Directory — Joomla 3 Standalone Extension

A Joomla 3 component + module that publishes your club's leadership roster
(officers, directors, league-appointed roles, staff) on the front end.

**Why "standalone"?** The data is stored in its own **SQLite file** under
`media/com_clubleaddir/data/` — completely separate from the Joomla / Community
Builder MySQL database. There is no shared table and no CB dependency, so the
extension can never take down the site database or leak into CB. If the server
lacks the `pdo_sqlite` PHP extension, it automatically falls back to a **JSON**
file with identical behaviour.

---

## Features

- **Component** (`com_clubleaddir`) — administrator CRUD for leadership records:
  name, role type (Officer / Director / League-Appointed / Staff), league name,
  board term, bio, photo, role-based contact fields (email / phone / SMS),
  publish state and a manual ordering.
- **Status history** — every record is `active` or `archived`. Archiving a board
  keeps it as a permanent record and removes it from the public display, so you
  never lose the record of a previous board.
- **Module** (`mod_clubleaddir`) — drops the current **published** leadership
  onto any page (typically a sidebar), grouped by role type, ordered by the
  component's ordering field.
- **Isolated, injection-proof data store** — prepared statements (SQLite) or a
  plain file (JSON). No user input ever reaches a query string.
- **Safe uploads** — photos are validated by real MIME type (`finfo`), capped at
  2 MB, stored under a random name.

## Requirements

| | |
|---|---|
| Joomla | 3.x (tested conceptually against 3.10.x) |
| PHP | 7.4+ recommended |
| Storage | `pdo_sqlite` (preferred) — **or** any PHP install for the JSON fallback |
| Web server | Apache (the data folder is protected by a generated `.htaccess`). On nginx/LiteSpeed add your own `deny` rule — see *Security* below. |

## Install (one file, one click)

The extension ships as a **single package**: **`pkg_clubleaddir.zip`**. There
is no separate component or module zip — the package installs (and contains)
both.

1. Download **`pkg_clubleaddir.zip`** from the
   [Releases](../../releases) page.
2. **System → Extensions → Install**, upload the package.
3. The component installs first (it creates the data file), then the module.
4. **Components → Club Leadership** to add / edit / archive records.
5. Publish the **Club Leadership** module to a template position.

A fresh install starts with an empty roster — add your current board, mark the
records `published = Yes` and `status = active`.

## Update

The package is pre-wired to a Joomla update server. After installing, updates
show up under **Components → Joomla! Update** (or **System → Update** depending
on your Joomla 3 build) — click **Find Updates** and install, exactly like
updating core. Re-uploading `pkg_clubleaddir.zip` via **Install** also
upgrades in place (the manifests use `method="upgrade"`).

To cut a new release:
- bump `<version>` in `pkg/pkg_com_clubleaddir.xml` (and the child manifests if you
  changed them),
- update `version` and the `downloadurl` in `update.xml`,
- rebuild + tag + publish the new `pkg_clubleaddir.zip` to a matching
  GitHub release.

## Build from source

The repo holds the raw extension files plus a small Python assembler (no `zip`
binary required). It produces **only** the single package zip:

```bash
cd club-leadership
python3 build_zips.py
# produces ../pkg_out/pkg_clubleaddir.zip  (com + mod nested inside)
```

Install `pkg_out/pkg_clubleaddir.zip`.

## Data location & uninstall

- Data file: `media/com_clubleaddir/data/clubleaddir.sqlite`  (created by the installer)
  (or `clubleaddir.json` in fallback mode).
- On **uninstall** the data file is removed — the extension leaves nothing behind
  in the Joomla database.

## Security notes

- **No SQL injection surface.** All reads/writes go through parameterized SQLite
  statements; the JSON fallback has no query language at all.
- **Output is escaped**; phone numbers are stripped before being placed in a
  `tel:` link.
- **CSRF** protected on every write (POST + token).
- **ACL** gates delete / publish / reorder to users with the appropriate
  permission.
- **Data file exposure:** the installer writes a `Deny from all` `.htaccess`
  into the data folder. This protects you on **Apache**. On **nginx or LiteSpeed**
  add a server rule such as:

  ```nginx
  location ~* /media/com_clubleaddir/data/ { deny all; }
  ```

  The PHP store validates the path on every load and refuses to run if the file
  is not where it expects, but a good server rule is the real backstop.

## License

GNU General Public License version 2 or later. See [LICENSE](LICENSE).
