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
    protected $termOptions;
    protected $backendName;
    protected $filters = array();

    public function display($tpl = null)
    {
        $this->params = ClubleaddirHelper::getGlobalConfig();
        $model = $this->getModel();

        if (!$model) {
            $this->items            = array();
            $this->typeOptions      = array();
            $this->publishedOptions = array();
            $this->statusOptions    = array();
            $this->termOptions      = array();
            $this->backendName      = '';
        } else {
            $this->items            = $model->getItems();
            $this->typeOptions      = $model->getTypeOptions();
            $this->publishedOptions = $model->getPublishedOptions();
            $this->statusOptions    = $model->getStatusOptions();
            $this->termOptions      = $model->getTermOptions();
            $this->backendName      = $model->getBackendName();
        }

        $this->filters = array(
            'type'      => $model ? $model->getFilterValue('type') : '',
            'published' => $model ? $model->getFilterValue('published') : '',
            'status'    => $model ? $model->getFilterValue('status') : '',
            'term'      => $model ? $model->getFilterValue('term') : '',
            'search'    => $model ? $model->getFilterValue('search') : '',
        );

        ClubleaddirHelper::addSubmenu('leaderships');
        $this->addToolbar();
        $this->checkVacancyConfig();

        parent::display($tpl);
    }

    protected function checkVacancyConfig()
    {
        try {
            $store   = ClubleaddirStore::getInstance();
            $rows    = $store->getAll(array('published' => 1));
            $hasVacant = false;
            foreach ($rows as $item) {
                if (!empty($item->vacant)) {
                    $hasVacant = true;
                    break;
                }
            }
            if (!$hasVacant) {
                return;
            }

            $vacancy = ClubleaddirHelper::getGlobalConfig();
            if ((int) $vacancy->get('vacant_contact_id', 0) > 0 || trim((string) $vacancy->get('vacancy_default_email', '')) !== '') {
                return;
            }

            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('COM_CLUBLEADDIR_VACANCY_CONFIG_WARNING'), 'warning');
        } catch (\Throwable $e) {
            // Never fatal: config warning is advisory only.
        }
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
            ToolbarHelper::trash('leadership.trash', 'JTOOLBAR_TRASH');
            ToolbarHelper::custom('leadership.saveorder', 'icon-menu', '', 'COM_CLUBLEADDIR_TOOLBAR_SAVE_ORDER', false);
        }
        if ($canDo->get('core.delete')) {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'leadership.delete', 'JTOOLBAR_DELETE');
        }
        if ($canDo->get('core.admin')) {
            ToolbarHelper::preferences('com_clubleaddir');
        }
    }
}
