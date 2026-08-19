<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleadership
 * @copyright   Copyright (C) 2026 Simcoe Curling Club. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class ClubLeadershipControllerLeadership extends BaseController
{
    /**
     * Add a new entry (routes to the edit form with no id).
     */
    public function add()
    {
        $this->setRedirect('index.php?option=com_clubleadership&view=leadership');
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
        $this->setRedirect('index.php?option=com_clubleadership&view=leadership&id=' . (int) $id);
    }

    public function save($key = null, $urlVar = null)
    {
        if (strtoupper($this->input->getMethod()) !== 'POST' || !Session::checkToken()) {
            $this->setRedirect('index.php?option=com_clubleadership&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }

        $data = $this->input->post->get('jform', array(), 'array');
        $data = $this->sanitize($data);

        $model = $this->getModel('Leadership', 'ClubLeadershipModel');

        if ($model->save($data)) {
            $this->setMessage(Text::_('COM_CLUBLEADERSHIP_ITEM_SAVED'));
        } else {
            $this->setMessage(Text::_('COM_CLUBLEADERSHIP_ERROR_SAVING'), 'error');
        }

        $task = (string) $this->input->getCmd('task');
        $id   = !empty($data['id']) ? (int) $data['id'] : 0;

        if ($task === 'leadership.apply') {
            $this->setRedirect('index.php?option=com_clubleadership&view=leadership&id=' . $id);
        } else {
            $this->setRedirect('index.php?option=com_clubleadership&view=leaderships');
        }

        return true;
    }

    public function cancel($key = null)
    {
        $this->setRedirect('index.php?option=com_clubleadership&view=leaderships');
    }

    /**
     * Shared POST+token+authorisation guard for state-changing batch actions.
     */
    private function guardState()
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            $this->setRedirect('index.php?option=com_clubleadership&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }
        if (!Session::checkToken()) {
            $this->setRedirect('index.php?option=com_clubleadership&view=leaderships', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }

        $user = Factory::getUser();
        if (!$user->authorise('core.edit.state', 'com_clubleadership') && !$user->authorise('core.delete', 'com_clubleadership')) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect('index.php?option=com_clubleadership&view=leaderships');
            return false;
        }

        return true;
    }

    public function delete()
    {
        if (!$this->guardState()) {
            return false;
        }

        $model = $this->getModel('Leadership', 'ClubLeadershipModel');
        $ids   = array_map('intval', (array) $this->input->post->get('cid', array(), 'array'));
        $ids   = array_filter($ids, function ($id) { return $id > 0; });

        if (empty($ids)) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_clubleadership&view=leaderships');
            return false;
        }

        if ($model->delete($ids)) {
            $this->setMessage(Text::_('COM_CLUBLEADERSHIP_ITEMS_DELETED'));
        } else {
            $this->setMessage(Text::_('COM_CLUBLEADERSHIP_ERROR_DELETING'), 'error');
        }

        $this->setRedirect('index.php?option=com_clubleadership&view=leaderships');
    }

    public function publish()
    {
        if (!$this->guardState()) {
            return false;
        }

        $model = $this->getModel('Leadership', 'ClubLeadershipModel');
        $ids   = array_map('intval', (array) $this->input->post->get('cid', array(), 'array'));
        $ids   = array_filter($ids, function ($id) { return $id > 0; });
        // From the toolbar the publish/unpublish buttons pass state via the
        // 'state' request var; 1 = publish, 0 = unpublish. The jgrid toggle in
        // the list passes it through 'task' (leadership.publish instead of
        // leadership.unpublish), so derive from the task when absent.
        $state = $this->input->getInt('state', null);
        if ($state === null) {
            $task  = (string) $this->input->getCmd('task');
            $state = (strpos($task, 'unpublish') !== false) ? 0 : 1;
        }

        if (empty($ids)) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_clubleadership&view=leaderships');
            return false;
        }

        if ($model->publish($ids, $state)) {
            $this->setMessage(Text::_('COM_CLUBLEADERSHIP_ITEMS_UPDATED'));
        }

        $this->setRedirect('index.php?option=com_clubleadership&view=leaderships');
    }

    public function reorder()
    {
        if (!$this->guardState()) {
            return false;
        }

        $model     = $this->getModel('Leadership', 'ClubLeadershipModel');
        $id        = $this->input->getInt('id');
        $direction = $this->input->getInt('direction', 1);

        if ($id < 1) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_clubleadership&view=leaderships');
            return false;
        }

        if ($model->reorderSingle($id, $direction)) {
            $this->setMessage(Text::_('COM_CLUBLEADERSHIP_ITEM_REORDERED'));
        }

        $this->setRedirect('index.php?option=com_clubleadership&view=leaderships');
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
