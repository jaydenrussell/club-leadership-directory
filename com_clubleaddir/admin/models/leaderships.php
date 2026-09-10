<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

require_once __DIR__ . '/../store/Store.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../zip.php';

class ClubleaddirModelLeaderships extends BaseDatabaseModel
{
    private $store;

    public function __construct($config = array())
    {
        parent::__construct($config);
        try {
            $this->store = ClubleaddirStore::getInstance();
        } catch (\Throwable $e) {
            $this->store = null;
        }
    }

    public function getItems()
    {
        if ($this->store === null) {
            return array();
        }

        $app = Factory::getApplication();
        $filters = array(
            'type'      => $app->input->get('filter_type', '', 'string'),
            'published' => $app->input->get('filter_published', '', 'string'),
            'status'    => $app->input->get('filter_status', '', 'string'),
            'term'      => $app->input->get('filter_term', '', 'string'),
            'search'    => mb_substr($app->input->get('filter_search', '', 'string'), 0, 255),
        );

        $items = $this->store->getAll($filters);

        // Backend ordering: default to manual ordering so drag-to-reorder
        // is WYSIWYG.  The role-rank grouping in sortForDisplay() is for
        // front-end display only; the admin list must show the raw
        // ordering field so Save Order / drag actually persists.
        $orderCol  = $app->input->get('filter_order', 'ordering');
        $orderDirn = $app->input->get('filter_order_Dir', 'asc');
        if (!$orderCol) {
            $orderCol = 'ordering';
        }

        $dir = strtoupper($orderDirn) === 'DESC' ? -1 : 1;
        usort($items, function ($a, $b) use ($orderCol, $dir) {
            $av = $this->orderValue($a, $orderCol);
            $bv = $this->orderValue($b, $orderCol);
            if ($av === $bv) {
                // Tie-breaker keeps groups stable (type then name) when
                // ordering values are equal (e.g. all zeros on first use).
                $ta = strcmp($a->type ?? '', $b->type ?? '');
                if ($ta !== 0) {
                    return $ta;
                }
                return strcmp(strtolower($a->name ?? ''), strtolower($b->name ?? ''));
            }
            return ($av < $bv) ? -$dir : $dir;
        });

        foreach ($items as &$item) {
            $item->type_label = $this->getTypeLabel($item->type);
            $item->type_class = 'badge-' . $item->type;
        }

        return $items;
    }

    private function orderValue($item, $col)
    {
        switch ($col) {
            case 'name':
                return strtolower((string) ($item->name ?: ''));
            case 'role':
                return strtolower((string) ($item->role ?: ''));
            case 'type':
                return strtolower((string) ($item->type ?: ''));
            case 'term':
                return strtolower((string) ($item->term ?: ''));
            case 'published':
                return (int) $item->published;
            case 'ordering':
                return (int) $item->ordering;
            default:
                return strtolower((string) ($item->name ?: ''));
        }
    }

    public function getPagination()
    {
        return null;
    }

    public function getFilterValue($key)
    {
        $app = Factory::getApplication();
        switch ($key) {
            case 'type':      return $app->input->get('filter_type', '', 'string');
            case 'published': return $app->input->get('filter_published', '', 'string');
            case 'status':    return $app->input->get('filter_status', '', 'string');
            case 'term':      return $app->input->get('filter_term', '', 'string');
            case 'search':    return $app->input->get('filter_search', '', 'string');
            default:          return '';
        }
    }

    protected function getTypeLabel($type)
    {
        $labels = array(
            'officer'         => Text::_('COM_CLUBLEADDIR_TYPE_OFFICER'),
            'director'        => Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR'),
            'director_league' => Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR_LEAGUE'),
            'staff'           => Text::_('COM_CLUBLEADDIR_TYPE_STAFF'),
        );
        return $labels[$type] ?? $type;
    }

    public function getTypeOptions()
    {
        return ClubleaddirHelper::getTypeOptions();
    }

    public function getPublishedOptions()
    {
        return ClubleaddirHelper::getPublishedOptions();
    }

    public function getStatusOptions()
    {
        return ClubleaddirHelper::getStatusOptions();
    }

