<?php
/**
 * Package installer script for Club Leadership Directory.
 *
 * On install/update it ensures the "Club Leadership Directory Update" update
 * site is ENABLED so Joomla's Update Manager can detect new releases. Joomla
 * sometimes registers package-declared <updateservers> as disabled, which
 * silently breaks update detection; this script guarantees it is enabled.
 *
 * @package     Joomla.Administrator
 * @subpackage  pkg_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Package installer script.
 */
class pkg_clubleaddirInstallerScript
{
    /**
     * Enable the package's update site after install/update.
     *
     * @param   string  $type  'install' | 'update' | 'discover_install'
     * @param   object  $parent  Installer parent
     *
     * @return  void
     */
    public function postflight($type, $parent)
    {
        $this->enableUpdateSite();
    }

    /**
     * Set enabled = 1 for the Club Leadership Directory update site.
     *
     * @return  void
     */
    private function enableUpdateSite()
    {
        try {
            $db = Factory::getDbo();
        } catch (\Throwable $e) {
            return;
        }

        $name = 'Club Leadership Directory Update';

        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__update_sites'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('name') . ' = ' . $db->quote($name));
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            // Non-fatal: update detection simply requires the site enabled.
        }
    }
}
