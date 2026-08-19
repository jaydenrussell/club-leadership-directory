<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleadership
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/../store/Store.php';

class ClubLeadershipModelLeadership
{
    private $store;
    public $item;

    public function __construct()
    {
        $this->store = ClubLeadershipStore::getInstance();
    }

    public function getItem($pk = null)
    {
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
            $photoPath = $this->handlePhotoUpload($files['photo']);
            if ($photoPath === false) {
                return false;
            }
            $record['photo'] = $photoPath;
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
            $this->setError(Text::_('COM_CLUBLEADERSHIP_ERROR_NAME_REQUIRED'));
            return false;
        }
        if (empty(trim($data['type'] ?? ''))) {
            $this->setError(Text::_('COM_CLUBLEADERSHIP_ERROR_TYPE_REQUIRED'));
            return false;
        }
        $valid = array('officer', 'director', 'director_league', 'staff');
        if (!in_array($data['type'], $valid, true)) {
            $this->setError(Text::_('COM_CLUBLEADERSHIP_ERROR_INVALID_TYPE'));
            return false;
        }
        return $data;
    }

    protected function handlePhotoUpload($fileInfo)
    {
        $allowedMimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $maxSize      = 2 * 1024 * 1024;

        if ($fileInfo['size'] > $maxSize) {
            $this->setError(Text::_('COM_CLUBLEADERSHIP_ERROR_PHOTO_TOO_LARGE'));
            return false;
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileInfo['tmp_name']);

        if (!in_array($mimeType, $allowedMimes, true)) {
            $this->setError(Text::_('COM_CLUBLEADERSHIP_ERROR_PHOTO_INVALID_TYPE'));
            return false;
        }

        $ext = 'jpg';
        switch ($mimeType) {
            case 'image/png':  $ext = 'png';  break;
            case 'image/gif':  $ext = 'gif';  break;
            case 'image/webp': $ext = 'webp'; break;
        }

        $destDir = JPATH_ROOT . '/images/clubleadership/photos';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $filename = 'photo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $destDir . '/' . $filename;

        if (!move_uploaded_file($fileInfo['tmp_name'], $destPath)) {
            $this->setError(Text::_('COM_CLUBLEADERSHIP_ERROR_PHOTO_UPLOAD_FAILED'));
            return false;
        }

        return 'images/clubleadership/photos/' . $filename;
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

    public function reorderSingle($id, $direction = 1)
    {
        return $this->store->reorderSingle((int) $id, (int) $direction);
    }

    private function setError($msg)
    {
        Factory::getApplication()->enqueueMessage($msg, 'error');
    }
}