    public function getTermOptions()
    {
        return ClubleaddirHelper::getTermOptions();
    }

    /**
     * Build a portable backup zip: records.json (raw store records) plus
     * every referenced photo, under a photos/ folder. Returns
     * array('file' => temp zip path, 'name' => download filename, 'count' => n)
     * or false on failure.
     */
    public function exportPack()
    {
        if ($this->store === null) {
            return false;
        }

        $records = $this->store->getRawRecords();
        $photoDir = JPATH_ROOT . '/images/clubleaddir/photos';

        $photoNames = array();
        foreach ($records as $r) {
            if (!is_array($r)) {
                continue;
            }
            foreach (array('photo', 'photo_full') as $k) {
                $p = (string) ($r[$k] ?? '');
                if ($p === '' || strpos($p, '/') === false) {
                    continue;
                }
                $base = basename($p);
                if ($this->isAllowedPhotoName($base) && is_file($photoDir . '/' . $base)) {
                    $photoNames[$base] = true;
                }
            }
        }

        $tmpBase = sys_get_temp_dir();
        if (!$tmpBase || !is_dir($tmpBase) || !is_writable($tmpBase)) {
            $cfg = Factory::getConfig();
            $tmpBase = (string) $cfg->get('tmp_path', '');
            if (!$tmpBase || !is_dir($tmpBase) || !is_writable($tmpBase)) {
                $tmpBase = JPATH_ROOT . '/tmp';
            }
        }
        $tmpFile = rtrim($tmpBase, '/\\') . '/clubleaddir-export-' . date('Y-m-d-His') . '-' . bin2hex(random_bytes(4)) . '.zip';

        $manifest = array(
            'format'   => 'clubleaddir-records',
            'version'  => 1,
            'source'   => 'com_clubleaddir',
            'exported' => date('c'),
            'count'    => count($records),
            'records'  => $records,
        );

        $files = array();
        $files['records.json'] = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        foreach (array_keys($photoNames) as $base) {
            $src = $photoDir . '/' . $base;
            if (is_file($src) && is_readable($src)) {
                $content = @file_get_contents($src);
                if ($content !== false) {
                    $files['photos/' . $base] = $content;
                }
            }
        }

        if (!ClubleaddirZip::write($tmpFile, $files)) {
            @unlink($tmpFile);
            return false;
        }

        return array(
            'file'  => $tmpFile,
            'name'  => 'clubleaddir-records-' . date('Y-m-d-H-i-s') . '.zip',
            'count' => count($records),
        );
    }

