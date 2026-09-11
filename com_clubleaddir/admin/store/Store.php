<?php
/**
 * Standalone data store for Club Leadership.
 *
 * This component intentionally does NOT use the Joomla MySQL database.
 * Records are kept in a plain JSON file at:
 *   JPATH_ADMINISTRATOR . '/components/com_clubleaddir/data/clubleaddir.json'
 *
 * The data lives OUTSIDE the Joomla/CB MySQL instance AND outside the web root,
 * so a bug or compromise here can never affect Joomla core, Community Builder,
 * or any other table. There is no SQL injection surface: the JSON backend
 * performs no query language at all. The file is not reachable via HTTP.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

/**
 * JSON file backend.
 */
class ClubleaddirStoreJson
{
    const LOG_LEVEL = 'warning';
    private $file;
    private $data = array('records' => array());
    private $metaFile;
    private $maxId = 0;

    public function __construct($filePath)
    {
        $this->file = $filePath;
        $this->metaFile = $filePath . '.meta';
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create data directory: ' . $dir);
            }
        }
        if (is_dir($dir)) {
            if (!chmod($dir, 0700)) {
                Log::add('Clubleaddir Store: cannot chmod data directory to 0700: ' . $dir, self::LOG_LEVEL, 'com_clubleaddir');
            }
            $ht = $dir . '/.htaccess';
            if (!is_file($ht)) {
                if (file_put_contents($ht,
                    "<Files *>\n"
                    . "    Require all denied\n"
                    . "</Files>\n"
                    . "# Apache 2.2 fallback\n"
                    . "<IfModule !mod_authz_core.c>\n"
                    . "    Deny from all\n"
                    . "</IfModule>\n"
                ) === false) {
                    Log::add('Clubleaddir Store: cannot write .htaccess: ' . $ht, self::LOG_LEVEL, 'com_clubleaddir');
                }
            }
            $idx = $dir . '/index.html';
            if (!is_file($idx)) {
                if (file_put_contents($idx, '') === false) {
                    Log::add('Clubleaddir Store: cannot write index.html: ' . $idx, self::LOG_LEVEL, 'com_clubleaddir');
                }
            }
            $wc = $dir . '/web.config';
            if (!is_file($wc)) {
                if (file_put_contents($wc,
                    "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                    . "<configuration>\n"
                    . "    <system.webServer>\n"
                    . "        <security>\n"
                    . "            <requestFiltering>\n"
                    . "                <hiddenSegments>\n"
                    . "                    <add segment=\"data\" />\n"
                    . "                </hiddenSegments>\n"
                    . "            </requestFiltering>\n"
                    . "        </security>\n"
                    . "    </system.webServer>\n"
                    . "</configuration>\n"
                ) === false) {
                    Log::add('Clubleaddir Store: cannot write web.config: ' . $wc, self::LOG_LEVEL, 'com_clubleaddir');
                }
            }
        }
        if (is_file($this->file)) {
            $lock = fopen($this->file, 'c+');
            if ($lock && flock($lock, LOCK_SH)) {
                $raw = file_get_contents($this->file);
                flock($lock, LOCK_UN);
                fclose($lock);
            } else {
                if ($lock) { fclose($lock); }
                $raw = false;
            }

            if ($raw === false) {
                error_log('Clubleaddir JSON unreadable: ' . $this->file);
                $raw = '';
            }
            $dec    = null;
            $broken = false;

            try {
                $dec = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $broken = true;
            }

            if (is_array($dec) && isset($dec['records']) && is_array($dec['records'])) {
                foreach ($dec['records'] as $r) {
                    if (!is_array($r) || !isset($r['id']) || !is_int($r['id'])) {
                        $broken = true;
                        break;
                    }
                }
                if (!$broken) {
                    $this->data = $dec;
                }
            } else {
                $broken = true;
            }

            if ($broken && !empty($raw)) {
                $quarantine = $this->file . '.corrupt-' . date('Ymd-His');
                error_log('Clubleaddir JSON quarantined: ' . $this->file . ' -> ' . $quarantine);
                rename($this->file, $quarantine);
            }
        }

        if ((!isset($this->data['records']) || empty($this->data['records']))) {
            if (is_file($this->file . '.tmp')) {
                if (!$this->recoverFromBackup($this->file . '.tmp')) {
                    $this->recoverFromBackup($this->file . '.bak');
                }
            } else {
                foreach (array('.bak', '.bak.1', '.bak.2') as $gen) {
                    if ($this->recoverFromBackup($this->file . $gen)) {
                        break;
                    }
                }
            }
        }

        if (!isset($this->data['records']) || !is_array($this->data['records'])) {
            $this->data['records'] = array();
        }

        $this->loadMaxId();
    }

    /**
     * Validate a backup/tmp copy and, if good, restore it as the live data.
     * Returns true when the copy was recovered (and persisted).
     */
    private function recoverFromBackup($path)
    {
        if (!is_file($path)) {
            return false;
        }
        $lock = fopen($path, 'c');
        if ($lock && flock($lock, LOCK_SH)) {
            $raw = file_get_contents($path);
            flock($lock, LOCK_UN);
            fclose($lock);
        } else {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }
        if ($raw === false || $raw === '') {
            return false;
        }
        try {
            $dec = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return false;
        }
        if (!is_array($dec) || !isset($dec['records']) || !is_array($dec['records'])) {
            return false;
        }
        foreach ($dec['records'] as $r) {
            if (!is_array($r) || !isset($r['id']) || !is_int($r['id'])) {
                return false;
            }
        }
        $this->data = $dec;
        $this->save();
        return true;
    }

    /**
     * Read the raw on-disk JSON through an already-open (locked) handle.
     *
     * Single-open invariant: inside a locked section we MUST NOT re-open the
     * file. Opening a second descriptor (fopen/file_get_contents/copy) and
     * flock()ing it against the LOCK_EX already held on the first one hangs
     * forever: flock() treats each file descriptor independently, so a nested
     * request blocks on the caller's own lock (observed on Linux AND Windows).
     * This self-deadlock used to hang every insert/update/delete/reorder, which
     * presented as "Joomla crashes when saving a record" (PHP timeout -> 500).
     */
    private function readRawFromLock($lock)
    {
        rewind($lock);
        $raw = stream_get_contents($lock);
        return ($raw === false) ? '' : $raw;
    }

    /**
     * Re-read the JSON file into $this->data through the locked handle.
     * Only call while holding the exclusive lock on $lock.
     */
    private function reloadFromLock($lock)
    {
        $raw = $this->readRawFromLock($lock);
        if ($raw === '') {
            $this->data = array('records' => array());
            $this->loadMaxId();
            return;
        }
        try {
            $dec = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($dec) && isset($dec['records']) && is_array($dec['records'])) {
                $this->data = $dec;
            } else {
                $this->data = array('records' => array());
            }
        } catch (\JsonException $e) {
            $this->data = array('records' => array());
        }
        $this->loadMaxId();
    }

    /**
     * Flush $this->data to disk through the SAME locked handle the caller
     * already holds (single-open invariant, see readRawFromLock()). The
     * complete new content is staged to {file}.tmp before the live file is
     * truncated, so an interrupted write never loses data: the finished copy
     * is on disk at {file}.tmp and the prior state survives in {file}.bak
     * plus two older generations.
     */
    private function writeToLock($lock)
    {
        $rawBefore = $this->readRawFromLock($lock);
        $validPrior = false;
        if ($rawBefore !== '') {
            try {
                $dec = json_decode($rawBefore, true, 512, JSON_THROW_ON_ERROR);
                $validPrior = is_array($dec) && isset($dec['records']) && is_array($dec['records']);
            } catch (\JsonException $e) {
                $validPrior = false;
            }
        }
        if ($validPrior && !$this->rotateBackup($rawBefore)) {
            Log::add('Clubleaddir Store: cannot create backup, aborting save: ' . ($this->file . '.bak'), self::LOG_LEVEL, 'com_clubleaddir');
            return false;
        }
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $tmp = $this->file . '.tmp';
        $out = fopen($tmp, 'wb');
        if ($out === false) {
            Log::add('Clubleaddir Store: cannot stage write (fopen failed): ' . $tmp, self::LOG_LEVEL, 'com_clubleaddir');
            return false;
        }
        $written = fwrite($out, $json);
        fclose($out);
        if ($written === false || $written !== strlen($json)) {
            @unlink($tmp);
            Log::add('Clubleaddir Store: short write staging (wrote ' . ($written ?? 0) . ' of ' . strlen($json) . ' bytes): ' . $tmp, self::LOG_LEVEL, 'com_clubleaddir');
            return false;
        }
        if (!ftruncate($lock, 0)) {
            @unlink($tmp);
            return false;
        }
        rewind($lock);
        $len    = strlen($json);
        $cursor = 0;
        while ($cursor < $len) {
            $n = fwrite($lock, substr($json, $cursor));
            if ($n === false || $n === 0) {
                @unlink($tmp);
                return false;
            }
            $cursor += $n;
        }
        fflush($lock);
        @unlink($tmp);
        $this->saveMaxId();
        return true;
    }

    /**
     * Rotate the single .bak into a 3-generation history, then write the new
     * pre-write copy, so several consecutive failure windows stay recoverable.
     */
    private function rotateBackup($rawBefore)
    {
        $bak = $this->file . '.bak';
        @unlink($bak . '.2');
        if (is_file($bak . '.1')) {
            @rename($bak . '.1', $bak . '.2');
        }
        if (is_file($bak)) {
            @rename($bak, $bak . '.1');
        }
        return file_put_contents($bak, $rawBefore) !== false;
    }

    /**
     * Standalone atomic save when the caller does NOT already hold the lock
     * (currently only the constructor's .bak recovery path). Acquires the
     * exclusive lock once on a single handle and writes through it.
     */
    private function save()
    {
        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }
        $ok = $this->writeToLock($lock);
        flock($lock, LOCK_UN);
        fclose($lock);
        return $ok;
    }

    private function nextId()
    {
        if ($this->maxId > 0) {
            $this->maxId++;
            $this->saveMaxId();
            return $this->maxId;
        }
        $max = 0;
        foreach ($this->data['records'] as $r) {
            if ((int) ($r['id'] ?? 0) > $max) {
                $max = (int) $r['id'];
            }
        }
        $this->maxId = $max;
        $this->maxId++;
        $this->saveMaxId();
        return $this->maxId;
    }

    private function loadMaxId()
    {
        $this->maxId = 0;
        if (is_file($this->metaFile)) {
            $raw = file_get_contents($this->metaFile);
            if ($raw !== false) {
                $dec = json_decode($raw, true);
                if (is_array($dec) && isset($dec['max_id']) && is_int($dec['max_id'])) {
                    $this->maxId = $dec['max_id'];
                }
            } else {
                Log::add('Clubleaddir Store: cannot read meta file: ' . $this->metaFile, self::LOG_LEVEL, 'com_clubleaddir');
            }
        }
        if ($this->maxId === 0) {
            foreach ($this->data['records'] as $r) {
                if ((int) ($r['id'] ?? 0) > $this->maxId) {
                    $this->maxId = (int) $r['id'];
                }
            }
        }
    }

    private function saveMaxId()
    {
        $result = file_put_contents($this->metaFile, json_encode(array('max_id' => $this->maxId), JSON_PRETTY_PRINT));
        if ($result === false) {
            Log::add('Clubleaddir Store: cannot write meta file: ' . $this->metaFile, self::LOG_LEVEL, 'com_clubleaddir');
        } elseif (is_file($this->metaFile)) {
            if (!chmod($this->metaFile, 0600)) {
                Log::add('Clubleaddir Store: cannot chmod meta file to 0600: ' . $this->metaFile, self::LOG_LEVEL, 'com_clubleaddir');
            }
        }
    }

    /**
     * Return the raw stored record arrays (pre-defaults) for export.
     * The in-memory copy is read directly; safe for an admin export request.
     */
    public function getRawRecords()
    {
        return $this->data['records'];
    }

    public function getFilePath()
    {
        return $this->file;
    }

    /**
     * Write a crash-safe copy of the store to {file}.{suffix}.{timestamp}
     * and prune to the 3 newest of that suffix. Used before destructive
     * operations (import) so a bad replace can be recovered without relying
     * on the next write clobbering .bak. Reads only; never re-opens the
     * data file inside a nested lock.
     */
    public function snapshot($suffix)
    {
        $sfx = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $suffix);
        if ($sfx === '') {
            return false;
        }
        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }
        $raw = $this->readRawFromLock($lock);
        flock($lock, LOCK_UN);
        fclose($lock);
        if ($raw === '' || $raw === false) {
            return false;
        }
        $dest = $this->file . '.' . $sfx . '.' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        if (file_put_contents($dest, $raw) === false) {
            return false;
        }
        $list = glob($this->file . '.' . $sfx . '.*');
        if ($list && count($list) > 3) {
            usort($list, function ($a, $b) {
                return (int) filemtime($b) - (int) filemtime($a);
            });
            foreach (array_slice($list, 3) as $old) {
                @unlink($old);
            }
        }
        return true;
    }

    /**
     * Replace the entire records set in one locked write (import).
     * Records must already be sanitised and carry unique positive int ids.
     */
    public function importAll(array $records)
    {
        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }
        try {
            $this->reloadFromLock($lock);
            $this->data['records'] = array_values($records);
            $ok = $this->writeToLock($lock);
            flock($lock, LOCK_UN);
            fclose($lock);
            return $ok;
        } catch (\Throwable $e) {
            flock($lock, LOCK_UN);
            fclose($lock);
            Log::add('Clubleaddir Store: importAll failed: ' . $e->getMessage(), self::LOG_LEVEL, 'com_clubleaddir');
            return false;
        }
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
            'bio'        => '',
            'phone'      => '',
            'email'      => '',
            'vacant'     => 0,
            'status'     => 'active',
            'role'       => '',
            'league_name'=> '',
            'term'       => '',
            'photo'      => '',
            'contact_id' => 0,
            'ordering'   => 0,
            'published'  => 1,
            'created'    => '',
            'modified'   => '',
            'created_by' => 0,
            'modified_by'=> 0,
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
        $allowed = array('name', 'type', 'role', 'league_name', 'term', 'start_year', 'end_year', 'bio', 'photo', 'photo_full',
            'email', 'phone', 'contact_id', 'vacant', 'ordering', 'published', 'status', 'created', 'modified', 'created_by', 'modified_by');
        $filtered = array();
        foreach ($allowed as $c) {
            if (array_key_exists($c, $data)) {
                $filtered[$c] = $data[$c];
            }
        }

        $lock = fopen($this->file, 'c+');
        if ($lock && flock($lock, LOCK_EX)) {
            $this->reloadFromLock($lock);
            $id = $this->nextId();
            $filtered['id'] = $id;
            if (!isset($filtered['status'])) {
                $filtered['status'] = 'active';
            }
            if (!isset($filtered['published'])) {
                $filtered['published'] = 1;
            }
            $this->data['records'][] = $filtered;
            $ok = $this->writeToLock($lock);
            flock($lock, LOCK_UN);
            fclose($lock);
            return $ok ? $id : false;
        }

        if ($lock) {
            fclose($lock);
        }
        return false;
    }

    public function update($id, array $data)
    {
        $allowed = array('name', 'type', 'role', 'league_name', 'term', 'start_year', 'end_year', 'bio', 'photo', 'photo_full',
            'email', 'phone', 'contact_id', 'vacant', 'ordering', 'published', 'status', 'modified', 'modified_by');
        $filtered = array();
        foreach ($allowed as $c) {
            if (array_key_exists($c, $data)) {
                $filtered[$c] = $data[$c];
            }
        }

        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reloadFromLock($lock);
            foreach ($this->data['records'] as &$r) {
                if ((int) $r['id'] === (int) $id) {
                    foreach ($filtered as $k => $v) {
                        $r[$k] = $v;
                    }
                    $ok = $this->writeToLock($lock);
                    flock($lock, LOCK_UN);
                    fclose($lock);
                    return $ok;
                }
            }
            unset($r);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        } catch (\Throwable $e) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $e;
        }
    }

    public function delete($id)
    {
        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reloadFromLock($lock);
            foreach ($this->data['records'] as $i => $r) {
                if ((int) $r['id'] === (int) $id) {
                    unset($this->data['records'][$i]);
                    $this->data['records'] = array_values($this->data['records']);
                    $ok = $this->writeToLock($lock);
                    flock($lock, LOCK_UN);
                    fclose($lock);
                    return $ok;
                }
            }
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        } catch (\Throwable $e) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $e;
        }
    }

    public function setPublished($id, $published)
    {
        return $this->update($id, array('published' => (int) $published));
    }

    public function reorderSingle($id, $direction)
    {
        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reloadFromLock($lock);

            $row = null;
            foreach ($this->data['records'] as $r) {
                if ((int) $r['id'] === (int) $id) {
                    $row = $r;
                    break;
                }
            }
            if (!$row) {
                flock($lock, LOCK_UN);
                fclose($lock);
                return false;
            }

            $neighbors = array_filter($this->data['records'], function ($r) use ($row, $direction) {
                if ($r['type'] !== $row['type']) {
                    return false;
                }
                if ($direction < 0) {
                    return (int) $r['ordering'] < (int) $row['ordering'];
                }
                return (int) $r['ordering'] > (int) $row['ordering'];
            });

            if (empty($neighbors)) {
                flock($lock, LOCK_UN);
                fclose($lock);
                return true;
            }

            usort($neighbors, function ($a, $b) use ($direction) {
                return $direction < 0
                    ? (int) $b['ordering'] <=> (int) $a['ordering']
                    : (int) $a['ordering'] <=> (int) $b['ordering'];
            });

            $neighbor = reset($neighbors);
            $tmp = (int) $row['ordering'];
            $newOrd = (int) $neighbor['ordering'];

            foreach ($this->data['records'] as &$r) {
                if ((int) $r['id'] === (int) $id) {
                    $r['ordering'] = $newOrd;
                } elseif ((int) $r['id'] === (int) $neighbor['id']) {
                    $r['ordering'] = $tmp;
                }
            }
            unset($r);

            $ok = $this->writeToLock($lock);
            flock($lock, LOCK_UN);
            fclose($lock);
            return $ok;
        } catch (\Throwable $e) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $e;
        }
    }

    public function setOrdering($id, $ordering)
    {
        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reloadFromLock($lock);
            foreach ($this->data['records'] as &$r) {
                if ((int) $r['id'] === (int) $id) {
                    $r['ordering'] = (int) $ordering;
                    $ok = $this->writeToLock($lock);
                    flock($lock, LOCK_UN);
                    fclose($lock);
                    return $ok;
                }
            }
            unset($r);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        } catch (\Throwable $e) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $e;
        }
    }

    public function reorderAll($type = null)
    {
        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reloadFromLock($lock);

            $rows = $this->data['records'];
            if ($type !== null) {
                $rows = array_filter($rows, function ($r) use ($type) {
                    return $r['type'] === $type;
                });
            }

            usort($rows, function ($a, $b) {
                $oa = (int) ($a['ordering'] ?? 0);
                $ob = (int) ($b['ordering'] ?? 0);
                if ($oa !== $ob) {
                    return $oa <=> $ob;
                }
                return strcmp($a['name'] ?? '', $b['name'] ?? '');
            });

            $byId = array();
            foreach ($this->data['records'] as $idx => $rec) {
                $byId[(int) $rec['id']] = $idx;
            }

            foreach ($rows as $i => $r) {
                $id = (int) $r['id'];
                if (isset($byId[$id])) {
                    $this->data['records'][$byId[$id]]['ordering'] = $i + 1;
                }
            }

            $ok = $this->writeToLock($lock);
            flock($lock, LOCK_UN);
            fclose($lock);
            return $ok;
        } catch (\Throwable $e) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $e;
        }
    }

    /**
     * Atomically set ordering for a batch of records and normalize.
     *
     * Holds ONE exclusive lock for the entire batch so concurrent
     * requests cannot interleave changes between individual writes.
     */
    public function saveOrderAll(array $pks, array $order)
    {
        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reloadFromLock($lock);

            foreach ($pks as $i => $pk) {
                $ord = isset($order[$i]) ? (int) $order[$i] : 0;
                $ord = max(0, min(9999, $ord));
                foreach ($this->data['records'] as &$r) {
                    if ((int) $r['id'] === (int) $pk) {
                        $r['ordering'] = $ord;
                        break;
                    }
                }
                unset($r);
            }

            $ok = $this->writeToLock($lock);
            flock($lock, LOCK_UN);
            fclose($lock);
            return $ok;
        } catch (\Throwable $e) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $e;
        }
    }

    /**
     * WYSIWYG variant for drag-to-reorder (sortablelist AJAX). Rewrites the
     * posted records to a strict 1..N sequence in the posted cid[] order, so
     * the admin list renders exactly as dropped even when the JS recalc
     * leaves duplicate values behind (cross-type ties from per-type
     * ordering namespaces).
     */
    public function saveOrderAllWysiwyg(array $pks, array $order, $offset = 0)
    {
        $lock = fopen($this->file, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reloadFromLock($lock);

            // Strict 1..N in the posted cid[] order, starting after $offset so
            // a paginated drop renumbers only the current page's rows (page 2
            // continues where the invisible page 1 left off).
            foreach ($pks as $i => $pk) {
                $pk = (int) $pk;
                if ($pk <= 0) {
                    continue;
                }
                $ord = (int) $offset + $i + 1;
                foreach ($this->data['records'] as &$r) {
                    if ((int) $r['id'] === $pk) {
                        $r['ordering'] = $ord;
                        break;
                    }
                }
                unset($r);
            }

            $ok = $this->writeToLock($lock);
            flock($lock, LOCK_UN);
            fclose($lock);
            return $ok;
        } catch (\Throwable $e) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $e;
        }
    }
}

