<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

require_once __DIR__ . '/helper.php';

// Every behaviour option comes from the component's global Options — the
// single source of configuration for the whole extension. This module only
// decides WHERE the directory appears; the component Options decide WHAT it
// looks like.
$cfg = ModClubleaddirHelper::getConfig();

$rawData = ModClubleaddirHelper::getLeadership();

$maxItems = (int) $cfg->get('max_items', 0);

if ($maxItems > 0) {
	foreach (array('officers', 'directors', 'directors_league', 'staff') as $group) {
		if (!empty($rawData[$group]) && is_array($rawData[$group])) {
			$rawData[$group] = array_slice($rawData[$group], 0, $maxItems);
		}
	}
}

require ModuleHelper::getLayoutPath('mod_clubleaddir', $params->get('layout', 'default'));
