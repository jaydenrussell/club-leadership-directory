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
    /** Hard ceiling on imported records; prevents decode/cleanup amplification. */
    const MAX_RECORDS = 50000;

    /** Byte cap for the legacy records.json entry (whole-array JSON decode). */
    const LEGACY_MANIFEST_CAP = 2097152;

    /** Warnings are surfaced, never allowed to grow without bound. */
    const MAX_WARNINGS = 100;

    /** Above this many filtered rows the admin list paginates, keeping every
     *  render bounded and drag-reorder page-aware via limitstart offsets. */
    const LIST_PAGE_LIMIT = 100;

    private $store;
    private $total;
    private $limitstart;
    private $pageLimit;

    private function addWarning(array &$result, $msg)
    {
        if (count($result['warnings']) < self::MAX_WARNINGS) {
            $result['warnings'][] = $msg;
        }
    }

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
            'search'    => ClubleaddirStore::mbSubstr($app->input->get('filter_search', '', 'string'), 0, 255),
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

        $this->total = count($items);
        return $this->paginate($items);
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
            case 'id':
                return (int) $item->id;
            case 'ordering':
                return (int) $item->ordering;
            default:
                return strtolower((string) ($item->name ?: ''));
        }
    }

    public function getPagination()
    {
        if ($this->store === null || $this->total === null || $this->total <= self::LIST_PAGE_LIMIT) {
            return null;
        }
        try {
            return new \JPagination($this->getTotal(), (int) $this->limitstart, (int) $this->pageLimit);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getTotal()
    {
        if ($this->total === null) {
            $this->getItems();
        }
        return (int) $this->total;
    }

    public function getLimitStart()
    {
        if ($this->limitstart === null) {
            $this->getItems();
        }
        return (int) $this->limitstart;
    }

    /**
     * Self-contained pagination toolbar rendered only when the filtered
     * roster exceeds LIST_PAGE_LIMIT. Pure links carry the active filters so
     * navigating never silently resets them; the hidden limitstart input in
     * the form keeps drag-reorder offsets in sync.
     */
    public function getPaginationHtml()
    {
        $pagination = $this->getPagination();
        if ($pagination === null) {
            return '';
        }

        $total = $this->getTotal();
        $pages = max(1, (int) ceil($total / $this->pageLimit));
        $cur   = (int) floor($this->limitstart / $this->pageLimit);

        $app  = Factory::getApplication();
        $in   = $app->input;
        $base = 'index.php?option=' . urlencode((string) $in->getCmd('option', 'com_clubleaddir')) . '&view=leaderships';
        foreach (array('filter_type', 'filter_published', 'filter_status', 'filter_term', 'filter_search', 'filter_order', 'filter_order_Dir') as $k) {
            $v = (string) $in->get($k, '', 'string');
            if ($v !== '') {
                $base .= '&' . $k . '=' . urlencode($v);
            }
        }

        $html  = '<div class="pagination">';
        $html .= '<ul>' ;
        if ($cur > 0) {
            $html .= '<li><a href="' . $base . '&limitstart=' . (($cur - 1) * $this->pageLimit) . '">&laquo; ' . Text::_('JPREV') . '</a></li>';
        }
        for ($p = 0; $p < $pages; $p++) {
            $label = $p + 1;
            $html .= $p === $cur
                ? '<li class="active"><span>' . $label . '</span></li>'
                : '<li><a href="' . $base . '&limitstart=' . ($p * $this->pageLimit) . '">' . $label . '</a></li>';
        }
        if ($cur < $pages - 1) {
            $html .= '<li><a href="' . $base . '&limitstart=' . (($cur + 1) * $this->pageLimit) . '">' . Text::_('JNEXT') . ' &raquo;</a></li>';
        }
        $html .= '</ul>';
        $html .= '<div class="pagination-counter">' . Text::sprintf('JLIB_HTML_PAGE_CURRENT_OF_TOTAL', $cur + 1, $pages) . '</div>';
        $html .= '</div>';
        return $html;
    }

    private function paginate(array $items)
    {
        $total = count($items);
        $this->total = $total;
        $this->pageLimit = self::LIST_PAGE_LIMIT;
        $this->limitstart = 0;

        if ($total <= self::LIST_PAGE_LIMIT) {
            return $items;
        }

        $app = Factory::getApplication();
        $limit = (int) $app->input->getInt('limit', self::LIST_PAGE_LIMIT);
        $this->pageLimit = max(1, min(500, $limit));

        $limitstart = abs((int) $app->input->getInt('limitstart', 0));
        $this->limitstart = min($limitstart, max(0, $total - 1));

        return array_slice($items, $this->limitstart, $this->pageLimit);
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
     * every referenced photo, under a photos/ folder. Photos stream from
     * disk so memory stays flat no matter how many or how large. Returns
     * array('file' => temp zip path, 'name' => download filename,
     * 'count' => n, 'photos' => exported, 'skipped_photos' => n) or false.
     */
    public function exportPack()
    {
        if ($this->store === null) {
            return false;
        }

        $records  = $this->store->getRawRecords();
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

        $tmpFile = rtrim($this->tmpBase(), '/\\') . '/clubleaddir-export-' . date('Y-m-d-His') . '-' . bin2hex(random_bytes(4)) . '.zip';

        // Clean up even if the process dies mid-stream (OOM / timeout): an
        // aborted export would otherwise leave club data in world-readable
        // sys temp. The controller's own handler stays as a second net.
        register_shutdown_function(function () use ($tmpFile) {
            if (is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        });

        $exportedPhotos = 0;
        $skippedPhotos  = 0;

        $manifest = array(
            'format'      => 'clubleaddir-records',
            'version'     => 1,
            'source'      => 'com_clubleaddir',
            'exported'    => date('c'),
            'count'       => count($records),
            'photo_count' => count($photoNames),
        );

        $entries   = array();

        // Primary manifest is an NDJSON stream: the importer decodes one
        // record per line, so json_decode can never blow the memory budget no
        // matter how many records there are. A compact records.json header
        // signs the format; the legacy whole-array 'records' blob is only
        // embedded when it fits the reader's byte budget so older component
        // versions can still read small backups.
        $ndEntries = array();
        foreach ($records as $r) {
            if (is_array($r)) {
                $ndEntries[] = json_encode($r, JSON_UNESCAPED_SLASHES);
            }
        }
        $ndBody = implode("\n", $ndEntries) . ($ndEntries ? "\n" : "");

        $legacyRecords = json_encode($records, JSON_UNESCAPED_SLASHES);
        if (strlen($legacyRecords) <= self::LEGACY_MANIFEST_CAP) {
            $manifest['records'] = $records;
        } else {
            $manifest['records_file'] = 'records.ndjson';
        }
        unset($legacyRecords);

        $entries[] = array('name' => 'records.json', 'data' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $ndTmp = rtrim($this->tmpBase(), '/\\') . '/clubleaddir-ndjson-' . bin2hex(random_bytes(4));
        if (file_put_contents($ndTmp, $ndBody) === false) {
            return false;
        }
        // The staging files can hold names/emails and must not be world
        // readable even under a permissive umask.
        @chmod($ndTmp, 0600);
        $entries[] = array('name' => 'records.ndjson', 'path' => $ndTmp);

        unset($ndEntries, $ndBody);

        foreach (array_keys($photoNames) as $base) {
            $src = $photoDir . '/' . $base;
            if (!is_file($src) || !is_readable($src)) {
                continue;
            }
            // Photos above the import cap could never be imported back; skip.
            $fs = @filesize($src);
            if ($fs === false || $fs <= 0 || $fs > 6291456) {
                $skippedPhotos++;
                continue;
            }
            $entries[] = array('name' => 'photos/' . $base, 'path' => $src);
            $exportedPhotos++;
        }

        if (!ClubleaddirZip::writeStream($tmpFile, $entries)) {
            @unlink($tmpFile);
            @unlink($ndTmp);
            return false;
        }
        @unlink($ndTmp);
        // The finished backup zip also carries personal data; lock it down
        // the moment it exists (owner-only, on top of the HTTPS-only stream).
        @chmod($tmpFile, 0600);

        return array(
            'file'           => $tmpFile,
            'name'           => 'clubleaddir-records-' . date('Y-m-d-H-i-s') . '.zip',
            'count'          => count($records),
            'photos'         => $exportedPhotos,
            'skipped_photos' => $skippedPhotos,
        );
    }

    /**
     * Replace the store with the contents of an uploaded backup zip.
     * Photos are only moved into their final folder AFTER the store commit
     * succeeds, and everything is staged first, so a failed import leaves no
     * orphan files and a pre-import store snapshot exists for recovery.
     * Returns array('imported'=>n,'photos'=>n,'skipped'=>n,'warnings'=>array())
     * on success, or array('error'=>msg) on failure.
     */
    public function importPack(array $fileInfo)
    {
        $result = array('imported' => 0, 'photos' => 0, 'skipped' => 0, 'warnings' => array());

        $errCode = (int) ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errCode !== UPLOAD_ERR_OK) {
            if ($errCode === UPLOAD_ERR_NO_FILE) {
                return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_NOFILE'));
            }
            if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE
                || (int) ($fileInfo['size'] ?? -1) > 52428800) {
                return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_TOOLARGE'));
            }
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT'));
        }
        $src = (string) ($fileInfo['tmp_name'] ?? '');
        if ($src === '' || !is_file($src)) {
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_NOFILE'));
        }

        // Stage photos under a private temp dir; nothing touches the web
        // images folder until the store write has already succeeded.
        $staging = rtrim($this->tmpBase(), '/\\') . '/clubleaddir-import-' . date('Y-m-d-His') . '-' . bin2hex(random_bytes(4));
        if (!mkdir($staging, 0700, true) && !is_dir($staging)) {
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_STAGING'));
        }
        // Survives OOM / timeout / die() anywhere in this import: PHP runs
        // shutdown functions on fatal errors, so staging can never accumulate.
        register_shutdown_function(function () use ($staging) {
            $this->removeDir($staging);
        });
        $stagingPhotos = $staging . '/photos';
        if (!mkdir($stagingPhotos, 0700, true) && !is_dir($stagingPhotos)) {
            $this->removeDir($staging);
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_STAGING'));
        }

        $photoBase = JPATH_ROOT . '/images/clubleaddir';
        $photoDir  = $photoBase . '/photos';
        // Photos are served statically by the web server (see
        // ClubleaddirHelper::photoUrl). Both this folder and its parent must
        // stay 0755 so the web server can traverse and read them even when it
        // runs as a different user/group than PHP. mkdir(..., 0755, true) only
        // modes the final component; intermediate folders become 0777 &
        // ~umask, which under umask 0077 leaves /images/clubleaddir at 0700 —
        // the import "succeeds" (PHP writes fine) but every rendered photo
        // 403s. Reassert 0755 on both, matching the installer (script.php).
        if (!is_dir($photoBase)) {
            @mkdir($photoBase, 0755, true);
        }
        if (is_dir($photoBase)) {
            @chmod($photoBase, 0755);
        }
        if (!is_dir($photoDir)) {
            @mkdir($photoDir, 0755, true);
        }
        if (is_dir($photoDir)) {
            @chmod($photoDir, 0755);
        }

        // Defence-in-depth: the photos directory must never execute scripts
        // nor be served as active content (imports only ever place images
        // here, but a planted .svg/.php polyglot must not be run). Apache
        // installs get an explicit deny ruleset; nginx ignores .htaccess, so
        // outside Apache the extension whitelist and folder placement are the
        // boundary. Written only when absent so an intentionally managed
        // .htaccess is never clobbered (same ruleset as the installer).
        $htPhotos = $photoDir . '/.htaccess';
        if (!is_file($htPhotos)) {
            @file_put_contents($htPhotos, ClubleaddirHelper::photoHtaccessRules());
            @chmod($htPhotos, 0444);
        }

        $manifestRaw       = null;
        $manifestTooLarge  = false;
        $legacyTooLarge    = false;
        $extracted         = array();
        $referenced        = array();
        $photoCap          = 6291456;
        $ndRows            = null;      // populated only when records.ndjson exists
        $ndCount           = 0;
        $recordsJsonSeen   = false;

        // Single streaming pass: records.ndjson is parsed line-by-line (each
        // line is one record, so json_decode is amortised per record and can
        // never amplify into a memory blow-up), records.json is only read as a
        // small format header, and photos/ entries are validated and staged.
        // Caps on per-entry and total size are enforced inside
        // ClubleaddirZip::iterate().
        $zipOk = ClubleaddirZip::iterate($src, function ($name, $tmp, $meta) use (&$manifestRaw, &$manifestTooLarge, &$legacyTooLarge, &$ndRows, &$ndCount, &$recordsJsonSeen, &$extracted, $photoCap, $stagingPhotos, &$result) {
            $name = (string) $name;
            if ($name === '' || substr($name, -1) === '/') {
                return true;
            }
            if ($name === 'records.ndjson') {
                // One record per line. A single line is capped so one huge
                // crafted line cannot become a huge json_decode; the line
                // count is capped so a huge number of lines cannot be
                // accumulated. Either violation aborts the archive cleanly.
                $fh2 = fopen($tmp, 'rb');
                if (!$fh2) {
                    Log::add('Clubleaddir import: cannot open ndjson temp file: ' . $tmp, self::LOG_LEVEL, 'com_clubleaddir');
                    $manifestTooLarge = true;
                    return false;
                }
                $rows = array();
                while (($line = fgets($fh2)) !== false) {
                    $ndCount++;
                    if ($ndCount > self::MAX_RECORDS) {
                        fclose($fh2);
                        $manifestTooLarge = true;
                        return false;
                    }
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if (strlen($line) > 1048576) {
                        fclose($fh2);
                        $manifestTooLarge = true;
                        return false;
                    }
                    $rec = json_decode($line, true);
                    if (!is_array($rec)) {
                        fclose($fh2);
                        $manifestTooLarge = true;
                        return false;
                    }
                    $rows[] = $rec;
                }
                fclose($fh2);
                $ndRows = $rows;
                return true;
            }
            if ($name === 'records.json') {
                $recordsJsonSeen = true;
                // Bounded strictly: the legacy whole-array decode below has a
                // ~25x memory amplification on crafted input, which is why the
                // byte budget is a fraction of the cap for the streaming file.
                $manifestSize = @filesize($tmp);
                if ($manifestSize === false || $manifestSize <= 0 || $manifestSize > self::LEGACY_MANIFEST_CAP || $meta['usize'] > self::LEGACY_MANIFEST_CAP) {
                    $manifestTooLarge = true;
                    $legacyTooLarge   = true;
                    return false;
                }
                $raw = file_get_contents($tmp);
                if ($raw !== false && $raw !== '') {
                    $manifestRaw = $raw;
                } elseif ($raw === false) {
                    Log::add('Clubleaddir import: cannot read records.json temp file: ' . $tmp, self::LOG_LEVEL, 'com_clubleaddir');
                }
                return true;
            }
            if (strpos($name, 'photos/') === 0) {
                $base = basename($name);
                if ($name !== 'photos/' . $base || !$this->isAllowedPhotoName($base)) {
                    $this->addWarning($result, Text::sprintf('COM_CLUBLEADDIR_IMPORT_SKIPPED_PHOTO', $this->escapeQuiet($name)));
                    return true;
                }
                if ($meta['usize'] <= 0 || $meta['usize'] > $photoCap) {
                    $this->addWarning($result, Text::sprintf('COM_CLUBLEADDIR_IMPORT_SKIPPED_PHOTO', $base));
                    return true;
                }
                if (!$this->isImageFile($tmp)) {
                    $this->addWarning($result, Text::sprintf('COM_CLUBLEADDIR_IMPORT_SKIPPED_PHOTO', $base));
                    return true;
                }
                if (!$this->moveOrCopy($tmp, $stagingPhotos . '/' . $base)) {
                    $this->addWarning($result, Text::sprintf('COM_CLUBLEADDIR_IMPORT_SKIPPED_PHOTO', $base));
                    return true;
                }
                if (!@chmod($stagingPhotos . '/' . $base, 0600)) {
                    Log::add('Clubleaddir import: cannot chmod staged photo: ' . ($stagingPhotos . '/' . $base), self::LOG_LEVEL, 'com_clubleaddir');
                }
                $extracted[$base] = true;
            }
            return true;
        });

        if ($zipOk === false) {
            $this->removeDir($staging);
            return array('error' => Text::_(
                $legacyTooLarge
                ? 'COM_CLUBLEADDIR_IMPORT_ERROR_LEGACY_TOO_LARGE'
                : ($manifestTooLarge ? 'COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT' : 'COM_CLUBLEADDIR_IMPORT_ERROR_ZIP')
            ));
        }

        if (!$recordsJsonSeen) {
            // The format header must be present; reject anonymous data.
            $this->removeDir($staging);
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT'));
        }
        $manifest = $manifestRaw !== null ? json_decode($manifestRaw, true) : null;
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'clubleaddir-records') {
            $this->removeDir($staging);
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT'));
        }

        // Records come from the streaming file when present; otherwise from
        // the (bounded, legacy) whole-array manifest.
        $rawRecords = null;
        if ($ndRows !== null) {
            $rawRecords = $ndRows;
        } elseif (isset($manifest['records']) && is_array($manifest['records'])) {
            $rawRecords = $manifest['records'];
            if (count($rawRecords) > self::MAX_RECORDS) {
                $this->removeDir($staging);
                return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT'));
            }
        } else {
            $rawRecords = array();
        }

        $cleaned = array();
        $seen    = array();
        foreach ($rawRecords as $r) {
            if (!is_array($r)) {
                continue;
            }
            $n = $this->normalizeRecord($r, $result);
            if ($n === null) {
                $result['skipped']++;
                continue;
            }
            $id = (int) $n['id'];
            if ($id <= 0 || isset($seen[$id])) {
                $result['skipped']++;
                continue;
            }
            // P1-2: drop photo refs whose file is not actually in this archive.
            foreach (array('photo', 'photo_full') as $k) {
                if ($n[$k] === '') {
                    continue;
                }
                $pb = $this->photoFromPath($n[$k]);
                if ($pb === null || !isset($extracted[$pb])) {
                    $n[$k] = '';
                    $this->addWarning($result, Text::sprintf('COM_CLUBLEADDIR_IMPORT_PHOTO_MISSING', $this->escapeQuiet($n['name'])));
                } else {
                    $referenced[$pb] = true;
                }
            }
            $seen[$id] = true;
            $cleaned[] = $n;
            $result['imported']++;
        }

        if ($result['imported'] === 0) {
            // Restoring an empty backup is a legitimate operation (wipe), so a
            // zero-record archive is accepted only when it is self-consistent:
            // nothing was declared AND the manifest count says zero. Anything
            // that declared records but sanitised to zero, or declared count 0
            // while carrying records, is malformed and stays rejected.
            $declared = (isset($manifest['count']) && is_int($manifest['count'])) ? $manifest['count'] : null;
            if (count($rawRecords) !== 0 || ($declared !== null && $declared !== 0)) {
                $this->removeDir($staging);
                return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_FORMAT'));
            }
        }

        // Frozen pre-import copy for manual rollback (mirrors .bak generations).
        if ($this->store !== null) {
            try {
                $this->store->snapshot('pre-import');
            } catch (\Throwable $e) {
                // Best-effort; .bak generations still protect.
            }
        }

        // Move staged photos into their final home BEFORE the store write so
        // the photo-reference cleanup below can fold into the single atomic
        // store commit. Only photos actually referenced by a cleaned record
        // go; extracted-but-unreferenced files stay in staging and are
        // discarded with it. A failed store write afterwards leaves the moved
        // files as orphaned (valid, unreferenced) images — a harmless tidiness
        // cost that buys one locked write with no window for a concurrent save
        // to be clobbered by a second importAll pass.
        $finalized = array();
        foreach (array_keys($referenced) as $base) {
            if (!$this->moveOrCopy($stagingPhotos . '/' . $base, $photoDir . '/' . $base)) {
                $result['warnings'][] = Text::sprintf('COM_CLUBLEADDIR_IMPORT_PHOTO_MOVE', $base);
                continue;
            }
            if (@chmod($photoDir . '/' . $base, 0644) === false) {
                Log::add('Clubleaddir import: could not make photo world-readable (0644): ' . ($photoDir . '/' . $base), self::LOG_LEVEL, 'com_clubleaddir');
            }
            if (!is_file($photoDir . '/' . $base) || !is_readable($photoDir . '/' . $base)
                || (int) @filesize($photoDir . '/' . $base) < 1
                || !$this->isImageFile($photoDir . '/' . $base)) {
                $result['warnings'][] = Text::sprintf('COM_CLUBLEADDIR_IMPORT_PHOTO_BAD', $base);
                continue;
            }
            $finalized[$base] = true;
            $result['photos']++;
        }

        // A photo reference must resolve to a file that was actually finalised.
        // If the host could not place or verify a file, drop the reference so
        // the site never renders a phantom blank in its place; the name is
        // surfaced in the warnings above.
        foreach ($cleaned as $i => $rr) {
            foreach (array('photo', 'photo_full') as $k) {
                if ($rr[$k] === '') {
                    continue;
                }
                $pb = $this->photoFromPath($rr[$k]);
                if ($pb === null || !isset($finalized[$pb])) {
                    $rr[$k] = '';
                }
            }
            $cleaned[$i] = $rr;
        }

        if ($this->store === null || !$this->store->importAll($cleaned)) {
            $this->removeDir($staging);
            return array('error' => Text::_('COM_CLUBLEADDIR_IMPORT_ERROR_SAVE'));
        }

        $this->removeDir($staging);

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
    private function normalizeRecord(array $r, array &$result)
    {
        $id   = (int) ($r['id'] ?? 0);
        $type = (string) ($r['type'] ?? '');
        $valid = array('officer', 'director', 'director_league', 'staff');
        if (!in_array($type, $valid, true)) {
            return null;
        }

        $name = ClubleaddirStore::mbSubstr(trim((string) ($r['name'] ?? '')), 0, 120);
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

        $role = ClubleaddirStore::mbSubstr(trim((string) ($r['role'] ?? '')), 0, 80);
        $email = ClubleaddirStore::mbSubstr(trim((string) ($r['email'] ?? '')), 0, 254);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addWarning($result, Text::sprintf('COM_CLUBLEADDIR_IMPORT_BAD_EMAIL', $this->escapeQuiet($name)));
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
            'league_name' => ClubleaddirStore::mbSubstr(trim((string) ($r['league_name'] ?? '')), 0, 40),
            'term'        => ClubleaddirStore::mbSubstr(trim((string) ($r['term'] ?? '')), 0, 9),
            'bio'         => ClubleaddirStore::mbSubstr((string) ($r['bio'] ?? ''), 0, 5000),
            'photo'       => $photoBase === '' ? '' : '/images/clubleaddir/photos/' . $photoBase,
            'photo_full'  => $photoFullBase === '' ? '' : '/images/clubleaddir/photos/' . $photoFullBase,
            'email'       => $email,
            'phone'       => ClubleaddirStore::mbSubstr(preg_replace('/[^0-9+\-\s\(\)]/', '', (string) ($r['phone'] ?? '')), 0, 30),
            'contact_id'  => max(0, (int) ($r['contact_id'] ?? 0)),
            'vacant'      => $vacant,
            'ordering'    => max(0, min(9999, (int) ($r['ordering'] ?? 0))),
            'published'   => $published,
            'status'      => ((string) ($r['status'] ?? 'active')) === 'archived' ? 'archived' : 'active',
            'created'     => ClubleaddirStore::mbSubstr((string) ($r['created'] ?? ''), 0, 40),
            'modified'    => ClubleaddirStore::mbSubstr((string) ($r['modified'] ?? ''), 0, 40),
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
        if (!is_string($base) || $base === ''
            || strlen($base) > 200
            || preg_match('/^[A-Za-z0-9._-]+$/', $base) !== 1
            || !in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) {
            return false;
        }
        // Windows reserved device names (CON, PRN, AUX, NUL, COM1-9, LPT1-9)
        // are rejected up front so no platform ever attempts to write them.
        $dot  = strrpos($base, '.');
        $stem = strtoupper($dot === false ? $base : substr($base, 0, $dot));
        if (in_array($stem, array('CON', 'PRN', 'AUX', 'NUL'), true)
            || preg_match('/^(COM|LPT)[1-9]$/', $stem) === 1) {
            return false;
        }
        return true;
    }

    protected function isImageFile($path)
    {
        // Structural validation over magic bytes whenever GD is present: a
        // genuine decoder rejects header-only fakes, truncated files and
        // non-image polyglots, so junk never reaches the web-served photos
        // directory. (A real carrier image with appended payload still parses
        // — extension whitelisting plus non-executable placement is the
        // boundary for that, not this check.) Hosts whose GD lacks a codec
        // (e.g. WebP) fall through to the mime check for that file.
        if (function_exists('imagecreatefromstring')) {
            $data = @file_get_contents($path);
            if ($data !== false && $data !== '') {
                $im = @imagecreatefromstring($data);
                if ($im !== false) {
                    imagedestroy($im);
                    return true;
                }
                // Decode failed: fall through to an independent detector so a
                // type the local GD build cannot decode is not silently lost.
            }
        }

        // Header signatures are decisive: they are unambiguous for real
        // JPEG/PNG/GIF/WebP regardless of the host's libmagic state. MIME
        // detection is only a fallback (a stale or missing magic DB on shared
        // hosts can label a valid image 'application/octet-stream', which
        // would otherwise silently skip every photo on import).
        $head = '';
        $fh   = @fopen($path, 'rb');
        if ($fh) {
            $head = (string) fread($fh, 16);
            fclose($fh);
        }
        if (substr($head, 0, 3) === "\xFF\xD8\xFF") {
            return true; // JPEG
        }
        if (substr($head, 0, 8) === "\x89PNG\r\n\x1A\n") {
            return true; // PNG
        }
        if (substr($head, 0, 4) === 'GIF8') {
            return true; // GIF87a / GIF89a
        }
        if (substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') {
            return true; // WebP
        }
        $mime = null;
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $mime = finfo_file($f, $path);
                finfo_close($f);
            }
        }
        if (!$mime && function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
        }
        // Accepted extensions are already whitelisted to image types, so any
        // image/* mime from the fallback is sufficient.
        return is_string($mime) && strpos($mime, 'image/') === 0;
    }

    /**
     * Writable temp location for export/import staging (sys temp, then
     * Joomla tmp_path, then JPATH_ROOT/tmp).
     */
    private function tmpBase()
    {
        $tmpBase = sys_get_temp_dir();
        if ($tmpBase && is_dir($tmpBase) && is_writable($tmpBase)) {
            return rtrim($tmpBase, '/\\');
        }
        $cfg  = Factory::getConfig();
        $tmpBase = (string) $cfg->get('tmp_path', '');
        if ($tmpBase && is_dir($tmpBase) && is_writable($tmpBase)) {
            return rtrim($tmpBase, '/\\');
        }
        // Last-resort fallback lands inside the web root: staging here would
        // otherwise let a backup (which may contain names/emails) be fetched
        // anonymously while in progress. Apache installs get an explicit deny
        // rule; refuse-on-create is caught later by the caller's mkdir check.
        $webFail = JPATH_ROOT . '/tmp';
        if (!is_dir($webFail)) {
            @mkdir($webFail, 0700, true);
        }
        $htWeb = $webFail . '/.htaccess';
        if (!is_file($htWeb)) {
            @file_put_contents($htWeb, "Require all denied\n");
            @chmod($htWeb, 0444);
        }
        return $webFail;
    }

    /**
     * Move a file; falls back to copy+unlink where rename is impossible
     * (e.g. across devices).
     */
    private function moveOrCopy($src, $dst)
    {
        if (@rename($src, $dst)) {
            return true;
        }
        // rename() failed; copy fallback. If $dst already exists as a
        // symlink, copy() would follow it and write through to an arbitrary
        // target, so remove whatever occupies the destination first.
        if (is_link($dst) || is_file($dst)) {
            @unlink($dst);
        }
        if (@copy($src, $dst)) {
            @unlink($src);
            return true;
        }
        return false;
    }

    /**
     * Iterative recursive delete of the private import staging directory.
     */
    private function removeDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $stack = array($dir);
        $scanned = array();
        while ($stack) {
            $current = $stack[count($stack) - 1];
            if (!isset($scanned[$current])) {
                $scanned[$current] = true;
                $entries = @scandir($current);
                if ($entries === false) {
                    array_pop($stack);
                    continue;
                }
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $path = $current . '/' . $entry;
                    if (is_dir($path) && !is_link($path)) {
                        $stack[] = $path;
                    } else {
                        @unlink($path);
                    }
                }
            } else {
                array_pop($stack);
                @rmdir($current);
            }
        }
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
            $logDir = ClubleaddirStore::logDir();
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
            // Serialise rotate+append so two concurrent imports cannot
            // interleave against rotation (losing a generation, or appending
            // mid-rename and landing in a rotated file).
            $lock = @fopen($logDir . '/.lock', 'c');
            if ($lock) {
                flock($lock, LOCK_EX);
            }
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
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        } catch (\Throwable $e) {
            Log::add('Clubleaddir audit log exception: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
        }
    }
}
