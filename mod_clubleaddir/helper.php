<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';

class ModClubleaddirHelper
{
	/**
	 * Component global params — the single source of configuration.
	 *
	 * @return \Joomla\CMS\Registry\Registry
	 */
	public static function getConfig()
	{
		return ClubleaddirHelper::getGlobalConfig();
	}

	/**
	 * Published leadership records from the isolated store, grouped by type.
	 * No Joomla database table is touched.
	 *
	 * @return array
	 */
	public static function getLeadership()
	{
		try {
			return ClubleaddirHelper::getGroupedRoster();
		} catch (\Throwable $e) {
			return array(
				'officers'         => array(),
				'directors'        => array(),
				'directors_league' => array(),
				'staff'            => array(),
			);
		}
	}
}
