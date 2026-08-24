<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Registry\Registry;

require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';

class ClubleaddirViewLeaderships extends HtmlView
{
	public $groups = array();

	public function display($tpl = null)
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/store/Store.php';

		try {
			$this->groups = ClubleaddirHelper::getGroupedRoster();
		} catch (\Throwable $e) {
			$this->groups = array(
				'officers'         => array(),
				'directors'        => array(),
				'directors_league' => array(),
				'staff'            => array(),
			);
		}

		// Single source of truth for all display / vacancy settings = the
		// component global config (Options button in the component admin).
		$this->displayOptions = ClubleaddirHelper::displayOptions();

		// Menu-item params drive only the standard "Page Display" behaviour
		// (page heading, page class suffix). Behaviour never comes from here.
		$app         = JFactory::getApplication();
		$active      = $app->getMenu()->getActive();
		$this->params = $active ? clone $active->params : new Registry;

		parent::display($tpl);
	}
}
