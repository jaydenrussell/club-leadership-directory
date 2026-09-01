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
    private $file;
    private $data = array('records' => array());

    public function __construct($filePath)
    {
        $this->file = $filePath;
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create data directory: ' . $dir);
            }
        }
        if (is_dir($dir)) {
            if (!chmod($dir, 0700)) {
                Log::add('Clubleaddir Store: cannot chmod data directory to 0700: ' . $dir, Log::WARNING, 'com_clubleaddir');
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
                    Log::add('Clubleaddir Store: cannot write .htaccess: ' . $ht, Log::WARNING, 'com_clubleaddir');
                }
            }
            $idx = $dir . '/index.html';
            if (!is_file($idx)) {
                if (file_put_contents($idx, '') === false) {
                    Log::add('Clubleaddir Store: cannot write index.html: ' . $idx, Log::WARNING, 'com_clubleaddir');
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
                    Log::add('Clubleaddir Store: cannot write web.config: ' . $wc, Log::WARNING, 'com_clubleaddir');
                }
            }
        }
        if (is_file($this->file)) {
            $raw = file_get_contents($this->file);
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

        if ((!isset($this->data['records']) || empty($this->data['records'])) && is_file($this->file . '.bak')) {
            $bak = file_get_contents($this->file . '.bak');
            if ($bak !== false) {
                try {
                    $dec = json_decode($bak, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($dec) && isset($dec['records']) && is_array($dec['records'])) {
                        $valid = true;
                        foreach ($dec['records'] as $r) {
                            if (!is_array($r) || !isset($r['id']) || !is_int($r['id'])) {
                                $valid = false;
                                break;
                            }
                        }
                        if ($valid) {
                            $this->data = $dec;
                            $this->save();
                        }
                    }
                } catch (\JsonException $e) {
                    // .bak is also corrupt; leave data empty.
                }
            }
        }

        if (!isset($this->data['records']) || !is_array($this->data['records'])) {
            $this->data['records'] = array();
        }
    }

    private function reload()
    {
        if (!is_file($this->file)) {
            $this->data = array('records' => array());
            return;
        }
        $raw = file_get_contents($this->file);
        if ($raw === false) {
            $this->data = array('records' => array());
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
    }

    private function save()
    {
        $lock = fopen($this->file, 'c');
        if ($lock && flock($lock, LOCK_EX)) {
            if (is_file($this->file) && !copy($this->file, $this->file . '.bak')) {
                Log::add('Clubleaddir Store: cannot create backup: ' . ($this->file . '.bak'), Log::WARNING, 'com_clubleaddir');
            }
            $ok = file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
            flock($lock, LOCK_UN);
            fclose($lock);
            return $ok;
        }
        if ($lock) {
            fclose($lock);
        }
        return false;
    }

    private function nextId()
    {
        $max = 0;
        foreach ($this->data['records'] as $r) {
            if ((int) ($r['id'] ?? 0) > $max) {
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

        $lock = fopen($this->file, 'c');
        if ($lock && flock($lock, LOCK_EX)) {
            $this->reload();
            $id = $this->nextId();
            $filtered['id'] = $id;
            if (!isset($filtered['status'])) {
                $filtered['status'] = 'active';
            }
            if (!isset($filtered['published'])) {
                $filtered['published'] = 1;
            }
            $this->data['records'][] = $filtered;
            $ok = $this->save();
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

        $lock = fopen($this->file, 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reload();
            foreach ($this->data['records'] as &$r) {
                if ((int) $r['id'] === (int) $id) {
                    foreach ($filtered as $k => $v) {
                        $r[$k] = $v;
                    }
                    $ok = $this->save();
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
        $lock = fopen($this->file, 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reload();
            foreach ($this->data['records'] as $i => $r) {
                if ((int) $r['id'] === (int) $id) {
                    unset($this->data['records'][$i]);
                    $this->data['records'] = array_values($this->data['records']);
                    $ok = $this->save();
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
        $lock = fopen($this->file, 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reload();

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

            $ok = $this->save();
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
        $lock = fopen($this->file, 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reload();
            foreach ($this->data['records'] as &$r) {
                if ((int) $r['id'] === (int) $id) {
                    $r['ordering'] = (int) $ordering;
                    $ok = $this->save();
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
        $lock = fopen($this->file, 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) {
                fclose($lock);
            }
            return false;
        }

        try {
            $this->reload();

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

            foreach ($rows as $i => $r) {
                foreach ($this->data['records'] as &$rec) {
                    if ((int) $rec['id'] === (int) $r['id']) {
                        $rec['ordering'] = $i + 1;
                        break;
                    }
                }
                unset($rec);
            }
            $ok = $this->save();
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
                if (!is_dir($dataDir) && mkdir($dataDir, 0700, true) && is_dir($dataDir)) {
                    if (!chmod($dataDir, 0700)) {
                        Log::add('Clubleaddir Store: cannot chmod migrated data directory to 0700: ' . $dataDir, Log::WARNING, 'com_clubleaddir');
                    }
                    copy($oldPath, $newPath);
                }
            }

            self::$instance = new ClubleaddirStoreJson($newPath);
        }

        return self::$instance;
    }
}
