<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;

require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/store/Store.php';

class ClubleaddirViewLeaderships extends HtmlView
{
    public $groups = array();

    public function display($tpl = null)
    {
        $store = ClubleaddirStore::getInstance();
        $rows  = $store->getAll(array('published' => 1));

        $groups = array(
            'officers'         => array(),
            'directors'        => array(),
            'directors_league' => array(),
            'staff'            => array(),
        );

        foreach ($rows as $item) {
            switch ($item->type) {
                case 'officer':        $groups['officers'][] = $item; break;
                case 'director':       $groups['directors'][] = $item; break;
                case 'director_league':$groups['directors_league'][] = $item; break;
                case 'staff':          $groups['staff'][] = $item; break;
            }
        }

        $this->groups = $groups;

        parent::display($tpl);
    }
}
