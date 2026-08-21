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
use Joomla\CMS\Router\Route;
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
$vacantContactId     = (int) $paramsData->get('vacant_contact_id', 0);
$vacancyDefaultEmail = (string) $paramsData->get('vacancy_default_email', '');
$showTerm        = (int) $paramsData->get('show_term', 1);
$circularAvatars = (int) $paramsData->get('circular_avatars', 1);
$photoSize       = (int) $paramsData->get('photo_size', 120);
if ($photoSize < 40) { $photoSize = 40; }
if ($photoSize > 320) { $photoSize = 320; }
$maxItems        = (int) $paramsData->get('max_items', 0);
$headerTag       = preg_replace('/[^a-z0-9]/i', '', $paramsData->get('header_tag', 'h3'));
if (!in_array($headerTag, array('h1','h2','h3','h4','p','div'), true)) {
    $headerTag = 'h3';
}
$introText      = trim($paramsData->get('intro_text', ''));
$showSectionTitles = (int) $paramsData->get('show_section_titles', 1);

$rawData = ModClubleaddirHelper::getLeadership();
if ($maxItems > 0) {
    foreach (array('officers', 'directors', 'staff', 'league') as $grp) {
        if (!empty($rawData[$grp]) && is_array($rawData[$grp])) {
            $rawData[$grp] = array_slice($rawData[$grp], 0, $maxItems);
        }
    }
}

require ModuleHelper::getLayoutPath('mod_clubleaddir', $paramsData->get('layout', 'default'));
