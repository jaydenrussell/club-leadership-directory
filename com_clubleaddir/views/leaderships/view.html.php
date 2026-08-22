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

        // Predefined display order within each group (officers/staff by role rank,
        // directors by manual ordering).
        require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';
        foreach ($groups as $type => $items) {
            $groups[$type] = ClubleaddirHelper::sortForDisplay($items);
        }

        $this->groups = $groups;

        // Resolve vacant-enquiry settings from the module (single source of truth),
        // so the component page and the module render the same Joomla contact URL.
        // Falls back to component global config when no module is published.
        require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';
        $vacancy = ClubleaddirHelper::getModuleVacancySettings();
        $this->vacantContactId     = (int) $vacancy->contact_id;
        $this->vacancyDefaultEmail = (string) $vacancy->email;

        // Maintain the active menu item's params for any other layout options.
        $app  = \Joomla\CMS\Factory::getApplication();
        $menu = $app->getMenu()->getActive();
        $this->params = $menu ? $menu->getParams() : new \Joomla\CMS\Registry\Registry();

        parent::display($tpl);
    }
}
