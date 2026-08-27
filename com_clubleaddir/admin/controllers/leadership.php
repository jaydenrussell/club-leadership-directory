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
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class ClubleaddirControllerLeadership extends BaseController
{
    /**
     * Add a new entry (routes to the edit form with no id).
     */
    public function add()
    {
        $this->setRedirect('index.php?option=com_clubleaddir&view=leadership');
    }

    /**
     * Edit an existing entry (captures the id from the list checkbox selection).
     */
    public function edit($key = null, $urlVar = null)
    {
        $id = $this->input->getInt('id');
        if (!$id) {
            $cid = (array) $this->input->post->get('cid', array(), 'array');
            $id = array_map('intval', $cid);
            $id = !empty($id) ? (int) $id[0] : 0;
        }
        $this->setRedirect('index.php?option=com_clubleaddir&view=leadership&id=' . (int) $id);
    }

    public function save($key = null, $urlVar = null)
    {
        if (strtoupper($this->input->getMethod()) !== 'POST' || !Session::checkToken()) {
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }

        $user = Factory::getUser();
        $id   = (int) ($this->input->post->get('jform', array(), 'array')['id'] ?? 0);
        $can  = $id ? $user->authorise('core.edit', 'com_clubleaddir')
                    : $user->authorise('core.create', 'com_clubleaddir');
        if (!$can) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }

        $data = $this->input->post->get('jform', array(), 'array');
        $data = $this->sanitize($data);

        $model = $this->getModel('Leadership', 'ClubleaddirModel');

        if ($model->save($data)) {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ITEM_SAVED'));
        } else {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ERROR_SAVING'), 'error');
        }

        $task = (string) $this->input->getCmd('task');
        $id   = !empty($data['id']) ? (int) $data['id'] : 0;

        if ($task === 'leadership.apply') {
            $this->setRedirect('index.php?option=com_clubleaddir&view=leadership&id=' . $id);
        } else {
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
        }

        return true;
    }

    public function cancel($key = null)
    {
        $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
    }

    /**
     * Shared POST+token+authorisation guard for state-changing batch actions.
     * @deprecated use guardEditState()/guardDelete() — kept for BC.
     */
    private function guardState()
    {
        return $this->guardEditState();
    }

    private function guardEditState()
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }
        if (!Session::checkToken()) {
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }
        $user = Factory::getUser();
        if (!$user->authorise('core.edit.state', 'com_clubleaddir')) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }
        return true;
    }

    private function guardDelete()
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }
        if (!Session::checkToken()) {
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }
        $user = Factory::getUser();
        if (!$user->authorise('core.delete', 'com_clubleaddir')) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }
        return true;
    }

    public function delete()
    {
        if (!$this->guardDelete()) {
            return false;
        }

        $model = $this->getModel('Leadership', 'ClubleaddirModel');
        $ids   = array_map('intval', (array) $this->input->post->get('cid', array(), 'array'));
        $ids   = array_filter($ids, function ($id) { return $id > 0; });

        if (empty($ids)) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }

        if ($model->delete($ids)) {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ITEMS_DELETED'));
        } else {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ERROR_DELETING'), 'error');
        }

        $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
    }

    /**
     * Send selected records to the Trash (published = -2) so they can be recovered.
     * Standard Joomla recoverable-delete pattern; the permanent Delete (above)
     * remains available for emptying the trash.
     */
    public function trash()
    {
        if (!$this->guardEditState()) {
            return false;
        }

        $model = $this->getModel('Leadership', 'ClubleaddirModel');
        $ids   = array_map('intval', (array) $this->input->post->get('cid', array(), 'array'));
        $ids   = array_filter($ids, function ($id) { return $id > 0; });

        if (empty($ids)) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }

        if ($model->trash($ids)) {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ITEMS_TRASHED'));
        } else {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ERROR_TRASHING'), 'error');
        }

        $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
    }

    public function emptyTrash()
    {
        if (!$this->guardDelete()) {
            return false;
        }
        $model = $this->getModel('Leadership', 'ClubleaddirModel');
        $ids   = array_map('intval', (array) $this->input->post->get('cid', array(), 'array'));
        $ids   = array_filter($ids, function ($id) { return $id > 0; });
        // If no selection, empty all trashed (published = -2)
        if (empty($ids)) {
            try {
                $store = \ClubleaddirStore::getInstance();
                $trashed = $store->getAll(['published' => -2]);
                foreach ($trashed as $row) { $ids[] = (int) $row->id; }
            } catch (\Throwable $e) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_CLUBLEADDIR_ERROR_LOADING_DATA'), 'warning');
            }
        }
        if (empty($ids)) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }
        if ($model->delete($ids)) {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ITEMS_DELETED'));
        } else {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ERROR_DELETING'), 'error');
        }
        $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
    }

    public function publish()
    {
        if (!$this->isPost()) {
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }

        if (!Session::checkToken()) {
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }

        $user = Factory::getUser();
        if (!$user->authorise('core.edit.state', 'com_clubleaddir')) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }

        $model = $this->getModel('Leadership', 'ClubleaddirModel');
        $ids = array_map('intval', (array) $this->input->post->get('cid', array(), 'array'));
        $ids = array_filter($ids, function ($id) { return $id > 0; });

        $state = $this->input->post->getInt('state', null);
        if ($state === null) {
            $task  = (string) $this->input->post->getCmd('task');
            $state = (strpos($task, 'unpublish') !== false) ? 0 : 1;
        }

        if (empty($ids)) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }

        if ($model->publish($ids, $state)) {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ITEMS_UPDATED'));
        }

        $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
    }

    public function reorder()
    {
        if (!$this->guardState()) {
            return false;
        }

        $model     = $this->getModel('Leadership', 'ClubleaddirModel');
        $id        = $this->input->getInt('id');
        $direction = $this->input->getInt('direction', 1);

        if ($id < 1) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }

        if ($model->reorderSingle($id, $direction)) {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ITEM_REORDERED'));
        }

        $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
    }

    public function saveorder()
    {
        if (!$this->guardState()) {
            return false;
        }

        $pks   = array_map('intval', (array) $this->input->post->get('cid', array(), 'array'));
        $order = array_map('intval', (array) $this->input->post->get('order', array(), 'array'));
        $pks   = array_filter($pks, function ($id) { return $id > 0; });

        if (empty($pks)) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
            return false;
        }

        $model = $this->getModel('Leadership', 'ClubleaddirModel');
        if ($model->saveOrder($pks, $order)) {
            $this->setMessage(Text::_('JSUCCESS_SAVE_ORDER'));
        } else {
            $this->setMessage(Text::_('COM_CLUBLEADDIR_ERROR_SAVING'), 'error');
        }

        $this->setRedirect('index.php?option=com_clubleaddir&view=leaderships');
    }

    /**
     * AJAX endpoint for drag-to-reorder (sortablelist.sortable).
     * The JS posts cid[] + order[]; we persist and return "1" so the
     * spinner clears and the order survives a refresh.
     */
    public function saveorderAjax()
    {
        $user = Factory::getUser();
        if (!$user->authorise('core.edit.state', 'com_clubleaddir')) {
            echo '0';
            Factory::getApplication()->close();
        }

        if (!Session::checkToken('post')) {
            echo '0';
            Factory::getApplication()->close();
        }

        $pks   = array_map('intval', (array) $this->input->post->get('cid', array(), 'array'));
        $order = array_map('intval', (array) $this->input->post->get('order', array(), 'array'));
        $pks   = array_filter($pks, function ($id) { return $id > 0; });

        if (empty($pks) || empty($order)) {
            echo '0';
            Factory::getApplication()->close();
        }

        $model = $this->getModel('Leadership', 'ClubleaddirModel');
        foreach ($pks as $pk) {
            if (!$user->authorise('core.edit', 'com_clubleaddir.leadership.' . (int) $pk)) {
                echo '0';
                Factory::getApplication()->close();
            }
        }

        $ok = false;
        if ($model->saveOrder($pks, $order)) {
            $ok = true;
        }

        echo $ok ? '1' : '0';
        Factory::getApplication()->close();
    }

    /**
     * Cast/whitelist incoming form fields so nothing unexpected reaches the store.
     */
    private function sanitize(array $data)
    {
        $int = array('id', 'ordering', 'published', 'contact_id');
        foreach ($int as $k) {
            if (isset($data[$k])) {
                $data[$k] = (int) $data[$k];
            }
        }
        if (isset($data['type'])) {
            $valid = array('officer', 'director', 'director_league', 'staff');
            if (!in_array($data['type'], $valid, true)) {
                $data['type'] = 'director';
            }
        }
        if (isset($data['status'])) {
            $data['status'] = $data['status'] === 'archived' ? 'archived' : 'active';
        }
        return $data;
    }
}