/**
 * Store factory. The rest of the component talks only to this class.
 */
class ClubleaddirStore
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            $dataDir = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/data';
            $newPath = $dataDir . '/clubleaddir.json';
            $oldPath = JPATH_ROOT . '/media/com_clubleaddir/data/clubleaddir.json';

            if (!is_file($newPath) && is_file($oldPath)) {
                $lockPath = $dataDir . '/migrate.lock';
                $lock = fopen($lockPath, 'c');
                if ($lock && flock($lock, LOCK_EX)) {
                    if (!is_file($newPath) && is_file($oldPath)) {
                        if (!is_dir($dataDir) && mkdir($dataDir, 0700, true) && is_dir($dataDir)) {
                            if (!chmod($dataDir, 0700)) {
                                Log::add('Clubleaddir Store: cannot chmod migrated data directory to 0700: ' . $dataDir, self::LOG_LEVEL, 'com_clubleaddir');
                            }
                        }
                        if (is_dir($dataDir)) {
                            copy($oldPath, $newPath);
                            copy($oldPath, $newPath . '.bak');
                            $bakContent = file_get_contents($newPath . '.bak');
                            if ($bakContent !== false) {
                                try {
                                    $dec = json_decode($bakContent, true, 512, JSON_THROW_ON_ERROR);
                                    if (!is_array($dec['records'] ?? null)) {
                                        unlink($newPath . '.bak');
                                    }
                                } catch (\JsonException $e) {
                                    unlink($newPath . '.bak');
                                }
                            }
                        }
                    }
                    flock($lock, LOCK_UN);
                    fclose($lock);
                } elseif ($lock) {
                    fclose($lock);
                }
            }

            self::$instance = new ClubleaddirStoreJson($newPath);
        }

        return self::$instance;
    }
}
