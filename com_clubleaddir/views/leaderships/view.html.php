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

        // Single source of truth for all display / vacancy settings = the
        // component global config (Options button in the component admin).
        require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';
        $cfg = ClubleaddirHelper::getGlobalConfig();
        $this->vacantContactId     = (int) $cfg->get('vacant_contact_id', 0);
        $this->vacancyDefaultEmail = (string) $cfg->get('vacancy_default_email', '');

        // Per-section photo visibility — overrides coming from the component
        // config; no separate module or menu-item params needed.
        $this->showPhotos = array(
            'officers'         => (int) $cfg->get('show_photos_officers', 1),
            'directors'        => (int) $cfg->get('show_photos_directors', 0),
            'directors_league' => (int) $cfg->get('show_photos_directors', 0),
            'staff'            => (int) $cfg->get('show_photos_staff', 0),
        );

        parent::display($tpl);
    }
}
