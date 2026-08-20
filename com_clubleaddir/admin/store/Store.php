<?php
/**
 * Standalone data store for Club Leadership.
 *
 * This component intentionally does NOT use the Joomla MySQL database.
 * Records are kept in their own file:
 *   - SQLite database file at JPATH_ROOT . '/media/com_clubleaddir/data/clubleaddir.db'
 *     (fast, real SQL engine, isolated from MySQL)
 *   - or, if the SQLite PDO driver is unavailable on the host, a plain
 *     JSON file at the same folder (zero SQL, physically cannot touch any DB).
 *
 * Either way the data lives OUTSIDE the Joomla/CB MySQL instance, so a bug or
 * compromise here can never affect Joomla core, Community Builder, or any other
 * table. There is no SQL injection surface: SQLite uses prepared statements and
 * the JSON backend performs no query language at all.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Abstract backend. Both the SQLite and JSON implementations expose the same
 * contract so the rest of the component never knows which one is active.
 */
abstract class ClubleaddirStoreBackend
{
    /**
     * Return every record, ordered for display (type, ordering, name).
     *
     * @param   array  $filters  Optional ['type'=>..., 'published'=>..., 'status'=>..., 'search'=>...]
     * @return  array
     */
    abstract public function getAll(array $filters = array());

    /** Return one record by id, or null. */
    abstract public function getById($id);

    /** Insert a record. Returns the new id. */
    abstract public function insert(array $data);

    /** Update an existing record by id. Returns bool. */
    abstract public function update($id, array $data);

    /** Delete one record by id. Returns bool. */
    abstract public function delete($id);

    /** Set published flag (1/0) for one record. Returns bool. */
    abstract public function setPublished($id, $published);

    /** Move a record up (-1) or down (+1) within its type group. Returns bool. */
    abstract public function reorderSingle($id, $direction);

    /** Set an explicit ordering value for one record (native Save Order). Returns bool. */
    abstract public function setOrdering($id, $ordering);

    /** Which backend is in use (for admin display). */
    abstract public function getBackendName();
}

/**
 * SQLite backend.
 */
class ClubleaddirStoreSqlite extends ClubleaddirStoreBackend
{
    private $pdo;

