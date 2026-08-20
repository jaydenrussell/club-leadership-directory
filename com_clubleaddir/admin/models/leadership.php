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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

require_once __DIR__ . '/../store/Store.php';

class ClubleaddirModelLeadership extends BaseDatabaseModel
{
    private $store;
    public $item;

    public function __construct($config = array())
    {
        parent::__construct($config);
        try {
            $this->store = ClubleaddirStore::getInstance();
        } catch (\Throwable $e) {
            $this->store = null;
        }
    }

    public function getItem($pk = null)
    {
        if ($this->store === null) {
            return (object) array();
        }

        $id = (int) $pk;

        if ($id) {
            $row = $this->store->getById($id);
            if ($row) {
                $this->item = $row;
                return $row;
            }
        }

        $this->item = (object) array(
            'id'          => 0,
            'name'        => '',
            'type'        => 'director',
            'role'        => '',
            'league_name' => '',
            'term'        => '',
            'start_year'  => 0,
            'end_year'    => 0,
            'bio'         => '',
            'photo'       => '',
            'email'       => '',
            'phone'       => '',
            'contact_id'  => 0,
            'ordering'    => 0,
            'published'   => 1,
            'status'      => 'active',
            'created'     => '',
            'modified'    => '',
            'created_by'  => 0,
            'modified_by' => 0,
        );

        return $this->item;
    }

    public function save(array $data)
    {
        $date   = Factory::getDate()->toSql();
        $userId = (int) Factory::getUser()->id;
        $data   = $this->validate($data);

        if ($data === false) {
            return false;
        }

        $record = array(
            'name'        => $data['name'],
            'type'        => $data['type'],
            'role'        => $data['role'] ?? '',
            'league_name' => $data['league_name'] ?? '',
            'term'        => $data['term'] ?? '',
            'start_year'  => (int) ($data['start_year'] ?? 0),
            'end_year'    => (int) ($data['end_year'] ?? 0),
            'bio'         => $data['bio'] ?? '',
            'photo'       => $data['photo'] ?? '',
            'email'       => $data['email'] ?? '',
            'phone'       => $data['phone'] ?? '',
            'contact_id'  => (int) ($data['contact_id'] ?? 0),
            'ordering'    => (int) ($data['ordering'] ?? 0),
            'published'   => isset($data['published']) ? (int) $data['published'] : 1,
            'status'      => $data['status'] ?? 'active',
        );

        $app   = Factory::getApplication();
        $files = $app->input->files->get('jform', array(), 'array');
        if (!empty($files['photo']['name']) && $files['photo']['error'] === UPLOAD_ERR_OK) {
            $photoPaths = $this->handlePhotoUpload($files['photo']);
            if ($photoPaths === false) {
                return false;
            }
            // $photoPaths = [original, squareAvatar]
            $record['photo_full'] = $photoPaths[0];
            $record['photo']      = $photoPaths[1];
        }

        $record['modified']    = $date;
        $record['modified_by'] = $userId;

        if (!empty($data['id'])) {
            $existing = $this->store->getById((int) $data['id']);
            if ($existing) {
                $record['created']    = $existing->created;
                $record['created_by'] = $existing->created_by;
            }
            return (bool) $this->store->update((int) $data['id'], $record);
        }

        $record['created']    = $date;
        $record['created_by'] = $userId;

        return (bool) $this->store->insert($record);
    }

