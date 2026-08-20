<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/store/Store.php';

class ModClubleaddirHelper
{
    /**
     * Read published leadership records from the standalone store and group
     * them by type. No Joomla/CB database is touched.
     *
     * @return array
     */
    public static function getLeadership()
    {
        $store  = ClubleaddirStore::getInstance();
        $rows   = $store->getAll(array('published' => 1));

        $result = array(
            'officers'        => array(),
            'directors'       => array(),
            'directors_league'=> array(),
            'staff'           => array(),
        );

        foreach ($rows as $item) {
            switch ($item->type) {
                case 'officer':
                    $result['officers'][] = $item;
                    break;
                case 'director':
                    $result['directors'][] = $item;
                    break;
                case 'director_league':
                    $result['directors_league'][] = $item;
                    break;
                case 'staff':
                    $result['staff'][] = $item;
                    break;
            }
        }

        // Apply the predefined display order (officers by role rank, staff by
        // role rank, directors by manual ordering) within each group.
        require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';
        foreach ($result as $type => $items) {
            $result[$type] = ClubleaddirHelper::sortForDisplay($items);
        }

        return $result;
    }
}
