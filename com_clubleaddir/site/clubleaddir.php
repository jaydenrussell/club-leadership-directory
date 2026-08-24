<?php
/**
 * Site dispatcher: bootstraps the MVC controller and runs the requested task.
 *
 * @package     Joomla.Site
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$input = JFactory::getApplication()->input;

if (!$input->getCmd('view')) {
	$input->set('view', 'leaderships');
}

$controller = JControllerLegacy::getInstance('Clubleaddir');
$controller->execute($input->getCmd('task'));
$controller->redirect();
