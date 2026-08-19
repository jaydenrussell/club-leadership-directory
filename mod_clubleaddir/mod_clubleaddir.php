<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

require_once __DIR__ . '/helper.php';

$app  = Factory::getApplication();
$doc  = Factory::getDocument();
$user = Factory::getUser();

$paramsData = $module->params instanceof \Joomla\Registry\Registry
    ? $module->params
    : new \Joomla\Registry\Registry($module->params);

$moduleId        = $module->id;
$moduleClassSfx  = htmlspecialchars($paramsData->get('module_class_sfx', ''), ENT_QUOTES, 'UTF-8');
$displayTitle    = $paramsData->get('display_title', 'Club Leadership');
$showOfficers    = (int) $paramsData->get('show_officers', 1);
$showDirectors   = (int) $paramsData->get('show_directors', 1);
$showStaff       = (int) $paramsData->get('show_staff', 1);
$showPhotosOfficers  = (int) $paramsData->get('show_photos_officers', 1);
$showPhotosDirectors = (int) $paramsData->get('show_photos_directors', 0);
$showPhotosStaff     = (int) $paramsData->get('show_photos_staff', 0);
$showContact     = !$paramsData->get('require_login_for_contact', 1) || !$user->guest;
$contactHiddenText   = $paramsData->get('contact_hidden_text', Text::_('MOD_CLUBLEADDIRECTION_LOGIN_TO_VIEW'));

$rawData = ModClubleaddirHelper::getLeadership();

require ModuleHelper::getLayoutPath('mod_clubleaddir', $paramsData->get('layout', 'default'));
