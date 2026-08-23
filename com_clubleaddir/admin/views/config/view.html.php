<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class ClubleaddirViewConfig extends HtmlView
{
	protected $form;

	public function display($tpl = null)
	{
		$this->form = $this->get('Form');
		ToolbarHelper::apply('config.apply');
		ToolbarHelper::save('config.save');
		ToolbarHelper::cancel('config.cancel');
		parent::display($tpl);
	}
}