    /**
     * Replace the store with the contents of an uploaded backup zip.
     * Returns array('imported'=>n,'photos'=>n,'skipped'=>n,'warnings'=>array())
     * on success, or array('error'=>msg) on failure.
     */
    public function importPack(array $fileInfo)
    {
        $result = array('imported' => 0, 'photos' => 0, 'skipped' => 0, 'warnings' => array());

        if (($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            if (($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_NOFILE'));
            }
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT'));
        }
        if (($fileInfo['size'] ?? 0) <= 0 || ($fileInfo['size'] ?? 0) > 52428800) {
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT'));
        }
        $src = (string) ($fileInfo['tmp_name'] ?? '');
        if ($src === '' || !is_file($src)) {
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_NOFILE'));
        }

        $entries = ClubleaddirZip::readAll($src);
        if ($entries === false) {
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_ZIP'));
        }
        $manifestRaw = isset($entries['records.json']) ? $entries['records.json'] : null;
        $manifest    = $manifestRaw !== null ? json_decode($manifestRaw, true) : null;

        if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'clubleaddir-records') {
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT'));
        }
        $rawRecords = (isset($manifest['records']) && is_array($manifest['records'])) ? $manifest['records'] : array();

        $cleaned = array();
        $seen    = array();
        foreach ($rawRecords as $r) {
            if (!is_array($r)) {
                continue;
            }
            $n = $this->normalizeRecord($r, $result['warnings']);
            if ($n === null) {
                $result['skipped']++;
                continue;
            }
            $id = (int) $n['id'];
            if ($id <= 0 || isset($seen[$id])) {
                $result['skipped']++;
                continue;
            }
            $seen[$id] = true;
            $cleaned[] = $n;
            $result['imported']++;
        }

        // Extract photos/ entries from the archive.
        $photoDir = JPATH_ROOT . '/images/clubleaddir/photos';
        if (!is_dir($photoDir) && !mkdir($photoDir, 0700, true) && !is_dir($photoDir)) {
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_PHOTOS_DIR'));
        }

        foreach ($entries as $name => $data) {
            if (strpos($name, 'photos/') !== 0) {
                continue;
            }
            $base = basename($name);
            if ($name !== 'photos/' . $base || !$this->isAllowedPhotoName($base)) {
                $result['warnings'][] = Text::sprintf('COM_CLUBLEADDIR_IMPORT_SKIPPED_PHOTO', $this->escapeQuiet($name));
                continue;
            }
            if (!is_string($data) || $data === '' || strlen($data) > 6291456) {
                $result['warnings'][] = Text::sprintf('COM_CLUBLEADDIR_IMPORT_SKIPPED_PHOTO', $base);
                continue;
            }
            if (!$this->isImageData($data)) {
                $result['warnings'][] = Text::sprintf('COM_CLUBLEADDIR_IMPORT_SKIPPED_PHOTO', $base);
                continue;
            }
            if (file_put_contents($photoDir . '/' . $base, $data) === false) {
                $result['warnings'][] = Text::sprintf('COM_CLUBLEADDIR_IMPORT_SKIPPED_PHOTO', $base);
                continue;
            }
            @chmod($photoDir . '/' . $base, 0600);
            $result['photos']++;
        }

        if ($result['imported'] === 0) {
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT'));
        }
        if ($this->store === null || !$this->store->importAll($cleaned)) {
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_SAVE'));
        }

        $this->logAudit('import', 0, array(
            'imported'      => $result['imported'],
            'photos'        => $result['photos'],
            'skipped'       => $result['skipped'],
            'skipped_photo' => count($result['warnings']),
        ));

        return $result;
    }

    /**
     * Sanitise one imported record to exactly the rules the save path uses.
     * Returns the cleaned array, or null if the record must be skipped.
     */
    private function normalizeRecord(array $r, array &$warnings)
    {
        $id   = (int) ($r['id'] ?? 0);
        $type = (string) ($r['type'] ?? '');
        $valid = array('officer', 'director', 'director_league', 'staff');
        if (!in_array($type, $valid, true)) {
            return null;
        }

        $name = mb_substr(trim((string) ($r['name'] ?? '')), 0, 120);
        $vacant = !empty($r['vacant']) ? 1 : 0;
        if (!$vacant && $name === '') {
            return null;
        }
        if ($type === 'officer') {
            $allowed = array('President', 'Vice President', 'Secretary', 'Treasurer');
            $role = trim((string) ($r['role'] ?? ''));
            if ($role === '' || !in_array($role, $allowed, true)) {
                return null;
            }
        }
        if ($type === 'director_league') {
            $league = trim((string) ($r['league_name'] ?? ''));
            if ($league === '') {
                return null;
            }
        }

        $role = mb_substr(trim((string) ($r['role'] ?? '')), 0, 80);
        $email = mb_substr(trim((string) ($r['email'] ?? '')), 0, 254);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $warnings[] = Text::sprintf('COM_CLUBLEADDIR_IMPORT_BAD_EMAIL', $name);
            $email = '';
        }

        $photoBase = $this->photoFromPath((string) ($r['photo'] ?? ''));
        if ($photoBase === null) {
            $photoBase = '';
        }
        $photoFullBase = $this->photoFromPath((string) ($r['photo_full'] ?? ''));
        if ($photoFullBase === null) {
            $photoFullBase = '';
        }

        $published = (int) ($r['published'] ?? 1);
        if (!in_array($published, array(1, 0, -2), true)) {
            $published = 1;
        }

        return array(
            'id'          => $id,
            'name'        => $name,
            'type'        => $type,
            'role'        => $role,
            'league_name' => mb_substr(trim((string) ($r['league_name'] ?? '')), 0, 40),
            'term'        => mb_substr(trim((string) ($r['term'] ?? '')), 0, 9),
            'bio'         => mb_substr((string) ($r['bio'] ?? ''), 0, 5000),
            'photo'       => $photoBase === '' ? '' : '/images/clubleaddir/photos/' . $photoBase,
            'photo_full'  => $photoFullBase === '' ? '' : '/images/clubleaddir/photos/' . $photoFullBase,
            'email'       => $email,
            'phone'       => mb_substr(preg_replace('/[^0-9+\-\s\(\)]/', '', (string) ($r['phone'] ?? '')), 0, 30),
            'contact_id'  => max(0, (int) ($r['contact_id'] ?? 0)),
            'vacant'      => $vacant,
            'ordering'    => max(0, min(9999, (int) ($r['ordering'] ?? 0))),
            'published'   => $published,
            'status'      => ((string) ($r['status'] ?? 'active')) === 'archived' ? 'archived' : 'active',
            'created'     => mb_substr((string) ($r['created'] ?? ''), 0, 40),
            'modified'    => mb_substr((string) ($r['modified'] ?? ''), 0, 40),
            'created_by'  => max(0, (int) ($r['created_by'] ?? 0)),
            'modified_by' => max(0, (int) ($r['modified_by'] ?? 0)),
        );
    }

