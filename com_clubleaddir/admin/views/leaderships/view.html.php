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
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

require_once __DIR__ . '/../../helpers.php';

class ClubleaddirViewLeaderships extends HtmlView
{
    protected $items;
    protected $typeOptions;
    protected $publishedOptions;
    protected $statusOptions;
    protected $backendName;
    protected $filters = array();

    public function display($tpl = null)
    {
        $model = $this->getModel();

        $this->items            = $model->getItems();
        $this->typeOptions      = $model->getTypeOptions();
        $this->publishedOptions = $model->getPublishedOptions();
        $this->statusOptions    = $model->getStatusOptions();
        $this->backendName      = $model->getBackendName();
        $this->filters = array(
            'type'      => $model->getFilterValue('type'),
            'published' => $model->getFilterValue('published'),
            'status'    => $model->getFilterValue('status'),
            'search'    => $model->getFilterValue('search'),
        );

        ClubleaddirHelper::addSubmenu('leaderships');
        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar()
    {
        $canDo = ClubleaddirHelper::getActions();

        ToolbarHelper::title(Text::_('COM_CLUBLEADDIR_MENU_LEADERSHIP'), 'users');

        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('leadership.add');
        }
        if ($canDo->get('core.edit.state')) {
            ToolbarHelper::publish('leadership.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('leadership.publish', 'JTOOLBAR_UNPUBLISH', true);
            // Joomla 3.10 has no ToolbarHelper::saveorder(); use a custom button
            // that submits the order[] inputs via the leadership.saveorder task.
            ToolbarHelper::custom('leadership.saveorder', 'icon-menu', '', 'JTOOLBAR_SAVE_ORDER', false);
        }
        if ($canDo->get('core.delete')) {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'leadership.delete', 'JTOOLBAR_DELETE');
        }
        if ($canDo->get('core.admin')) {
            ToolbarHelper::preferences('com_clubleaddir');
        }
    }
}
