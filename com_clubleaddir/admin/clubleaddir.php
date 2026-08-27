<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

// Admin dispatcher: bootstraps the MVC controller and runs the requested task.
$input = Factory::getApplication()->input;
if (!$input->get('view')) {
    $input->set('view', 'leaderships');
}
$controller = BaseController::getInstance('Clubleaddir');
$controller->execute($input->get('task'));
$controller->redirect();
