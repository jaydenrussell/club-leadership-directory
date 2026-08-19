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

class ClubleaddirViewLeadership extends HtmlView
{
    protected $item;

    public function display($tpl = null)
    {
        $id         = Factory::getApplication()->input->getInt('id', 0);
        $model      = $this->getModel('Leadership');
        $this->item = $model->getItem($id);

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar()
    {
        $isNew = empty($this->item->id);

        ToolbarHelper::title(
            Text::_($isNew ? 'COM_CLUBLEADDIRECTION_ADD_LEADERSHIP' : 'COM_CLUBLEADDIRECTION_EDIT_LEADERSHIP'),
            'users'
        );

        ToolbarHelper::apply('leadership.apply');
        ToolbarHelper::save('leadership.save');
        ToolbarHelper::cancel('leadership.cancel');
    }
}