    /**
     * Photo filename policy shared by export and import: safe basename +
     * whitelisted image extension only, so a crafted zip cannot write
     * outside the photos directory or plant executable files.
     */
    protected function isAllowedPhotoName($base)
    {
        return is_string($base)
            && $base !== ''
            && strlen($base) <= 200
            && preg_match('/^[A-Za-z0-9._-]+$/', $base) === 1
            && in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'png', 'gif', 'webp'), true);
    }

    protected function isImageData($data)
    {
        $mime = null;
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $mime = finfo_buffer($f, $data);
                finfo_close($f);
            }
        }
        if (!$mime) {
            $tmp = tempnam(sys_get_temp_dir(), 'clubleaddir-img');
            if ($tmp === false) {
                return false;
            }
            file_put_contents($tmp, $data);
            $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : '';
            @unlink($tmp);
        }
        return in_array($mime, array('image/jpeg', 'image/png', 'image/gif', 'image/webp'), true);
    }

    /**
     * Normalise a stored photo path to its photos/ basename, or null if the
     * path does not resolve to an allowed file.
     */
    private function photoFromPath($path)
    {
        if ($path === '' || strpos($path, '/') === false) {
            return null;
        }
        $base = basename($path);
        if (!$this->isAllowedPhotoName($base)) {
            return null;
        }
        return strpos($path, '/images/clubleaddir/photos/') === 0 ? $base : null;
    }

    private function escapeQuiet($txt)
    {
        return htmlspecialchars((string) $txt, ENT_QUOTES, 'UTF-8');
    }

    private function logAudit($action, $id, array $data)
    {
        try {
            $user = Factory::getUser();
            $logDir = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/logs';
            if (!is_dir($logDir) && !mkdir($logDir, 0700, true) && !is_dir($logDir)) {
                return;
            }
            $entry = sprintf(
                "[%s] user=%d action=%s id=%d data=%s\n",
                Factory::getDate()->toSql(),
                (int) $user->id,
                $action,
                (int) $id,
                json_encode($data, JSON_UNESCAPED_SLASHES)
            );
            $file = $logDir . '/audit.log';
            if (is_file($file) && filesize($file) > 10485760) {
                for ($i = 5; $i >= 1; $i--) {
                    $src = $file . ($i === 1 ? '' : '.' . ($i - 1));
                    $dst = $file . '.' . $i;
                    if (is_file($src)) {
                        rename($src, $dst);
                    }
                }
            }
            if (file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) === false) {
                Log::add('Clubleaddir audit log: write failed to: ' . $file, Log::WARNING, 'com_clubleaddir');
            }
        } catch (\Throwable $e) {
            Log::add('Clubleaddir audit log exception: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
        }
    }
}
