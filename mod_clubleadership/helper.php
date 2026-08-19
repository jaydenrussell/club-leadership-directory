<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_clubleadership
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once JPATH_ADMINISTRATOR . '/components/com_clubleadership/store/Store.php';

class ModClubLeadershipHelper
{
    /**
     * Read published leadership records from the standalone store and group
     * them by type. No Joomla/CB database is touched.
     *
     * @return array
     */
    public static function getLeadership()
    {
        $store  = ClubLeadershipStore::getInstance();
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

        return $result;
    }
}