    private function validate(array $data)
    {
        if (empty(trim($data['name'] ?? ''))) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_NAME_REQUIRED'));
            return false;
        }
        if (empty(trim($data['type'] ?? ''))) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_TYPE_REQUIRED'));
            return false;
        }
        $valid = array('officer', 'director', 'director_league', 'staff');
        if (!in_array($data['type'], $valid, true)) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_INVALID_TYPE'));
            return false;
        }
        if ($data['type'] === 'director_league' && empty(trim($data['league_name'] ?? ''))) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_LEAGUE_REQUIRED'));
            return false;
        }
        // Officers may only use the four club governance titles.
        if ($data['type'] === 'officer') {
            $allowed = array('President', 'Vice President', 'Secretary', 'Treasurer');
            $role = trim($data['role'] ?? '');
            if ($role === '' || !in_array($role, $allowed, true)) {
                $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_OFFICER_ROLE_INVALID'));
                return false;
            }
        }
        return $data;
    }

    /**
     * Handle an uploaded photo: validate, move the ORIGINAL into place, and also
     * generate a square "avatar" crop (faces framed, centre-weighted toward the
     * top where headshots sit) used for the circular display. Returns
     * array($originalRel, $squareRel) on success, or false on failure.
     */
    protected function handlePhotoUpload($fileInfo)
    {
        $allowedMimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $maxSize      = 2 * 1024 * 1024;

        if ($fileInfo['size'] > $maxSize) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_TOO_LARGE'));
            return false;
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileInfo['tmp_name']);

        if (!in_array($mimeType, $allowedMimes, true)) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_INVALID_TYPE'));
            return false;
        }

        $ext = 'jpg';
        switch ($mimeType) {
            case 'image/png':  $ext = 'png';  break;
            case 'image/gif':  $ext = 'gif';  break;
            case 'image/webp': $ext = 'webp'; break;
        }

        $destDir = JPATH_ROOT . '/images/clubleaddir/photos';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $base   = 'photo_' . time() . '_' . bin2hex(random_bytes(4));
        $orig   = $base . '.' . $ext;
        $square = $base . '_sq.' . $ext;
        $origPath   = $destDir . '/' . $orig;
        $squarePath = $destDir . '/' . $square;

        if (!move_uploaded_file($fileInfo['tmp_name'], $origPath)) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED'));
            return false;
        }

        // Square avatar crop (best-effort; original is returned regardless).
        $this->makeSquareCrop($origPath, $squarePath, 400);

        return array(
            '/images/clubleaddir/photos/' . $orig,
            '/images/clubleaddir/photos/' . $square,
        );
    }

    /**
     * Create a 1:1 crop of $src into $dest ($size x $size). The crop is centred
     * horizontally and biased upward (faces usually sit in the upper portion of a
     * headshot) so the resulting circle frames the face. Falls back silently to
     * leaving only the original if GD image functions are unavailable.
     *
     * @return bool true if a crop was written
     */
    protected function makeSquareCrop($src, $dest, $size = 400)
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $img = @imagecreatefromstring(file_get_contents($src));
        if ($img === false) {
            return false;
        }

        $sw = imagesx($img);
        $sh = imagesy($img);
        if (!$sw || !$sh) {
            imagedestroy($img);
            return false;
        }

        // Largest square that fits, biased toward the top (face region).
        $side = min($sw, $sh);
        $srcX = (int) (($sw - $side) / 2);
        $srcY = (int) (($sh - $side) * 0.38); // ~38% from top: headshot framing
        if ($srcY < 0) {
            $srcY = 0;
        }

        $out = imagecreatetruecolor($size, $size);
        if (!$out) {
            imagedestroy($img);
            return false;
        }
        // Preserve transparency for PNG/GIF.
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagesavealpha($out, true);
        imagealphablending($out, false);

        imagecopyresampled($out, $img, 0, 0, $srcX, $srcY, $size, $size, $side, $side);

        $ok = false;
        $lower = strtolower($dest);
        if (substr($lower, -4) === '.png') {
            $ok = imagepng($out, $dest, 8);
        } elseif (substr($lower, -4) === '.gif') {
            $ok = imagegif($out, $dest);
        } elseif (substr($lower, -5) === '.webp' && function_exists('imagewebp')) {
            $ok = imagewebp($out, $dest, 90);
        } else {
            $ok = imagejpeg($out, $dest, 90);
        }

        imagedestroy($img);
        imagedestroy($out);

        return (bool) $ok;
    }


    public function delete(array $pks)
    {
        $ok = true;
        foreach ($pks as $pk) {
            if (!$this->store->delete((int) $pk)) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function publish(array $pks, $state = 1)
    {
        $ok = true;
        foreach ($pks as $pk) {
            if (!$this->store->setPublished((int) $pk, (int) $state)) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * Send records to the Trash (Joomla standard published = -2) so they remain
     * in the store and can be recovered later via Publish.
     */
    public function trash(array $pks)
    {
        return $this->publish($pks, -2);
    }

    public function reorderSingle($id, $direction = 1)
    {
        return $this->store->reorderSingle((int) $id, (int) $direction);
    }

    public function saveOrder(array $pks, array $order)
    {
        if ($this->store === null) {
            return false;
        }
        foreach ($pks as $i => $pk) {
            $ord = isset($order[$i]) ? (int) $order[$i] : 0;
            $this->store->setOrdering((int) $pk, $ord);
        }
        return true;
    }

    public function setError($msg)
    {
        Factory::getApplication()->enqueueMessage($msg, 'error');
    }
}
