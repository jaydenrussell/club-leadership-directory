<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Site dispatcher: bootstraps the MVC controller and runs the requested task.
$controller = JControllerLegacy::getInstance('Clubleaddir');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();