    public function __construct($dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath, null, null, array(
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS records (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
            'name TEXT NOT NULL DEFAULT \'\', ' .
            'type TEXT NOT NULL DEFAULT \'director\', ' .
            'role TEXT NOT NULL DEFAULT \'\', ' .
            'league_name TEXT NOT NULL DEFAULT \'\', ' .
            'term TEXT NOT NULL DEFAULT \'\', ' .
            'start_year INTEGER NOT NULL DEFAULT 0, ' .
            'end_year INTEGER NOT NULL DEFAULT 0, ' .
            'bio TEXT NOT NULL DEFAULT \'\', ' .
            'photo TEXT NOT NULL DEFAULT \'\', ' .
            'photo_full TEXT NOT NULL DEFAULT \'\', ' .
            'email TEXT NOT NULL DEFAULT \'\', ' .
            'phone TEXT NOT NULL DEFAULT \'\', ' .
            'ordering INTEGER NOT NULL DEFAULT 0, ' .
            'published INTEGER NOT NULL DEFAULT 1, ' .
            'status TEXT NOT NULL DEFAULT \'active\', ' .
            'created TEXT NOT NULL DEFAULT \'\', ' .
            'modified TEXT NOT NULL DEFAULT \'\', ' .
            'created_by INTEGER NOT NULL DEFAULT 0, ' .
            'modified_by INTEGER NOT NULL DEFAULT 0' .
            ')'
        );

        // Self-healing migration: a DB file created by an EARLIER version may be
        // missing columns added later (e.g. start_year/end_year). CREATE TABLE
        // uses IF NOT EXISTS, so it will NOT alter an existing table. Add any
        // missing columns here, idempotently, so old data keeps working and the
        // model's update() never hits "no such column".
        $this->migrateSchema();
    }

    /**
     * Add any columns present in the canonical schema but missing from the
     * live table. Safe to call on every request — columns that already exist
     * are skipped. Mirrors the CREATE TABLE column set above.
     */
    private function migrateSchema()
    {
        $expected = array(
            'name'        => "TEXT NOT NULL DEFAULT ''",
            'type'        => "TEXT NOT NULL DEFAULT 'director'",
            'role'        => "TEXT NOT NULL DEFAULT ''",
            'league_name' => "TEXT NOT NULL DEFAULT ''",
            'term'        => "TEXT NOT NULL DEFAULT ''",
            'start_year'  => 'INTEGER NOT NULL DEFAULT 0',
            'end_year'    => 'INTEGER NOT NULL DEFAULT 0',
            'bio'         => "TEXT NOT NULL DEFAULT ''",
            'photo'       => "TEXT NOT NULL DEFAULT ''",
            'photo_full'  => "TEXT NOT NULL DEFAULT ''",
            'email'       => "TEXT NOT NULL DEFAULT ''",
            'phone'       => "TEXT NOT NULL DEFAULT ''",
            'ordering'    => 'INTEGER NOT NULL DEFAULT 0',
            'published'   => 'INTEGER NOT NULL DEFAULT 1',
            'status'      => "TEXT NOT NULL DEFAULT 'active'",
            'created'     => "TEXT NOT NULL DEFAULT ''",
            'modified'    => "TEXT NOT NULL DEFAULT ''",
            'created_by'  => 'INTEGER NOT NULL DEFAULT 0',
            'modified_by' => 'INTEGER NOT NULL DEFAULT 0',
        );

        $existing = array();
        $cols = $this->pdo->query('PRAGMA table_info(records)');
        foreach ($cols as $c) {
            $existing[] = $c['name'];
        }

        foreach ($expected as $col => $def) {
            if (!in_array($col, $existing, true)) {
                $this->pdo->exec('ALTER TABLE records ADD COLUMN ' . $col . ' ' . $def);
            }
        }
    }

    public function getBackendName()
    {
        return 'SQLite';
    }

    public function getAll(array $filters = array())
    {
        $sql  = 'SELECT * FROM records WHERE 1=1';
        $binds = array();

        if (isset($filters['type']) && $filters['type'] !== '' && $filters['type'] !== null) {
            $sql .= ' AND type = :type';
            $binds[':type'] = $filters['type'];
        }
        if (isset($filters['published']) && $filters['published'] !== '' && $filters['published'] !== null) {
            $sql .= ' AND published = :published';
            $binds[':published'] = (int) $filters['published'];
        }
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $sql .= ' AND status = :status';
            $binds[':status'] = $filters['status'];
        }
        if (!empty($filters['term'])) {
            $sql .= ' AND term LIKE :term';
            $binds[':term'] = '%' . $filters['term'] . '%';
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (name LIKE :search OR role LIKE :search)';
            $binds[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY type ASC, ordering ASC, name ASC';

        $stmt = $this->pdo->prepare($sql);
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM records WHERE id = :id');
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function insert(array $data)
    {
        $cols = array('name', 'type', 'role', 'league_name', 'term', 'start_year', 'end_year', 'bio', 'photo', 'photo_full',
            'email', 'phone', 'ordering', 'published', 'status', 'created', 'modified', 'created_by', 'modified_by');

        $colList = array();
        $placeholders = array();
        $binds = array();

        foreach ($cols as $c) {
            $colList[] = $c;
            $placeholders[] = ':' . $c;
            $binds[':' . $c] = isset($data[$c]) ? $data[$c] : ($c === 'published' ? 1 : ($c === 'status' ? 'active' : ''));
        }

        $sql = 'INSERT INTO records (' . implode(', ', $colList) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update($id, array $data)
    {
        $editable = array('name', 'type', 'role', 'league_name', 'term', 'start_year', 'end_year', 'bio', 'photo', 'photo_full',
            'email', 'phone', 'ordering', 'published', 'status', 'modified', 'modified_by');

        $sets = array();
        $binds = array(':id' => (int) $id);
        foreach ($editable as $c) {
            if (array_key_exists($c, $data)) {
                $sets[] = $c . ' = :' . $c;
                $binds[':' . $c] = $data[$c];
            }
        }

        if (empty($sets)) {
            return true;
        }

        $sql = 'UPDATE records SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        return $stmt->execute();
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare('DELETE FROM records WHERE id = :id');
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function setPublished($id, $published)
    {
        return $this->update($id, array('published' => (int) $published));
    }

    public function reorderSingle($id, $direction)
    {
        $row = $this->getById($id);
        if (!$row) {
            return false;
        }

        $dir = $direction < 0 ? 'DESC' : 'ASC';
        $cmp = $direction < 0 ? '<' : '>';
        $ord = $direction < 0 ? 'ORDER BY ordering DESC' : 'ORDER BY ordering ASC';

        // Find the adjacent sibling of the same type.
        $stmt = $this->pdo->prepare(
            'SELECT * FROM records WHERE type = :type AND ordering ' . $cmp . ' :ordering ' . $ord . ' LIMIT 1'
        );
        $stmt->bindValue(':type', $row->type, PDO::PARAM_STR);
        $stmt->bindValue(':ordering', (int) $row->ordering, PDO::PARAM_INT);
        $stmt->execute();
        $neighbor = $stmt->fetch();

        if (!$neighbor) {
            return true; // already at the end of its group
        }

        $tmp = $row->ordering;
        $this->update($id, array('ordering' => $neighbor->ordering));
        $this->update($neighbor->id, array('ordering' => $tmp));

        return true;
    }

    public function setOrdering($id, $ordering)
    {
        $stmt = $this->pdo->prepare('UPDATE records SET ordering = :o WHERE id = :id');
        $stmt->bindValue(':o', (int) $ordering, PDO::PARAM_INT);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

/**
 * JSON file backend (fallback when SQLite PDO is unavailable).
 */
class ClubleaddirStoreJson extends ClubleaddirStoreBackend
{
    private $file;
    private $data = array('records' => array());

    public function __construct($filePath)
    {
        $this->file = $filePath;
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_file($this->file)) {
            $raw = file_get_contents($this->file);
            try {
                $dec = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $dec = null;
            }
            if (is_array($dec) && isset($dec['records']) && is_array($dec['records'])) {
                $this->data = $dec;
            }
        }
        if (!isset($this->data['records']) || !is_array($this->data['records'])) {
            $this->data['records'] = array();
        }
    }

    public function getBackendName()
    {
        return 'JSON';
    }

    private function save()
    {
        return file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    }

    private function nextId()
    {
        $max = 0;
        foreach ($this->data['records'] as $r) {
            if ((int) $r['id'] > $max) {
                $max = (int) $r['id'];
            }
        }
        return $max + 1;
    }

    public function getAll(array $filters = array())
    {
        $out = array();
        foreach ($this->data['records'] as $r) {
            $r = $this->withDefaults($r);
            if (isset($filters['type']) && $filters['type'] !== '' && $filters['type'] !== null && $r['type'] !== $filters['type']) {
                continue;
            }
            if (isset($filters['published']) && $filters['published'] !== '' && $filters['published'] !== null && (int) $r['published'] !== (int) $filters['published']) {
                continue;
            }
            if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null && ($r['status'] ?? 'active') !== $filters['status']) {
                continue;
            }
            if (!empty($filters['search'])) {
                $s = strtolower($filters['search']);
                if (stripos($r['name'], $s) === false && stripos($r['role'], $s) === false) {
                    continue;
                }
            }
            if (!empty($filters['term'])) {
                if (stripos((string) ($r['term'] ?? ''), (string) $filters['term']) === false) {
                    continue;
                }
            }
            $out[] = (object) $r;
        }

        usort($out, function ($a, $b) {
            if ($a->type !== $b->type) {
                return strcmp($a->type, $b->type);
            }
            if ((int) $a->ordering !== (int) $b->ordering) {
                return (int) $a->ordering <=> (int) $b->ordering;
            }
            return strcmp($a->name, $b->name);
        });

        return $out;
    }

    private function withDefaults(array $r)
    {
        static $defaults = array(
            'photo_full' => '',
            'start_year' => 0,
            'end_year'   => 0,
        );
        foreach ($defaults as $k => $v) {
            if (!isset($r[$k])) {
                $r[$k] = $v;
            }
        }
        return $r;
    }

    public function getById($id)
    {
        foreach ($this->data['records'] as $r) {
            if (!is_array($r) || !isset($r['id'])) {
                continue;
            }
            if ((int) $r['id'] === (int) $id) {
                return (object) $this->withDefaults($r);
            }
        }
        return null;
    }

    public function insert(array $data)
    {
        $id = $this->nextId();
        $data['id'] = $id;
        if (!isset($data['status'])) {
            $data['status'] = 'active';
        }
        if (!isset($data['published'])) {
            $data['published'] = 1;
        }
        $this->data['records'][] = $data;
        $this->save();
        return $id;
    }

    public function update($id, array $data)
    {
        foreach ($this->data['records'] as &$r) {
            if ((int) $r['id'] === (int) $id) {
                foreach ($data as $k => $v) {
                    $r[$k] = $v;
                }
                $this->save();
                return true;
            }
        }
        return false;
    }

    public function delete($id)
    {
        foreach ($this->data['records'] as $i => $r) {
            if ((int) $r['id'] === (int) $id) {
                unset($this->data['records'][$i]);
                $this->data['records'] = array_values($this->data['records']);
                $this->save();
                return true;
            }
        }
        return false;
    }

    public function setPublished($id, $published)
    {
        return $this->update($id, array('published' => (int) $published));
    }

    public function reorderSingle($id, $direction)
    {
        $row = $this->getById($id);
        if (!$row) {
            return false;
        }

        $neighbors = array_filter($this->data['records'], function ($r) use ($row, $direction) {
            if ($r['type'] !== $row->type) {
                return false;
            }
            if ($direction < 0) {
                return (int) $r['ordering'] < (int) $row->ordering;
            }
            return (int) $r['ordering'] > (int) $row->ordering;
        });

        if (empty($neighbors)) {
            return true;
        }

        usort($neighbors, function ($a, $b) use ($direction) {
            return $direction < 0
                ? (int) $b['ordering'] <=> (int) $a['ordering']
                : (int) $a['ordering'] <=> (int) $b['ordering'];
        });

        $neighbor = reset($neighbors);
        $tmp = (int) $row->ordering;
        $this->update($id, array('ordering' => (int) $neighbor['ordering']));
        $this->update($neighbor['id'], array('ordering' => $tmp));

        return true;
    }

    public function setOrdering($id, $ordering)
    {
        foreach ($this->data['records'] as &$r) {
            if ((int) $r['id'] === (int) $id) {
                $r['ordering'] = (int) $ordering;
                $this->save();
                return true;
            }
        }
        return false;
    }
}

/**
 * Store factory + dispatcher. The rest of the component talks only to this class.
 */
class ClubleaddirStore
{
    private static $instance = null;

    /**
     * Singleton accessor.
     *
     * @return ClubleaddirStoreBackend
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            $dataDir = JPATH_ROOT . '/media/com_clubleaddir/data';

            if (extension_loaded('pdo_sqlite')) {
                try {
                    self::$instance = new ClubleaddirStoreSqlite($dataDir . '/clubleaddir.db');
                    return self::$instance;
                } catch (\Throwable $e) {
                    // Fall through to JSON on any SQLite failure.
                }
            }

            self::$instance = new ClubleaddirStoreJson($dataDir . '/clubleaddir.json');
        }

        return self::$instance;
    }

    /**
     * Human-readable backend name for the admin info bar.
     *
     * @return string
     */
    public static function backendName()
    {
        try {
            return self::getInstance()->getBackendName();
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }
}
