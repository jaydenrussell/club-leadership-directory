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
        $model = $this->getModel();

        $this->items            = $model->getItems();
        $this->typeOptions      = $model->getTypeOptions();
        $this->publishedOptions = $model->getPublishedOptions();
        $this->statusOptions    = $model->getStatusOptions();
        $this->termOptions      = $model->getTermOptions();
        $this->backendName      = $model->getBackendName();
        $this->filters = array(
            'type'      => $model->getFilterValue('type'),
            'published' => $model->getFilterValue('published'),
            'status'    => $model->getFilterValue('status'),
            'term'      => $model->getFilterValue('term'),
            'search'    => $model->getFilterValue('search'),
        );

        ClubleaddirHelper::addSubmenu('leaderships');
        $this->addToolbar();

        // Surface a visible backend warning when vacant positions are published
        // but no Vacant Enquiry Contact / Vacancy Default Email is configured,
        // with a direct link to the module's Contact Settings to fix it.
        $this->checkVacancyConfig();

        parent::display($tpl);
    }

    /**
     * Warn in the admin UI when a published vacant position exists but the
     * vacant-enquiry target (module/component Contact Settings) is empty.
     */
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

            $vacancy = ClubleaddirHelper::getModuleVacancySettings();
            if ((int) $vacancy->contact_id > 0 || trim((string) $vacancy->email) !== '') {
                return;
            }

            $msg = Text::_('COM_CLUBLEADDIR_VACANCY_CONFIG_WARNING');
            $link = ClubleaddirHelper::moduleSettingsLink();
            if ($link !== '') {
                $msg .= ' <a href="' . $link . '">' . Text::_('COM_CLUBLEADDIR_VACANCY_CONFIG_LINK') . '</a>';
            }
            Factory::getApplication()->enqueueMessage($msg, 'warning');
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
            // Recoverable delete: moves selected records to the Trash (published = -2)
            // so they can be restored later. Permanent Delete remains available.
            ToolbarHelper::trash('leadership.trash', 'JTOOLBAR_TRASH');
            // Joomla 3.10 has no ToolbarHelper::saveorder(); use a custom button
            // that submits the order[] inputs via the leadership.saveorder task.
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
